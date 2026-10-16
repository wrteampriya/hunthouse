<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserInterest;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\HeaderParameter;

class PersonalisationApiController extends Controller
{
    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getUserPersonalisedInterest(Request $request)
    {
        try {
            $loggedInUserId = Auth::user()->id;
            $data = [];

            $userInterest = UserInterest::where('user_id', $loggedInUserId)->first();
            if (collect($userInterest)->isNotEmpty()) {
                $categoriesIds = ! empty($userInterest->category_ids) ? explode(',', $userInterest->category_ids) : '';
                $priceRange = $userInterest->property_type != null ? explode(',', $userInterest->price_range) : '';
                $propertyType = $userInterest->property_type == 0 || $userInterest->property_type == 1 ? explode(',', $userInterest->property_type) : '';
                $outdoorFacilitiesIds = ! empty($userInterest->outdoor_facilitiy_ids) ? explode(',', $userInterest->outdoor_facilitiy_ids) : '';
                $countryIds = ! empty($userInterest->country_ids) ? explode(',', $userInterest->country_ids) : '';
                $data = [
                    'user_id' => $loggedInUserId,
                    'category_ids' => $categoriesIds,
                    'price_range' => $priceRange,
                    'property_type' => $propertyType,
                    'outdoor_facilitiy_ids' => $outdoorFacilitiesIds,
                    'country_ids' => $countryIds,
                ];
            }
            $response = [
                'error' => false,
                'data' => $data,
                'message' => trans('Data Fetched Successfully'),
            ];

            return response()->json($response);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function storeUserPersonalisedInterest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_ids' => 'nullable|string',
            'outdoor_facilitiy_ids' => 'nullable|string',
            'price_range' => 'nullable|string',
            'property_type' => 'nullable|string',
            'country_ids' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;

            $userInterest = UserInterest::where('user_id', $loggedInUserId)->first();

            if (collect($userInterest)->isNotEmpty()) {
                $response['error'] = false;
                $response['message'] = trans('Data Updated Successfully');
            } else {
                $userInterest = new UserInterest;
                $response['error'] = false;
                $response['message'] = trans('Data Submitted Successfully');
            }

            $userInterest->user_id = $loggedInUserId;
            $userInterest->category_ids = (isset($request->category_ids) && ! empty($request->category_ids)) ? $request->category_ids : '';
            $userInterest->outdoor_facilitiy_ids = (isset($request->outdoor_facilitiy_ids) && ! empty($request->outdoor_facilitiy_ids)) ? $request->outdoor_facilitiy_ids : null;
            $userInterest->price_range = (isset($request->price_range) && ! empty($request->price_range)) ? $request->price_range : '';
            $userInterest->property_type = isset($request->property_type) && ($request->property_type == 0 || $request->property_type == 1) ? $request->property_type : '0,1';
            $userInterest->country_ids = (isset($request->country_ids) && ! empty($request->country_ids)) ? $request->country_ids : null;
            $userInterest->save();

            DB::commit();

            $categoriesIds = ! empty($userInterest->category_ids) ? explode(',', $userInterest->category_ids) : '';
            $priceRange = ! empty($userInterest->price_range) ? explode(',', $userInterest->price_range) : '';
            $propertyType = explode(',', $userInterest->property_type);
            $outdoorFacilitiesIds = ! empty($userInterest->outdoor_facilitiy_ids) ? explode(',', $userInterest->outdoor_facilitiy_ids) : '';
            $countryIds = ! empty($userInterest->country_ids) ? explode(',', $userInterest->country_ids) : '';

            $data = [
                'user_id' => $userInterest->user_id,
                'category_ids' => $categoriesIds,
                'price_range' => $priceRange,
                'property_type' => $propertyType,
                'outdoor_facilitiy_ids' => $outdoorFacilitiesIds,
                'country_ids' => $countryIds,
            ];
            $response['data'] = $data;
            $response['message'] = trans('Data Fetched Successfully');

            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function deleteUserPersonalisedInterest(Request $request)
    {
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            UserInterest::where('user_id', $loggedInUserId)->delete();
            DB::commit();
            $response = [
                'error' => false,
                'message' => trans('Data Deleted Successfully'),
            ];

            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }
    }
}
