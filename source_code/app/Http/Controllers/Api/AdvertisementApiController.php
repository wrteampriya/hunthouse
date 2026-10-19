<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\PaymentTransaction;
use App\Models\Projects;
use App\Models\Property;
use App\Services\ApiResponseService;
use App\Services\HelperService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdvertisementApiController extends Controller
{
    public function get_advertisement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'customer_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $date = date('Y-m-d');

        $adv = Advertisement::select('id', 'image', 'category_id', 'property_id', 'type', 'customer_id', 'is_enable', 'status', 'role_context')->with('customer:id,name')->where('end_date', '>', $date);
        if (isset($request->customer_id)) {
            $adv->where(['customer_id' => $request->customer_id, 'role_context' => $request->user_active_role]);
        }
        $total = $adv->get()->count();
        $result = $adv->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        if (! $result->isEmpty()) {
            foreach ($adv as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('').config('global.IMG_PATH').config('global.ADVERTISEMENT_IMAGE_PATH').$row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function store_advertisement(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'feature_for' => 'required|in:property,project',
            'property_id' => 'nullable|required_if:feature_for,property',
            'project_id' => 'nullable|required_if:feature_for,project',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();
            $current_user = Auth::user()->id;

            // Determine role context from the property/project being advertised
            $addedAs = 'user';
            if ($request->feature_for == 'property') {
                $relatedProperty = Property::find($request->property_id);
                $addedAs = $relatedProperty->role_context ?? 'user';
            } else {
                $relatedProject = Projects::find($request->project_id);
                $addedAs = $relatedProject->role_context ?? 'user';
            }

            // Validate active role matches listing's role context
            $activeRole = $request->user_active_role;
            if ($activeRole !== $addedAs) {
                ApiResponseService::validationError('This listing was created in '.$addedAs.' mode. Please switch to '.$addedAs.' mode to feature it.');
            }

            $advertisementQuery = Advertisement::whereIn('status', [0, 1])->where('role_context', $request->user_active_role);
            if ($request->feature_for == 'property') {
                $packageData = HelperService::updatePackageLimit('property_feature', true);
                $checkAdvertisement = $advertisementQuery->clone()->where('property_id', $request->property_id)->count();
            } else {
                $packageData = HelperService::updatePackageLimit('project_feature', true);
                $checkAdvertisement = $advertisementQuery->clone()->where('project_id', $request->project_id)->count();
            }
            if (collect($packageData)->isEmpty()) {
                ApiResponseService::validationError('Package not found');
            }
            if (! empty($checkAdvertisement)) {
                ApiResponseService::validationError('Advertisement already exists');
            }
            $advertisementData = new Advertisement;
            $advertisementData->for = $request->feature_for;
            $advertisementData->start_date = Carbon::now();
            if (isset($request->end_date)) {
                $advertisementData->end_date = $request->end_date;
            } else {
                $advertisementData->end_date = Carbon::now()->addHours($packageData->duration);
            }
            $advertisementData->package_id = $packageData->id;
            $advertisementData->type = 'HomeScreen';
            if ($request->feature_for == 'property') {
                $advertisementData->property_id = $request->property_id;
            } else {
                $advertisementData->project_id = $request->project_id;
            }
            $advertisementData->customer_id = $current_user;
            $advertisementData->is_enable = false;

            // Check the auto approve and verified user status and make advertisement auto approved or pending and is enable true or false
            $autoApproveStatus = HelperService::getAutoApproveStatus($current_user, $request->user_active_role);
            if ($autoApproveStatus) {
                $advertisementData->status = 0;
                $advertisementData->is_enable = true;
            } else {
                $advertisementData->status = 1;
                $advertisementData->is_enable = false;
            }
            $advertisementData->save();

            DB::commit();
            ApiResponseService::successResponse('Advertisement added successfully');
        } catch (\Throwable $th) {
            DB::rollback();
            ApiResponseService::errorResponse();
        }
    }

    public function delete_advertisement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $adv = Advertisement::where('customer_id', Auth::user()->id)->where('role_context', $request->user_active_role)->find($request->id);
        if (! $adv) {
            return response()->json(['error' => true, 'message' => trans('Advertisement not found')]);
        }

        // Validate active role matches the related property/project's role_context
        $addedAs = 'user';
        if ($adv->property_id) {
            $addedAs = Property::where('id', $adv->property_id)->value('role_context') ?? 'user';
        } elseif ($adv->project_id) {
            $addedAs = Projects::where('id', $adv->project_id)->value('role_context') ?? 'user';
        }

        if ($request->user_active_role !== $addedAs) {
            return response()->json([
                'error' => true,
                'message' => trans('This advertisement belongs to a :role mode listing. Please switch to :role mode to delete it.', ['role' => $addedAs]),
            ]);
        }

        $adv->delete();

        return response()->json(['error' => false, 'message' => trans('Advertisement Deleted Successfully')]);
    }

    public function renew_listing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:property,project',
            'id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        try {
            $userId = Auth::user()->id;

            // 1. Find listing belonging to user
            if ($request->type == 'property') {
                $listing = Property::where('id', $request->id)->where('added_by', $userId)->first();
                $featureType = 'property_list';
            } else {
                $listing = Projects::where('id', $request->id)->where('added_by', $userId)->first();
                $featureType = 'project_list';
            }

            if (! $listing) {
                return ApiResponseService::errorResponse('Listing not found');
            }

            // Validate active role matches listing's role_context context
            $userActiveRole = $request->user_active_role;
            $listingAddedAs = $listing->role_context ?? 'user';
            if ($userActiveRole !== $listingAddedAs) {
                return ApiResponseService::errorResponse('This listing was created by '.$listingAddedAs.'. Please switch to '.$listingAddedAs.' mode to renew it.');
            }

            // 2. Check if actually expired
            if ($listing->expiry_date === null || Carbon::parse($listing->expiry_date)->isFuture()) {
                return ApiResponseService::errorResponse('Listing is not expired');
            }

            // 3. Consume package slot (same as creating new listing) with role context
            $addedAs = $listing->role_context ?? 'user';
            $limitResult = HelperService::updatePackageLimit($featureType, false, true);
            $isPayAsYouGo = ($limitResult === 'pay_as_you_go');

            // 4. Recalculate expiry date
            if ($isPayAsYouGo) {
                $listing->expiry_date = Carbon::now()->addDays(30);
            } else {
                $listing->expiry_date = HelperService::calculateExpirationDate($userId);
            }

            // 5. Reactivate
            $listing->status = 1;
            $listing->save();

            // 6. Link payment transaction to listing (pay-as-you-go only)
            if ($isPayAsYouGo && HelperService::$lastConsumedPaymentTransactionId) {
                $updateColumn = ($request->type == 'property') ? 'property_id' : 'project_id';
                PaymentTransaction::where('id', HelperService::$lastConsumedPaymentTransactionId)
                    ->update([$updateColumn => $listing->id]);
            }

            return ApiResponseService::successResponse('Listing Renewed Successfully');
        } catch (Exception $e) {
            return ApiResponseService::errorResponse('Something went wrong');
        }
    }
}
