<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * Free map provider (OpenStreetMap path).
 * Search + place details + reverse geocode powered by the GeoNames web service.
 * Completely independent from GooglePlacesService — its own endpoints, cache store and response shape.
 *
 * GeoNames free tier: 10,000 credits/day, 1,000/hour per username.
 * Responses are cached 7 days in the dedicated `osmmaps` store to stay well under those limits.
 */
class GeoNamesMapService
{
    public static string $username = '';

    protected const BASE_URL = 'http://api.geonames.org';

    public function __construct()
    {
        $username = HelperService::getSettingData('geonames_username');
        self::$username = (string) $username;
    }

    /**
     * Place list/details always respond in English, regardless of the
     * caller's country or the system-configured default language.
     */
    private static function systemDefaultLocale(): string
    {
        return 'en';
    }

    /**
     * Place list/details always respond in English — no per-country
     * language resolution.
     */
    public static function localeForCountry(): string
    {
        return self::systemDefaultLocale();
    }

    /**
     * Search places by text. Mirrors the role of Google Places Autocomplete.
     * Returns: ['predictions' => [['description','place_id','city','state','country','lat','lng'], ...]]
     */
    public static function autocomplete(string $input, ?string $locale = null, ?array $countryCodes = null): array
    {
        if (self::$username === '' || trim($input) === '') {
            return ['predictions' => []];
        }

        $locale = $locale ?? self::systemDefaultLocale();
        $normalized = mb_strtolower(trim($input));

        // Restrict suggestions to admin-added countries, same as the Google path.
        // Callers may instead pass their own $countryCodes (e.g. a single
        // user-selected country) to scope suggestions more tightly.
        if (is_null($countryCodes)) {
            $countryCodes = Cache::store('osmmaps')->remember('osm:region-codes', now()->addMinutes(10), function () {
                return Country::where('status', 1)->pluck('code')->map(fn ($code) => strtoupper($code))->values()->all();
            });
        }

        $cacheKey = 'osm:ac:'.md5($normalized.'|'.$locale.'|'.implode(',', $countryCodes));

        $cached = Cache::store('osmmaps')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $query = [
            'q' => $input,
            'maxRows' => 7,
            'featureClass' => 'P', // populated places (cities, towns, villages)
            'style' => 'MEDIUM',
            'lang' => $locale,
            'username' => self::$username,
        ];

        // GeoNames' `country` param restricts results and is repeatable, but must be
        // sent as repeated bare keys (country=IN&country=US), not bracketed array
        // notation — so it's appended to the query string manually.
        $queryString = http_build_query($query);
        foreach ($countryCodes as $code) {
            $queryString .= '&country='.urlencode($code);
        }

        $response = Http::get(self::BASE_URL.'/searchJSON?'.$queryString);

        if (! $response->successful()) {
            return ['predictions' => []];
        }

        $json = (array) $response->json();
        // GeoNames signals errors (e.g. limit exceeded / invalid user) via a `status` object.
        if (isset($json['status'])) {
            return ['predictions' => []];
        }

        $predictions = [];
        foreach (($json['geonames'] ?? []) as $place) {
            $predictions[] = self::mapPlace($place);
        }

        $result = ['predictions' => $predictions];
        Cache::store('osmmaps')->put($cacheKey, $result, now()->addDays(7));

        return $result;
    }

    /**
     * Resolve a single place — either by GeoNames id (a picked prediction) or by lat/lng (reverse geocode on pin drag).
     * Returns: ['result' => ['city','state','country','address','geometry' => ['location' => ['lat','lng']]]]
     */
    public static function detailsOrReverse(?string $geonameId = null, ?float $latitude = null, ?float $longitude = null, ?string $locale = null): array
    {
        if (self::$username === '') {
            return ['result' => null];
        }

        $locale = $locale ?? self::systemDefaultLocale();

        if (! empty($geonameId)) {
            return self::details($geonameId, $locale);
        }

        return self::reverse($latitude, $longitude, $locale);
    }

