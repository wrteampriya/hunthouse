<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GooglePlacesService
{
    public static string $apiKey = '';

    public function __construct()
    {
        $placeApiKey = HelperService::getSettingData('place_api_key');
        self::$apiKey = (string) $placeApiKey;
    }

    public static function autocomplete(string $input, ?string $locale = null): array
    {
        if (self::$apiKey === '') {
            return [];
        }

        $normalized = mb_strtolower(trim($input));
        $cacheKey = 'gplaces:ac:'.md5($normalized.'|'.($locale ?? app()->getLocale()));

        $cached = Cache::store('gplaces')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'key' => self::$apiKey,
            'input' => $input,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $json = (array) $response->json();
        if (is_array($json) && ($json['status'] ?? null) === 'OK') {
            Cache::store('gplaces')->put($cacheKey, $json, now()->addDays(7));
        }

        return $json;
    }

    public static function detailsOrGeocode(?string $placeId = null, ?float $latitude = null, ?float $longitude = null): array
    {
        if (self::$apiKey === '') {
            return [];
        }

        $params = ['key' => self::$apiKey];
        $cacheKey = '';
        $endpoint = '';

        if (! empty($placeId)) {
            // Directly fetch Place Details
            $params['place_id'] = $placeId;
            $params['fields'] = 'address_components,geometry,formatted_address';
            $cacheKey = 'gplaces:details:pid:'.$placeId;
            $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json';
        } else {
            // Step 1: Try to get place_id from cache
            $geoCacheKey = 'gplaces:geocode:latlng:'.$latitude.','.$longitude;
            $geocode = Cache::store('gplaces')->get($geoCacheKey);

            if (! $geocode) {
                // Not in cache → call API
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

            // Step 2: Fetch Place Details using place_id
            $params['place_id'] = $placeId;
            $params['fields'] = 'address_components,geometry,formatted_address';
            $cacheKey = 'gplaces:details:pid:'.$placeId;
            $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json';
        }

        // Place Details cache
        $cached = Cache::store('gplaces')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::get($endpoint, $params);
        if (! $response->successful()) {
            return [];
        }

        $json = (array) $response->json();
        if (is_array($json) && ($json['status'] ?? null) === 'OK') {
            Cache::store('gplaces')->put($cacheKey, $json, now()->addDays(7));
        }

        return $json;
    }
}
