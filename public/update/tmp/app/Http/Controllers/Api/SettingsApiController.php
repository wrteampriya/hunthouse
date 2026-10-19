<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\HomepageSection;
use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use App\Models\VerifyCustomer;
use App\Services\ApiResponseService;
use App\Services\HelperService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\HeaderParameter;

class SettingsApiController extends Controller
{
    public function get_app_settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $result = Setting::select('type', 'data')->whereIn('type', ['app_home_screen', 'placeholder_logo', 'light_tertiary', 'light_secondary', 'light_primary', 'dark_tertiary', 'dark_secondary', 'dark_primary'])->get();

        $tempRow = [];

        if (($request->user_id) != '') {
            update_subscription($request->user_id);

            $customer_data = Customer::find($request->user_id);
            if ($customer_data) {
                if ($customer_data->isActive == 0) {

                    $tempRow['is_active'] = false;
                } else {
                    $tempRow['is_active'] = true;
                }
            }
        }

        foreach ($result as $row) {
            $tempRow[$row->type] = $row->data;

            if ($row->type == 'app_home_screen' || $row->type == 'placeholder_logo') {

                $tempRow[$row->type] = url('assets/images/logo/'.$row->data);
            }
        }

        $response['error'] = false;
        $response['data'] = $tempRow;

