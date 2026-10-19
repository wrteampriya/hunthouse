<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CityImage;
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
            ], [
                'input.required' => trans('Input is required'),
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            $input = (string) $request->query('input', '');
            $service = new GooglePlacesService;
            $responseData = $service->autocomplete($input);

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

            $service = new GooglePlacesService;
            $responseData = $service->detailsOrGeocode($placeId, $latitude, $longitude);
            ApiResponseService::successResponse('Data Fetched Successfully', $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getPlaceDetailsData error: '.$e->getMessage());
            ApiResponseService::errorResponse();
        }
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
        $citiesQuery = CityImage::where('status', 1)->withCount(['property' => function ($query) {
            $query->whereIn('propery_type', [0, 1])->onlyActive();
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
