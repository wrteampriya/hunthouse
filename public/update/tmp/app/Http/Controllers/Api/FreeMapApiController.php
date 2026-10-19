<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\ApiResponseService;
use App\Services\GeoNamesMapService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/*
 * Free map provider endpoints (OpenStreetMap path), powered by GeoNames.
 * Kept separate from MapApiController (Google) so the two providers never share code.
 */
class FreeMapApiController extends Controller
{
    public function getOsmPlacesListData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'input' => 'required|string',
                'country_id' => 'nullable|exists:countries,id',
            ], [
                'input.required' => trans('Input is required'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $input = (string) $request->query('input', '');

            // When the caller has a country selected, restrict suggestions to
            // just that country instead of the default "all admin-added
            // countries" list.
            $countryCodes = null;
            if ($request->filled('country_id')) {
                $selectedCountry = Country::find($request->country_id);
                if ($selectedCountry && ! empty($selectedCountry->code)) {
                    $countryCodes = [strtoupper($selectedCountry->code)];
                }
            }

            $locale = GeoNamesMapService::localeForCountry();

            $service = new GeoNamesMapService;
            $responseData = $service->autocomplete($input, $locale, $countryCodes);

            return ApiResponseService::successResponse('Data Fetched Successfully', $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getOsmPlacesListData error: '.$e->getMessage());

            return ApiResponseService::errorResponse();
        }
    }

    public function getOsmPlaceDetailsData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required_without:place_id|numeric',
            'longitude' => 'required_without:place_id|numeric',
            'place_id' => 'required_without_all:latitude,longitude|string',
            'country_id' => 'nullable|exists:countries,id',
        ], [
            'latitude.required_without' => trans('Latitude is required when place_id is null'),
            'longitude.required_without' => trans('Longitude is required when place_id is null'),
            'latitude.numeric' => trans('Latitude must be a number'),
            'longitude.numeric' => trans('Longitude must be a number'),
            'place_id.required_without_all' => trans('Place ID is required when latitude and longitude are null'),
            'place_id.string' => trans('Place ID must be a string'),
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $latitude = $request->has('latitude') ? (float) $request->latitude : null;
            $longitude = $request->has('longitude') ? (float) $request->longitude : null;
            $placeId = $request->has('place_id') ? $request->place_id : null;

            $locale = GeoNamesMapService::localeForCountry();

            $service = new GeoNamesMapService;
            $responseData = $service->detailsOrReverse($placeId, $latitude, $longitude, $locale);

            $resolved = $this->resolveCountryForPlace($responseData, $latitude, $longitude, $service, $request->country_id);
            if ($resolved['snapped'] && ! empty($resolved['response'])) {
                $responseData = $resolved['response'];
            }
            $responseData['resolved_country_id'] = $resolved['country']->id ?? null;
            $responseData['snapped'] = $resolved['snapped'];
            if ($resolved['snapped'] && $resolved['country']) {
                $responseData['snapped_country_name'] = $resolved['country']->name;
            }

            return ApiResponseService::successResponse('Data Fetched Successfully', $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getOsmPlaceDetailsData error: '.$e->getMessage());

            return ApiResponseService::errorResponse();
        }
    }

    /**
     * Resolve which admin-added country a resolved place belongs to. If the
     * place's own country isn't one we support — or doesn't match the
     * caller's expected country_id, when given — fall back to the expected
     * country (or, absent one, the nearest admin-added country by
     * reference-point distance) and re-resolve the place there, so the
     * returned location/address stay consistent with the country we're
     * snapping to.
     */
    private function resolveCountryForPlace(array $responseData, $latitude, $longitude, GeoNamesMapService $service, $expectedCountryId = null): array
    {
        $isoCode = $responseData['result']['country_code'] ?? null;

        $matched = Country::matchByCode($isoCode);
        if ($matched && (! $expectedCountryId || (int) $matched->id === (int) $expectedCountryId)) {
            return ['country' => $matched, 'snapped' => false, 'response' => null];
        }

        $pointLat = $responseData['result']['geometry']['location']['lat'] ?? $latitude;
        $pointLng = $responseData['result']['geometry']['location']['lng'] ?? $longitude;
        if (! is_numeric($pointLat) || ! is_numeric($pointLng)) {
            return ['country' => null, 'snapped' => false, 'response' => null];
        }

        // If the caller has a country selected, snap directly to it instead of
        // the globally nearest admin-added country — but only if it has its
        // own reference point to snap to.
        $target = null;
        if ($expectedCountryId) {
            $expected = Country::find($expectedCountryId);
            if ($expected && $expected->latitude !== null && $expected->longitude !== null) {
                $target = $expected;
            }
        }
        if (! $target) {
            $target = Country::nearestTo((float) $pointLat, (float) $pointLng);
        }
        if (! $target) {
            return ['country' => null, 'snapped' => false, 'response' => null];
        }

        $snappedResponse = $service->detailsOrReverse(null, (float) $target->latitude, (float) $target->longitude);

        return ['country' => $target, 'snapped' => true, 'response' => $snappedResponse ?: null];
    }
}
