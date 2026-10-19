<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CityImage;
use App\Models\Country;
use App\Models\Setting;
use App\Services\ApiResponseService;
use App\Services\GooglePlacesService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MapApiController extends Controller
{
    public function getMapPlacesListData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'input' => 'required|string',
                'country_id' => 'nullable|exists:countries,id',
            ], [
                'input.required' => trans('Input is required'),
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            $input = (string) $request->query('input', '');

            // When the caller has a country selected, restrict suggestions to
            // just that country instead of the default "all admin-added
            // countries" list.
            $regionCodes = null;
            if ($request->filled('country_id')) {
                $selectedCountry = Country::find($request->country_id);
                if ($selectedCountry && ! empty($selectedCountry->code)) {
                    $regionCodes = [strtoupper($selectedCountry->code)];
                }
            }

            $locale = GooglePlacesService::localeForCountry();

            $service = new GooglePlacesService;
            $responseData = $service->autocomplete($input, $locale, $regionCodes);

            return ApiResponseService::successResponse('Data Fetched Successfully', $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getPlacesForApp error: '.$e->getMessage());

            return ApiResponseService::errorResponse();
        }
    }

    public function getMapPlaceDetailsData(Request $request)
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
            $latitude = $request->has('latitude') ? $request->latitude : null;
            $longitude = $request->has('longitude') ? $request->longitude : null;
            $placeId = $request->has('place_id') ? $request->place_id : null;

            $locale = GooglePlacesService::localeForCountry();

            $service = new GooglePlacesService;
            $responseData = $service->detailsOrGeocode($placeId, $latitude, $longitude, $locale);

            $resolved = $this->resolveCountryForPlace($responseData, $latitude, $longitude, $service, $request->country_id);
            if ($resolved['snapped'] && ! empty($resolved['response'])) {
                $responseData = $resolved['response'];
            }
            $responseData['resolved_country_id'] = $resolved['country']->id ?? null;
            $responseData['snapped'] = $resolved['snapped'];
            if ($resolved['snapped'] && $resolved['country']) {
                $responseData['snapped_country_name'] = $resolved['country']->name;
            }

            ApiResponseService::successResponse('Data Fetched Successfully', $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getPlaceDetailsData error: '.$e->getMessage());
            ApiResponseService::errorResponse();
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
    private function resolveCountryForPlace(array $responseData, $latitude, $longitude, GooglePlacesService $service, $expectedCountryId = null): array
    {
        $isoCode = null;
        foreach (($responseData['addressComponents'] ?? []) as $component) {
            if (in_array('country', $component['types'] ?? [], true)) {
                $isoCode = $component['shortText'] ?? null;
                break;
            }
        }

        $matched = Country::matchByCode($isoCode);
        if ($matched && (! $expectedCountryId || (int) $matched->id === (int) $expectedCountryId)) {
            return ['country' => $matched, 'snapped' => false, 'response' => null];
        }

        $pointLat = $responseData['location']['latitude'] ?? $latitude;
        $pointLng = $responseData['location']['longitude'] ?? $longitude;
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

        $snappedResponse = $service->detailsOrGeocode(null, (float) $target->latitude, (float) $target->longitude);

        return ['country' => $target, 'snapped' => true, 'response' => $snappedResponse ?: null];
    }

    public function getCitiesData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'search' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $cityImageStyle = Setting::where('type', 'city_image_style')->first();
        $cityImageStyle = $cityImageStyle->data ?? 'style_1';
        $withImage = $cityImageStyle == 'style_1' ? true : false;
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $city_arr = [];
        $citiesQuery = CityImage::where('status', 1)->withCount(['property' => function ($query) use ($request) {
            $query->whereIn('propery_type', [0, 1])->onlyActive()
                ->when($request->filled('country_id'), function ($q) use ($request) {
                    return $q->where('country_id', $request->country_id);
                });
        }])->having('property_count', '>', 0);
        $totalData = $citiesQuery->clone()->count();
        $citiesData = $citiesQuery->clone()->orderByDesc('property_count')->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        foreach ($citiesData as $city) {
            if (! empty($city->getRawOriginal('image'))) {
                $url = $city->image;
                $relativePath = parse_url($url, PHP_URL_PATH);
                if (file_exists(public_path().$relativePath)) {
                    array_push($city_arr, ['City' => $city->city, 'Count' => $city->property_count, 'image' => $city->image]);

                    continue;
                }
            }
            $resultArray = $this->getUnsplashData($city);
            array_push($city_arr, $resultArray);
        }
        $response['error'] = false;
        $response['with_image'] = $withImage;
        $response['data'] = $city_arr;
        $response['total'] = $totalData;
        $response['message'] = trans('Data Fetched Successfully');

        return response()->json($response);
    }

    private function getUnsplashData($cityData)
    {
        $apiKey = env('UNSPLASH_API_KEY');
        $query = $cityData->city;
        $apiUrl = "https://api.unsplash.com/search/photos/?query=$query";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID '.$apiKey,
        ]);
        $unsplashResponse = curl_exec($ch);
        curl_close($ch);

        $unsplashData = json_decode($unsplashResponse, true);
        if (isset($unsplashData['results'])) {
            $results = $unsplashData['results'];
            $imageUrl = '';
            foreach ($results as $result) {
                $imageUrl = $result['urls']['regular'];
                break;
            }
            if ($imageUrl != '') {
                return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => $imageUrl];
            }
        }

        return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => ''];
    }
}
