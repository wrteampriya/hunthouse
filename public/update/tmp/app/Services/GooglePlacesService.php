<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GooglePlacesService
{
    public static string $apiKey = '';

    private static string $detailsFieldMask = 'id,formattedAddress,addressComponents,location,viewport';

    public function __construct()
    {
        $placeApiKey = HelperService::getSettingData('place_api_key');
        self::$apiKey = (string) $placeApiKey;
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

    public static function autocomplete(string $input, ?string $locale = null, ?array $regionCodes = null): array
    {
        if (self::$apiKey === '') {
            return [];
        }

        $locale = $locale ?? self::systemDefaultLocale();
        $normalized = mb_strtolower(trim($input));

        // Restrict suggestions to admin-added countries. Places API (New) accepts
        // up to 15 region codes — beyond that we skip the restriction and rely on
        // server-side validation/nearest-country resolution as the backstop.
        // Callers may instead pass their own $regionCodes (e.g. a single
        // user-selected country) to scope suggestions more tightly.
        if (is_null($regionCodes)) {
            $regionCodes = Cache::store('gplaces')->remember('gplaces:region-codes', now()->addMinutes(10), function () {
                return Country::where('status', 1)->pluck('code')->map(fn ($code) => strtoupper($code))->values()->all();
            });
        }

        $cacheKey = 'gplaces:ac:'.md5($normalized.'|'.$locale.'|'.implode(',', $regionCodes));

        $cached = Cache::store('gplaces')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $requestBody = [
            'input' => $input,
            'languageCode' => $locale,
        ];
        if (! empty($regionCodes) && count($regionCodes) <= 15) {
            $requestBody['includedRegionCodes'] = $regionCodes;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => self::$apiKey,
        ])->post('https://places.googleapis.com/v1/places:autocomplete', $requestBody);

        if (! $response->successful()) {
            return [];
        }

        $json = (array) $response->json();
        if (is_array($json) && ! empty($json)) {
            Cache::store('gplaces')->put($cacheKey, $json, now()->addDays(7));
        }

        return $json;
    }

    public static function detailsOrGeocode(?string $placeId = null, ?float $latitude = null, ?float $longitude = null, ?string $locale = null): array
    {
        if (self::$apiKey === '') {
            return [];
        }

        $locale = $locale ?? self::systemDefaultLocale();

        if (empty($placeId)) {
            // Step 1: Try to get place_id from cache (place_id is language-agnostic, so no locale in this key)
            $geoCacheKey = 'gplaces:geocode:latlng:'.$latitude.','.$longitude;
            $geocode = Cache::store('gplaces')->get($geoCacheKey);

            if (! $geocode) {
                // Not in cache → call API (reverse geocoding stays on the legacy Geocoding API,
                // which is a separate product from the Places API and unaffected by this migration)
                $geocode = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'latlng' => $latitude.','.$longitude,
                    'key' => self::$apiKey,
                ])->json();

                if (($geocode['status'] ?? null) === 'OK') {
                    Cache::store('gplaces')->put($geoCacheKey, $geocode, now()->addDays(7));
                }
            }

            $placeId = $geocode['results'][0]['place_id'] ?? null;
            if (! $placeId) {
                return [];
            }
        }

        $cacheKey = 'gplaces:details:pid:'.$placeId.'|'.$locale;

        $cached = Cache::store('gplaces')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => self::$apiKey,
            'X-Goog-FieldMask' => self::$detailsFieldMask,
        ])->get("https://places.googleapis.com/v1/places/{$placeId}", [
            'languageCode' => $locale,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $json = (array) $response->json();
        if (is_array($json) && ! empty($json)) {
            Cache::store('gplaces')->put($cacheKey, $json, now()->addDays(7));
        }

        return $json;
    }

    /**
     * Forward-geocode a plain address/place name (e.g. a country name) to its
     * centroid coordinates. Used to derive a country's reference point without
     * asking the admin to enter latitude/longitude by hand.
     */
    public static function geocodeAddress(string $address): ?array
    {
        if (self::$apiKey === '' || trim($address) === '') {
            return null;
        }

        $cacheKey = 'gplaces:geocode:addr:'.md5(mb_strtolower(trim($address)));
        $cached = Cache::store('gplaces')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'key' => self::$apiKey,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $json = (array) $response->json();
        if (($json['status'] ?? null) !== 'OK') {
            return null;
        }

        $location = $json['results'][0]['geometry']['location'] ?? null;
        if (! $location) {
            return null;
        }

        $result = ['lat' => (float) $location['lat'], 'lng' => (float) $location['lng']];
        Cache::store('gplaces')->put($cacheKey, $result, now()->addDays(30));

        return $result;
    }
}