        return response()->json($response);
    }

    public function get_languages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language_code' => 'required',
        ]);

        if (! $validator->fails()) {
            $language = Language::where('code', $request->language_code)->first();

            if ($language) {
                if ($request->web_language_file) {
                    $json_file_path = public_path('web_languages/'.$request->language_code.'.json');
                } else {
                    $json_file_path = public_path('languages/'.$request->language_code.'.json');
                }

                if (file_exists($json_file_path)) {
                    $json_string = file_get_contents($json_file_path);
                    $json_data = json_decode($json_string);

                    if ($json_data !== null) {
                        $language->file_name = $json_data;
                        $response['error'] = false;
                        $response['message'] = trans('Data Fetched Successfully');
                        $response['data'] = $language;
                    } else {
                        $response['error'] = true;
                        $response['message'] = trans('Invalid JSON format in the language file');
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = trans('Language file not found');
                }
            } else {
                $response['error'] = true;
                $response['message'] = trans('Language not found');
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }

        return response()->json($response);
    }

    public function getPrivacyPolicy()
    {
        try {
            $privacyPolicyData = Setting::where('type', 'privacy_policy')->with('translations')->first();
            $arrayData = null;
            if (! empty($privacyPolicyData)) {
                $translatedData = $privacyPolicyData->translated_data;
                $arrayData = $privacyPolicyData->toArray();
                $arrayData['data'] = $translatedData;
                unset($arrayData['translations']);
                unset($arrayData['created_at']);
            }
            ApiResponseService::successResponse('Data Fetched Successfully', ! empty($arrayData) ? $arrayData : '');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getTermsAndConditions()
    {
        try {
            $termsAndConditionsData = Setting::where('type', 'terms_conditions')->with('translations')->first();
            $arrayData = null;
            if (! empty($termsAndConditionsData)) {
                $translatedData = $termsAndConditionsData->translated_data;
                $arrayData = $termsAndConditionsData->toArray();
                $arrayData['data'] = $translatedData;
                unset($arrayData['translations']);
                unset($arrayData['created_at']);
            }
            ApiResponseService::successResponse('Data Fetched Successfully', ! empty($arrayData) ? $arrayData : '');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getWebSettings(Request $request)
    {
        try {
            // Types for web requirement only
            $types = ['company_name', 'currency_symbol', 'default_language', 'number_with_suffix', 'web_maintenance_mode', 'company_tel', 'company_tel2', 'system_version', 'web_favicon', 'web_logo', 'web_footer_logo', 'web_placeholder_logo', 'company_email', 'latitude', 'longitude', 'company_address', 'system_color', 'iframe_link', 'facebook_id', 'instagram_id', 'twitter_id', 'youtube_id', 'linkedin_id', 'playstore_id', 'sell_background', 'appstore_id', 'category_background', 'web_maintenance_mod', 'seo_settings', 'company_tel1', 'sell_web_color', 'sell_web_background_color', 'rent_web_color', 'rent_web_background_color', 'number_with_otp_login', 'social_login', 'distance_option', 'otp_service_provider','auto_approve', 'verification_required_for_user', 'agent_auto_approve', 'verification_required_for_agent', 'allow_cookies', 'currency_code', 'bank_details', 'schema_for_deeplink', 'min_radius_range', 'max_radius_range', 'homepage_location_alert_status', 'email_password_login', 'gemini_ai_enabled', 'show_direct_video_upload', 'show_whatsapp_button','show_premium_toggle', 'show_exact_location', 'map_service_provider', 'story_max_duration', 'story_video_max_size'];

            // Query the Types to Settings Table to get its data
            $result = Setting::whereIn('type', $types)->with('translations')->select('id', 'type', 'data')->get();

            // Check the result data is not empty
            if (collect($result)->isNotEmpty()) {
                $settingsData = [];

                $settingsData['verification_required_for_user'] = false;
                $settingsData['verification_required_for_agent'] = false;
                $settingsData['auto_approve'] = false;
                $settingsData['agent_auto_approve'] = false;

                $settingsData['verification_required_for_user'] = false;
                $settingsData['verification_required_for_agent'] = false;
                $settingsData['auto_approve'] = false;
                $settingsData['agent_auto_approve'] = false;

                // Loop on the result data
                foreach ($result as $row) {
                    // Change data according to conditions
                    if ($row->type == 'company_logo') {
                        // Add logo image with its url
                        $settingsData[$row->type] = url('/assets/images/logo/logo.png');
                    } elseif ($row->type == 'seo_settings') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'allow_cookies') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'verification_required_for_user') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'show_whatsapp_button') {
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'verification_required_for_agent') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'auto_approve') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'agent_auto_approve') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'web_favicon' || $row->type == 'web_logo' || $row->type == 'web_placeholder_logo' || $row->type == 'web_footer_logo') {
                        // Add Full URL to the specified type
                        $settingsData[$row->type] = url('assets/images/logo/'.$row->data);
                    } elseif ($row->type == 'currency_code') {
                        // Change Value to Bool
                        $settingsData['selected_currency_data'] = HelperService::getCurrencyData($row->data);
                    } elseif ($row->type == 'bank_details') {
                        // Change Value to Bool
                        $bankDetails = json_decode($row->data, true);
                        $settingsData['bank_details'] = $this->processBankDetails($bankDetails);
                    } elseif ($row->type == 'min_radius_range') {
                        // DB type stays min_radius_range; expose it as min_radius
                        $settingsData['min_radius'] = $row->translated_data;
                    } elseif ($row->type == 'max_radius_range') {
                        // DB type stays max_radius_range; expose it as max_radius
                        $settingsData['max_radius'] = $row->translated_data;
                    } elseif ($row->type == 'default_language') {
                        // Always the admin-configured global default — never the
                        // customer's own active language (change-language/change-country
                        // must not affect this).
                        $languageCode = $row->data;
                        $settingsData[$row->type] = $languageCode;

                        // Add Default language's name
                        $languageData = Language::where('code', $languageCode)->first();
                        if (collect($languageData)->isNotEmpty()) {
                            $settingsData['default_language_name'] = $languageData->name;
                            $settingsData['default_language_rtl'] = $languageData->rtl == 1 ? 1 : 0;
                        } else {
                            $settingsData['default_language_name'] = '';
                            $settingsData['default_language_rtl'] = 0;
                        }
                    } elseif ($row->type == 'gemini_ai_enabled') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'story_max_duration' || $row->type == 'story_video_max_size') {
                        $settingsData[$row->type] = (int) $row->data;
                    } else {
                        // add the data as it is in array
                        $settingsData[$row->type] = $row->translated_data;
                    }
                }

                $user_data = User::find(1);
                $settingsData['admin_name'] = $user_data->name;
                $settingsData['admin_image'] = url('/assets/images/faces/2.jpg');
                $settingsData['demo_mode'] = env('DEMO_MODE');
                $settingsData['img_placeholder'] = url('/assets/images/placeholder.svg');

                // Homepage Section Data
                // $sections = HomepageSection::where('is_active', 1)
                //     ->orderBy('sort_order')
                //     ->with('translations')
                //     ->get()
                //     ->map(function ($section) {
                //         return [
                //             'id' => $section->id,
                //             'type' => $section->section_type,
                //             'title' => $section->title,
                //             'translated_title' => $section->translated_title,
                //             'sort_order' => $section->sort_order,
                //             'is_active' => $section->is_active,
                //         ];
                //     });
                // $settingsData['homepage_sections'] = $sections;

                // if Token is passed of current user.
                if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                    $loggedInUserId = Auth::guard('sanctum')->user()->id;
                    update_subscription($loggedInUserId);

                    $checkVerifiedStatus = VerifyCustomer::where('user_id', $loggedInUserId)->first();
                    if (! empty($checkVerifiedStatus)) {
                        $settingsData['verification_status'] = $checkVerifiedStatus->status;
                    } else {
                        $settingsData['verification_status'] = 'initial';
                    }

                    $customerDataQuery = Customer::select('id', 'subscription', 'is_premium', 'isActive');
                    $customerData = $customerDataQuery->clone()->find($loggedInUserId);

                    // Check Active of current User
                    if (collect($customerData)->isNotEmpty()) {
                        $settingsData['is_active'] = $customerData->isActive == 1 ? true : false;
                    } else {
                        $settingsData['is_active'] = false;
                    }
                }

                // Check the min_price and max_price
                $settingsData['min_price'] = DB::table('propertys')->selectRaw('MIN(price) as min_price')->value('min_price');
                $settingsData['max_price'] = DB::table('propertys')->selectRaw('MAX(price) as max_price')->value('max_price');

                // Check the features available
                // $settingsData['features_available'] = [
                //     'premium_properties' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true, true, $request->user_active_role)['feature_available'],
                //     'premium_projects' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'), true, true, $request->user_active_role)['feature_available'],
                // ];

                // Get Languages Data
                $specificSelect = ['id', 'code', 'name'];
                $language = HelperService::getActiveLanguages($specificSelect, true);
                $settingsData['languages'] = $language;

                $response['error'] = false;
                $response['message'] = trans('Data Fetched Successfully');
                $response['data'] = $settingsData;
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
            ];

            return response()->json($response, 500);
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAppSettings(Request $request)
    {
        try {
            $types = ['company_name', 'currency_symbol', 'ios_version', 'default_language', 'force_update', 'android_version', 'number_with_suffix', 'maintenance_mode', 'company_tel1', 'company_tel2', 'company_email', 'company_address','playstore_id', 'sell_background', 'appstore_id', 'show_admob_ads', 'android_banner_ad_id', 'ios_banner_ad_id', 'android_interstitial_ad_id', 'ios_interstitial_ad_id', 'android_native_ad_id', 'ios_native_ad_id', 'demo_mode', 'min_price', 'max_price', 'number_with_otp_login', 'social_login', 'distance_option', 'otp_service_provider', 'app_home_screen', 'placeholder_logo', 'dark_mode_logo', 'light_tertiary', 'light_secondary', 'light_primary', 'dark_tertiary', 'dark_secondary', 'dark_primary', 'auto_approve', 'verification_required_for_user', 'currency_code', 'bank_details', 'schema_for_deeplink', 'min_radius_range', 'max_radius_range', 'latitude', 'longitude', 'homepage_location_alert_status', 'email_password_login', 'app_login_background', 'gemini_ai_enabled', 'show_direct_video_upload', 'show_whatsapp_button', 'show_premium_toggle', 'show_exact_location', 'agent_auto_approve', 'verification_required_for_agent', 'map_service_provider', 'story_max_duration', 'story_video_max_size'];

            // Query the Types to Settings Table to get its data
            $result = Setting::whereIn('type', $types)->with('translations')->select('id', 'type', 'data')->get();

            // Check the result data is not empty
            if (collect($result)->isNotEmpty()) {
                $settingsData = [];

                $settingsData['verification_required_for_user'] = false;
                $settingsData['verification_required_for_agent'] = false;
                $settingsData['auto_approve'] = false;
                $settingsData['agent_auto_approve'] = false;

                $settingsData['verification_required_for_user'] = false;
                $settingsData['verification_required_for_agent'] = false;
                $settingsData['auto_approve'] = false;
                $settingsData['agent_auto_approve'] = false;

                // Loop on the result data
                foreach ($result as $row) {
                    if ($row->type == 'default_language') {
                        // Always the admin-configured global default — never the
                        // customer's own active language (change-language/change-country
                        // must not affect this).
                        $languageCode = $row->data;
                        $settingsData[$row->type] = $languageCode;

                        // Add Default language's name
                        $languageData = Language::where('code', $languageCode)->first();
                        if (collect($languageData)->isNotEmpty()) {
                            $settingsData['default_language_name'] = $languageData->name;
                            $settingsData['default_language_rtl'] = $languageData->rtl == 1 ? 1 : 0;
                        } else {
                            $settingsData['default_language_name'] = '';
                            $settingsData['default_language_rtl'] = 0;
                        }
                    } elseif ($row->type == 'app_home_screen' || $row->type == 'placeholder_logo' || $row->type == 'dark_mode_logo' || $row->type == 'app_login_background') {
                        $settingsData[$row->type] = url('assets/images/logo/'.$row->data);
                    } elseif ($row->type == 'verification_required_for_user') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'verification_required_for_agent') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'auto_approve') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'agent_auto_approve') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'currency_code') {
                        // Change Value to Bool
                        $settingsData['selected_currency_data'] = HelperService::getCurrencyData($row->data);
                    } elseif ($row->type == 'bank_details') {
                        // Change Value to Bool
                        $bankDetails = json_decode($row->data, true);
                        $settingsData['bank_details'] = $this->processBankDetails($bankDetails);
                    } elseif ($row->type == 'gemini_ai_enabled') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'show_direct_video_upload') {
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'show_whatsapp_button') {
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'show_premium_toggle') {
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'homepage_location_alert_status') {
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'show_exact_location') {
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } elseif ($row->type == 'story_max_duration' || $row->type == 'story_video_max_size') {
                        $settingsData[$row->type] = (int) $row->data;
                    } elseif ($row->type == 'min_radius_range') {
                        // DB type stays min_radius_range; expose it as min_radius
                        $settingsData['min_radius'] = $row->translated_data;
                    } elseif ($row->type == 'max_radius_range') {
                        // DB type stays max_radius_range; expose it as max_radius
                        $settingsData['max_radius'] = $row->translated_data;
                    } else {
                        // add the data as it is in array
                        $settingsData[$row->type] = $row->translated_data;
                    }
                }

                $settingsData['demo_mode'] = env('DEMO_MODE');
                // if Token is passed of current user.
                if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                    $loggedInUserId = Auth::guard('sanctum')->user()->id;
                    update_subscription($loggedInUserId);

                    $checkVerifiedStatus = VerifyCustomer::where('user_id', $loggedInUserId)->first();
                    if (! empty($checkVerifiedStatus)) {
                        $settingsData['verification_status'] = $checkVerifiedStatus->status;
                    } else {
                        $settingsData['verification_status'] = 'initial';
                    }

                    $customerDataQuery = Customer::select('id', 'subscription', 'is_premium', 'isActive');
                    $customerData = $customerDataQuery->clone()->find($loggedInUserId);

                    // Check Active of current User
                    if (collect($customerData)->isNotEmpty()) {
                        $settingsData['is_active'] = $customerData->isActive == 1 ? true : false;
                    } else {
                        $settingsData['is_active'] = false;
                    }
                }

                // Check the min_price and max_price
                $settingsData['min_price'] = DB::table('propertys')->selectRaw('MIN(price) as min_price')->value('min_price');
                $settingsData['max_price'] = DB::table('propertys')->selectRaw('MAX(price) as max_price')->value('max_price');

                // Homepage Section Data
                // $sections = HomepageSection::where('is_active', 1)
                //     ->orderBy('sort_order')
                //     ->with('translations')
                //     ->get()
                //     ->map(function ($section) {
                //         return [
                //             'id' => $section->id,
                //             'type' => $section->section_type,
                //             'title' => $section->title,
                //             'translated_title' => $section->translated_title,
                //             'sort_order' => $section->sort_order,
                //             'is_active' => $section->is_active,
                //         ];
                //     });
                // $settingsData['homepage_sections'] = $sections;

                // Check the features available
                // $settingsData['features_available'] = [
                //     'premium_properties' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true, true, $request->user_active_role)['feature_available'],
                //     'premium_projects' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'), true, true, $request->user_active_role)['feature_available'],
                // ];

                // Get Languages Data
                $specificSelect = ['id', 'code', 'name'];
                $language = HelperService::getActiveLanguages($specificSelect, true);
                $settingsData['languages'] = $language;

                $response['error'] = false;
                $response['message'] = trans('Data Fetched Successfully');
                $response['data'] = $settingsData;
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
                'data' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    public function getSystemSettings(Request $request)
    {
        try {
            $types = ['company_name', 'currency_symbol', 'ios_version', 'default_language', 'force_update', 'android_version', 'number_with_suffix', 'maintenance_mode', 'company_tel1', 'company_tel2', 'company_email', 'company_address', 'place_api_key', 'map_service_provider', 'geonames_username','playstore_id', 'sell_background', 'appstore_id', 'show_admob_ads', 'android_banner_ad_id', 'ios_banner_ad_id', 'android_interstitial_ad_id', 'ios_interstitial_ad_id', 'android_native_ad_id', 'ios_native_ad_id', 'demo_mode', 'min_price', 'max_price', 'number_with_otp_login', 'social_login', 'distance_option', 'otp_service_provider', 'app_home_screen', 'placeholder_logo', 'dark_mode_logo', 'light_tertiary', 'light_secondary', 'light_primary', 'dark_tertiary', 'dark_secondary', 'dark_primary', 'auto_approve', 'verification_required_for_user', 'agent_auto_approve', 'verification_required_for_agent', 'currency_code', 'bank_details', 'schema_for_deeplink', 'min_radius_range', 'max_radius_range', 'latitude', 'longitude', 'homepage_location_alert_status', 'email_password_login', 'app_login_background', 'gemini_ai_enabled', 'show_direct_video_upload', 'show_premium_toggle', 'show_exact_location', 'story_max_duration', 'story_video_max_size'];

            $data = Setting::whereIn('type', $types)->get();

            $json_data = [];
            $json_data['verification_required_for_user'] = false;
            $json_data['verification_required_for_agent'] = false;
            $json_data['auto_approve'] = false;
            $json_data['agent_auto_approve'] = false;

            foreach ($data as $row) {
                if ($row->type == 'placeholder_logo' || $row->type == 'dark_mode_logo' || $row->type == 'app_login_background') {
                    if ($row->data != '') {
                        $json_data[$row->type] = $row->data;
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'social_login') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'number_with_otp_login') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'email_password_login') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'verification_required_for_user') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'verification_required_for_agent') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'auto_approve') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'agent_auto_approve') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'gemini_ai_enabled') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'show_direct_video_upload') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'show_whatsapp_button') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'show_premium_toggle') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'homepage_location_alert_status') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'show_exact_location') {
                    if ($row->data != '') {
                        $json_data[$row->type] = filter_var($row->data, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $json_data[$row->type] = '';
                    }
                } elseif ($row->type == 'story_max_duration' || $row->type == 'story_video_max_size') {
                    $json_data[$row->type] = (int) $row->data;
                } else {
                    $json_data[$row->type] = $row->data;
                }
            }

            $languages = Language::all();
            $language_data = [];

            foreach ($languages as $row) {
                $tempRow['id'] = $row->id;
                $tempRow['name'] = $row->name;
                $tempRow['code'] = $row->code;
                $tempRow['rtl'] = filter_var($row->rtl, FILTER_VALIDATE_BOOLEAN);
                $tempRow['image'] = ($row->image != '') ? $row->image : '';
                $tempRow['image_url'] = ($row->image != '') ? url('assets/images/logo/'.$row->image) : '';
                $tempRow['file_name'] = ($row->file_name != '') ? url('languages/'.$row->file_name) : '';
                $language_data[] = $tempRow;
            }

            $json_data['languages'] = $language_data;

            $demo_mode = config('global.DEMO_MODE');
            $json_data['demo_mode'] = filter_var($demo_mode, FILTER_VALIDATE_BOOLEAN);

            $terms_conditions = system_setting('terms_conditions');
            $privacy_policy = system_setting('privacy_policy');

            $json_data['terms_conditions'] = ! empty($terms_conditions) ? url('/terms-and-condition') : '';
            $json_data['privacy_policy'] = ! empty($privacy_policy) ? url('/privacy-policy') : '';
            $lang_file_path = public_path('json/languages.json');
            if (file_exists($lang_file_path)) {
                $file = file_get_contents($lang_file_path);
                if (! empty($file)) {
                    $lang_data = json_decode($file, true);
                } else {
                    $lang_data = (object) [];
                }
            } else {
                $lang_data = (object) [];
            }

            ApiResponseService::successResponse(trans('System Settings Fetched Successfully'), $json_data, ['languages' => $lang_data]);
        } catch (Exception $e) {

            ApiResponseService::errorResponse();
        }
    }

    public function getLanguagesData()
    {
        try {
            $languageData = Language::select('id', 'code', 'name')->get();
            if (collect($languageData)->isNotEmpty()) {
                $response['error'] = false;
                $response['message'] = trans('Data Fetched Successfully');
                $response['data'] = $languageData;
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
            ];

            return response()->json($response, 500);
        }
    }

    public function getCountries(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => 'nullable|exists:countries,id']);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        try {
            $countries = Country::where('status', 1)
                ->when($request->id, function ($query) use ($request) {
                    $query->where('id', $request->id);
                })
                ->select('id', 'name', 'code', 'default_language_id', 'currency_id', 'latitude', 'longitude')
                ->get()
                ->map(function ($country) {
                    return $this->formatCountryData($country);
                });

            if (collect($countries)->isNotEmpty()) {
                $response['error'] = false;
                $response['message'] = trans('Data Fetched Successfully');
                $response['data'] = $countries;
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
            ];

            return response()->json($response, 500);
        }
    }

    private function formatCountryData(Country $country): array
    {
        $language = $country->resolvedLanguage();
        $currency = $country->resolvedCurrency();

        return [
            'id' => $country->id,
            'name' => $country->name,
            'code' => $country->code,
            'latitude' => $country->latitude,
            'longitude' => $country->longitude,
            'language_id' => $language->id ?? null,
            'language_code' => $language->code ?? null,
            'language_name' => $language->name ?? null,
            'currency_id' => $currency->id ?? null,
            'currency_code' => $currency->code ?? null,
            'currency_symbol' => $currency->symbol ?? null,
        ];
    }

    public function getCurrencies()
    {
        try {
            $currencies = Currency::where('status', 1)->select('id', 'name', 'code', 'symbol')->get();

            if (collect($currencies)->isNotEmpty()) {
                $response['error'] = false;
                $response['message'] = trans('Data Fetched Successfully');
                $response['data'] = $currencies;
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
            ];

            return response()->json($response, 500);
        }
    }

    public function getAboutUs()
    {
        try {
            $aboutUsData = Setting::where('type', 'about_us')->with('translations')->first();
            $arrayData = null;
            if (! empty($aboutUsData)) {
                $translatedData = $aboutUsData->translated_data;
                $arrayData = $aboutUsData->toArray();
                $arrayData['data'] = $translatedData;
                unset($arrayData['translations']);
                unset($arrayData['created_at']);
            }
            ApiResponseService::successResponse('Data Fetched Successfully', ! empty($arrayData) ? $arrayData : '');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function deepLink(Request $request)
    {
        try {
            $data = HelperService::getMultipleSettingData(['company_name', 'playstore_id', 'appstore_id']);
            $appName = $data['company_name'] ?? 'ebroker';
            $customerPlayStoreUrl = $data['playstore_id'] ?? 'https://play.google.com/store/apps/details?id=com.ebroker.ebroker';
            $customerAppStoreUrl = $data['appstore_id'] ?? 'https://apps.apple.com/app/id1564818806';

            return view('settings.deep-link', compact('appName', 'customerPlayStoreUrl', 'customerAppStoreUrl'));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Process Bank Details to add Translated Title based on the language of the user
    private function processBankDetails($bankDetails)
    {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();
        $languageId = cache()->remember("language_id_{$languageCode}", 3600, function () use ($languageCode) {
            return Language::where('code', $languageCode)->value('id');
        });
        foreach ($bankDetails as $key => $bankDetail) {
            if (isset($bankDetail['translations']) && ! empty($bankDetail['translations'])) {
                foreach ($bankDetail['translations'] as $translation) {
                    if (! empty($languageId) && ($translation['language_id'] == $languageId)) {
                        $bankDetails[$key]['translated_title'] = $translation['title'];
                    } else {
                        $bankDetails[$key]['translated_title'] = $bankDetail['title'];
                    }
                }
            } else {
                $bankDetails[$key]['translated_title'] = $bankDetail['title'];
            }
        }

        return $bankDetails;
    }
}