    protected static function details(string $geonameId, string $locale): array
    {
        $cacheKey = 'osm:details:'.$geonameId.'|'.$locale;
        $cached = Cache::store('osmmaps')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::get(self::BASE_URL.'/getJSON', [
            'geonameId' => $geonameId,
            'style' => 'MEDIUM',
            'lang' => $locale,
            'username' => self::$username,
        ]);

        if (! $response->successful()) {
            return ['result' => null];
        }

        $json = (array) $response->json();
        if (isset($json['status']) || empty($json)) {
            return ['result' => null];
        }

        $result = ['result' => self::mapResult($json)];
        Cache::store('osmmaps')->put($cacheKey, $result, now()->addDays(7));

        return $result;
    }

    protected static function reverse(?float $latitude, ?float $longitude, string $locale): array
    {
        if (is_null($latitude) || is_null($longitude)) {
            return ['result' => null];
        }

        $cacheKey = 'osm:rev:'.$latitude.','.$longitude.'|'.$locale;
        $cached = Cache::store('osmmaps')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::get(self::BASE_URL.'/findNearbyPlaceNameJSON', [
            'lat' => $latitude,
            'lng' => $longitude,
            'style' => 'MEDIUM',
            'lang' => $locale,
            'username' => self::$username,
        ]);

        if (! $response->successful()) {
            return ['result' => null];
        }

        $json = (array) $response->json();
        if (isset($json['status'])) {
            return ['result' => null];
        }

        $place = $json['geonames'][0] ?? null;
        if (! $place) {
            return ['result' => null];
        }

        // Keep the dragged coordinates as the source of truth; GeoNames returns the nearest place's centroid.
        $mapped = self::mapResult($place);
        $mapped['geometry']['location'] = ['lat' => $latitude, 'lng' => $longitude];

        $result = ['result' => $mapped];
        Cache::store('osmmaps')->put($cacheKey, $result, now()->addDays(7));

        return $result;
    }

    /** Shape a search hit into a prediction the frontend can use without a second request. */
    protected static function mapPlace(array $place): array
    {
        $city = $place['name'] ?? '';
        $state = $place['adminName1'] ?? '';
        $country = $place['countryName'] ?? '';

        return [
            'description' => implode(', ', array_filter([$city, $state, $country])),
            'place_id' => (string) ($place['geonameId'] ?? ''),
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'country_code' => $place['countryCode'] ?? null,
            'lat' => isset($place['lat']) ? (float) $place['lat'] : null,
            'lng' => isset($place['lng']) ? (float) $place['lng'] : null,
        ];
    }

    /** Shape a place/reverse hit into the result the frontend writes into the form fields. */
    protected static function mapResult(array $place): array
    {
        $city = $place['name'] ?? '';
        $state = $place['adminName1'] ?? '';
        $country = $place['countryName'] ?? '';

        return [
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'country_code' => $place['countryCode'] ?? null,
            // GeoNames is a gazetteer, not a street-address geocoder, so this is a place-level address.
            'address' => implode(', ', array_filter([$city, $state, $country])),
            'geometry' => [
                'location' => [
                    'lat' => isset($place['lat']) ? (float) $place['lat'] : null,
                    'lng' => isset($place['lng']) ? (float) $place['lng'] : null,
                ],
            ],
        ];
    }

    /**
     * Forward-geocode a country name to its centroid coordinates, using GeoNames'
     * own country-feature records (featureCode PCLI). Used to derive a country's
     * reference point without asking the admin to enter latitude/longitude by hand.
     */
    public static function geocodeAddress(string $address): ?array
    {
        if (self::$username === '' || trim($address) === '') {
            return null;
        }

        $cacheKey = 'osm:geocode:addr:'.md5(mb_strtolower(trim($address)));
        $cached = Cache::store('osmmaps')->get($cacheKey);
        if ($cached !== null) {
            return (array) $cached;
        }

        $response = Http::get(self::BASE_URL.'/searchJSON', [
            'name_equals' => $address,
            'featureCode' => 'PCLI', // independent political entity (country)
            'maxRows' => 1,
            'username' => self::$username,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $json = (array) $response->json();
        $place = $json['geonames'][0] ?? null;
        if (! $place || ! isset($place['lat'], $place['lng'])) {
            return null;
        }

        $result = ['lat' => (float) $place['lat'], 'lng' => (float) $place['lng']];
        Cache::store('osmmaps')->put($cacheKey, $result, now()->addDays(30));

        return $result;
    }
}
