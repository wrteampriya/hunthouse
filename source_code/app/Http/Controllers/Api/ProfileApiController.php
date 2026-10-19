<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\AgentVerification;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Usertokens;
use App\Models\VerifyCustomer;
use App\Services\ApiResponseService;
use App\Services\FileService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProfileApiController extends Controller
{
    public function getUserData()
    {
        try {
            // Get LoggedIn User Data from Toke
            $userData = Auth::user();
            $userData->mobile = $userData->getRawOriginal('mobile') ?? $userData->full_mobile;
            $userData->is_demo_user = $userData->is_demo_user;
            // $userData->is_agent = $userData->is_agent;
            // $userData->is_user_verified = $userData->is_user_verified; // Expose verified status
            // $userData->is_appointment_available = $userData->is_appointment_available;
            // $userData->become_agent_status = AgentVerification::where('customer_id', $userData->id)->where('form_type', 'become_agent')->first()?->status ?? "";
            // $userData->agent_verification_status = AgentVerification::where('customer_id', $userData->id)->where('form_type', 'verify_agent')->first()?->status ?? "";
            // $userData->user_verification_status = VerifyCustomer::where('user_id', $userData->id)->first()?->status ?? "";
            $data = new CustomerResource($userData, [
                'is_agent',
                'is_user_verified',
                'is_appointment_available',
                'become_agent_status',
                'agent_verification_status',
                'user_verification_status',
            ]);

            // add reaject reason for become agent, agent verification and user verification if exist
            $data['become_agent_reject_reason'] = AgentVerification::with('rejectReason')->where('customer_id', $userData->id)->where('form_type', 'become_agent')->first()?->rejectReason?->reason ?? '';
            $data['agent_verification_reject_reason'] = AgentVerification::with('rejectReason')->where('customer_id', $userData->id)->where('form_type', 'verify_agent')->first()?->rejectReason?->reason ?? '';
            $data['user_verification_reject_reason'] = VerifyCustomer::with('rejectReason')->where('user_id', $userData->id)->first()?->rejectReason?->reason ?? '';

            $userData->load('agent_profile');
            $userData->agent_profile = $userData->resolved_agent_profile;
            // Check the User Data is not Empty
            if (collect($userData)->isNotEmpty()) {
                $response['error'] = false;
                $response['data'] = $data;
            } else {
                $response['error'] = false;
                $response['message'] = trans('No Data Found');
                $response['data'] = [];
            }

            return response()->json($response);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
                'details' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    public function update_profile(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'country_code' => 'required',
                    'mobile' => 'required',
                    'name' => 'required',
                    'email' => 'required|email',
                ],
                [
                    'country_code.required' => trans('Country code is required'),
                    'mobile.required' => trans('Mobile is required'),
                    'name.required' => trans('Name is required'),
                    'email.required' => trans('Email is required'),
                    'email.email' => trans('Email is invalid'),
                ]
            );
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();
            $currentUser = Auth::user();
            $customer = Customer::find($currentUser->id);

            if (! empty($customer)) {

                // update the Data passed in payload
                $fieldsToUpdate = $request->only([
                    'name',
                    'email',
                    'mobile',
                    'country_code',
                    'fcm_id',
                    'address',
                    'notification',
                    'about_me',
                    'facebook_id',
                    'twiiter_id',
                    'instagram_id',
                    'youtube_id',
                    'latitude',
                    'longitude',
                    'city',
                    'state',
                    'country',
                ]);

                // Remove spaces from mobile number
                if (isset($fieldsToUpdate['mobile']) && ! empty($fieldsToUpdate['mobile'])) {
                    $fieldsToUpdate['mobile'] = str_replace(' ', '', $fieldsToUpdate['mobile']);
                }

                if ($request->has('fcm_id') && ! empty($request->fcm_id)) {
                    Usertokens::updateOrCreate(
                        ['fcm_id' => $request->fcm_id],
                        ['customer_id' => $customer->id]
                    );
                }

                // Update Profile
                if ($request->hasFile('profile')) {
                    $rawImage = $customer->getRawOriginal('profile');
                    $customer->profile = FileService::compressAndReplace($request->file('profile'), config('global.USER_IMG_PATH'), $rawImage);
                }
                $customer->update($fieldsToUpdate);
                $customerData = $customer->fresh();
                $data = new CustomerResource($customerData, [
                    'is_agent',
                    'is_user_verified',
                    'is_appointment_available',
                    'become_agent_status',
                    'agent_verification_status',
                    'user_verification_status',
                ]);

                DB::commit();

                return response()->json(['error' => false, 'data' => $data]);
            } else {
                return response()->json(['error' => false, 'message' => trans('No Data Found'), 'data' => []]);
            }
        } catch (Exception $e) {
            DB::rollback();

            return response()->json(['error' => true, 'message' => trans('Something Went Wrong')], 500);
        }
    }

    public function delete_user(Request $request)
    {
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $customer = Customer::find($loggedInUserId);
            if (collect($customer)->isNotEmpty()) {
                $customer->delete();
            }
            DB::commit();
            $response['error'] = false;
            $response['message'] = trans('Data Deleted Successfully');

            return response()->json($response);
        } catch (Exception $e) {
            DB::rollBack();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }
    }

    public function updateLanguage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'language_code' => 'required',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            if ($request->language_code == 'en') {
                $request->language_code = 'en-new';
            }
            $language = Language::where(['code' => $request->language_code, 'status' => 1])->count();
            if (! $language) {
                ApiResponseService::errorResponse(trans('Language not found'));
            }
            $languageCode = $request->language_code;
            if ($languageCode == 'en') {
                $languageCode = 'en-new';
            }
            $loggedInUser = Auth::user();
            Customer::where('id', $loggedInUser->id)->update(['default_language' => $languageCode]);
            ApiResponseService::successResponse('Language Updated Successfully');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
}
