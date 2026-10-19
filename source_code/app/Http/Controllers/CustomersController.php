<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InterestedUser;
use App\Models\Usertokens;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class CustomersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'customer')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('customer.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id = null)
    {
        // If called from status toggle route (customerstatus), keep old behavior
        if ($request->has('status') && $request->has('id') && is_null($id)) {
            if (! has_permissions('update', 'customer')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }
            Customer::where('id', $request->id)->update(['isActive' => $request->status]);

            $customerName = null;
            // Send mail for user status
            try {
                $customerData = Customer::where('id', $request->id)->select('id', 'name', 'email', 'isActive')->first();
                $customerName = $customerData->name;
                if ($customerData->email) {
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes('user_status');

                    // Email Template
                    $propertyFeatureStatusTemplateData = system_setting($emailTypeData['type']);
                    $appName = env('APP_NAME') ?? 'eBroker';
                    $variables = [
                        'app_name' => $appName,
                        'user_name' => $customerData->name,
                        'status' => $customerData->isActive == 1 ? 'Activated' : 'Deactivated',
                        'email' => $customerData->email,
                    ];
                    if (empty($propertyFeatureStatusTemplateData)) {
                        $propertyFeatureStatusTemplateData = 'Your Property :- '.$variables['propertyName']."'s feature status ".$variables['status'];
                    }
                    $propertyFeatureStatusTemplate = HelperService::replaceEmailVariables($propertyFeatureStatusTemplateData, $variables);

                    $data = [
                        'email_template' => $propertyFeatureStatusTemplate,
                        'email' => $customerData->email,
                        'title' => $emailTypeData['title'],
                    ];
                    HelperService::sendMail($data);
                }
            } catch (Exception $e) {
                Log::error('Something Went Wrong in Customer Status Update Mail Sending');
            }

            /** Notification */
            $fcm_ids = [];

            $customer_id = Customer::where(['id' => $request->id, 'notification' => 1])->count();
            if ($customer_id) {
                $user_token = Usertokens::where('customer_id', $request->id)->pluck('fcm_id')->toArray();
                $fcm_ids[] = $user_token;
            }

            $msg = '';
            if (! empty($fcm_ids)) {
                $msg = $request->status == 1 ? 'Your account is activated by administrator' : 'Your account is deactivated by administrator';
                $type = $request->status == 1 ? 'account_activated' : 'account_deactivated';
                $full_msg = $request->status == 1 ? $msg : 'Please Contact to Administrator';
                $registrationIDs = $fcm_ids[0];

                $fcmMsg = [
                    'title' => $msg,
                    'message' => $full_msg,
                    'type' => $type,
                    'body' => $full_msg,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',

                ];
                send_push_notification($registrationIDs, $fcmMsg);
            }

            ResponseService::successResponse($request->status ? 'Customer Activated Successfully' : 'Customer Deactivated Successfully');

            return;
        }

        try {

            // Handle admin detail update via resource PUT/PATCH /customer/{id}
            if (! has_permissions('update', 'customer')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'country_code' => 'nullable|string',
                'mobile' => 'nullable|string|max:20',
                'profile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $customer = Customer::findOrFail($id);

            // Validate and format phone number if provided
            $formattedMobile = null;
            if ($request->filled('mobile') && $request->filled('country_code')) {
                $validationResult = $this->validateAndFormatPhoneNumber($request->mobile, $request->country_code);
                if (! $validationResult['valid']) {
                    ResponseService::validationError($validationResult['message']);
                }
                $formattedMobile = $validationResult['formatted_number'];
            } elseif ($request->filled('mobile') && ! $request->filled('country_code')) {
                ResponseService::validationError('Country code is required when mobile number is provided');
            }

            // Check for email uniqueness among email-login customers excluding current
            $exists = Customer::where(['email' => $request->email, 'logintype' => 3])
                ->where('id', '!=', $customer->id)
                ->count();
            if ($exists) {
                ResponseService::errorResponse('User Already Exists');
            }

            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'country_code' => $request->filled('country_code') ? $request->country_code : null,
            ];

            // Handle mobile number update
            if ($formattedMobile !== null) {
                $updateData['mobile'] = $formattedMobile;
            } elseif (! $request->filled('mobile') && ! $request->filled('country_code')) {
                // If neither mobile nor country_code is provided, keep existing mobile
                $updateData['mobile'] = $customer->mobile;
            } else {
                // If country_code is provided but mobile is empty, clear mobile
                $updateData['mobile'] = null;
            }

            if ($request->hasFile('profile')) {
                $path = config('global.USER_IMG_PATH');
                $requestFile = $request->file('profile');
                $updateData['profile'] = FileService::compressAndReplace($requestFile, $path, $customer->getRawOriginal('profile'));
            }

            $customer->update($updateData);

            ResponseService::successResponse('Customer updated successfully');
        } catch (Exception $e) {
            Log::error('Something Went Wrong in Customer Update: '.$e->getMessage());
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function customerList(Request $request)
    {
        if (! has_permissions('read', 'customer')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        if (isset($_GET['property_id'])) {
            $interested_users = InterestedUser::select('customer_id')->where('property_id', $_GET['property_id'])->pluck('customer_id');

            $sql = Customer::whereIn('id', $interested_users)->orderBy($sort, $order);
            if (isset($_GET['search']) && ! empty($_GET['search'])) {
                $search = $_GET['search'];
                $sql->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('mobile', 'LIKE', "%$search%");
                });
            }
        } else {

            $sql = Customer::orderBy($sort, $order);

            // Filter by role (user/agent)
            if (isset($_GET['role_filter']) && $_GET['role_filter'] !== '') {
                if ($_GET['role_filter'] === 'agent') {
                    $sql->where('is_agent', 1);
                } elseif ($_GET['role_filter'] === 'user') {
                    $sql->where('is_agent', 0);
                }
            }

            if (isset($_GET['search']) && ! empty($_GET['search'])) {
                $search = $_GET['search'];
                $sql->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('mobile', 'LIKE', "%$search%");
                });
            }
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $tempRow = [];
        $count = 1;

        foreach ($res as $row) {
            $operate = null;
            if ($row->is_admin_added) {
                $operate = BootstrapTableService::editButton(route('customer.edit', $row->id), false);
                $operate .= BootstrapTableService::deleteAjaxButton(route('customer.destroy', $row->id), false);
            }
            $tempRow = $row->toArray();

            // -------------------------------------------------------
            // FIX: Remove 'state' key so Bootstrap Table does not use
            // it to pre-select checkboxes on load. Bootstrap Table
            // reserves the 'state' field exclusively for checkbox state.
            // -------------------------------------------------------
            unset($tempRow['state']);

            // Mask Details in Demo Mode
            $tempRow['mobile'] = (env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ($row->mobile) : '****************************') : ($row->mobile));
            $tempRow['email'] = (env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? ($row->email) : '****************************') : ($row->email));
            $tempRow['address'] = (env('DEMO_MODE') ? (env('DEMO_MODE') == true && Auth::user()->email == 'superadmin@gmail.com' ? (! empty($row->address) ? $row->address : null) : '****************************') : (! empty($row->address) ? $row->address : null));
            $tempRow['logintype'] = $row->logintype;

            $tempRow['edit_status_url'] = 'customerstatus';
            $tempRow['is_agent'] = $row->is_agent;
            $tempRow['is_agent_verified'] = $row->is_agent_verified;
            $tempRow['total_properties'] = $row->total_properties;
            $tempRow['total_projects'] = $row->total_projects;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function create()
    {
        if (! has_permissions('create', 'customer')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $countryCodes = $this->getCountryCodes();

        return view('customer.create', compact('countryCodes'));
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'customer')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:6',
            're_password' => 'required|same:password',
            'mobile' => 'nullable|string|max:20',
            'country_code' => 'nullable|string',
            'profile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            [
                'name.required' => trans('Name is required'),
                'email.required' => trans('Email is required'),
                'email.email' => trans('Email is invalid'),
                'password.required' => trans('Password is required'),
                'password.min' => trans('Password must be at least 6 characters long'),
                're_password.required' => trans('Re-password is required'),
                're_password.same' => trans('Re-password and password must match'),
                'mobile.string' => trans('Mobile must be a string'),
                'mobile.max' => trans('Mobile must be less than 20 characters'),
                'profile.image' => trans('Profile must be an image'),
                'profile.mimes' => trans('Profile must be a jpg, jpeg, png, or webp image'),
                'profile.max' => trans('Profile must be less than 5MB'),
            ],
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        // Validate and format phone number if provided
        $formattedMobile = null;
        if ($request->filled('mobile') && $request->filled('country_code')) {
            $validationResult = $this->validateAndFormatPhoneNumber($request->mobile, $request->country_code);
            if (! $validationResult['valid']) {
                ResponseService::validationError($validationResult['message']);
            }
            $formattedMobile = $validationResult['formatted_number'];
        } elseif ($request->filled('mobile') && ! $request->filled('country_code')) {
            ResponseService::validationError('Country code is required when mobile number is provided');
        }

        // Unique check for email with logintype = 3 (email login)
        $existing = Customer::where(['email' => $request->email, 'logintype' => 3])->count();
        if ($existing) {
            ResponseService::errorResponse('User Already Exists');
        }

        try {
            $profileFilename = null;
            if ($request->hasFile('profile')) {
                $path = config('global.USER_IMG_PATH');
                $requestFile = $request->file('profile');
                $profileFilename = FileService::compressAndUpload($requestFile, $path);
            }

            $customerData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'auth_id' => Str::uuid()->toString(),
                'slug_id' => generateUniqueSlug($request->name, 5),
                'notification' => 1,
                'isActive' => 1,
                'logintype' => 3,
                'mobile' => $formattedMobile,
                'country_code' => $request->filled('country_code') ? $request->country_code : null,
                'profile' => $profileFilename,
                'is_admin_added' => true,
                'is_email_verified' => true,
            ];

            Customer::create($customerData);

            // Welcome Mail
            $emailTypeData = HelperService::getEmailTemplatesTypes('welcome_mail');
            $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
            $appName = env('APP_NAME') ?? 'eBroker';
            $variables = [
                'app_name' => $appName,
                'user_name' => $request->name,
                'email' => $request->email,
            ];
            if (empty($welcomeEmailTemplateData)) {
                $welcomeEmailTemplateData = "Welcome to $appName";
            }
            $welcomeEmailTemplate = HelperService::replaceEmailVariables($welcomeEmailTemplateData, $variables);

            $data = [
                'email_template' => $welcomeEmailTemplate,
                'email' => $request->email,
                'title' => $emailTypeData['title'],
            ];
            HelperService::sendMail($data);
            ResponseService::successResponse('Customer created successfully');
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function resetPasswordIndex(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return redirect(route('home'))->with('error', $validator->errors()->first())->send();
        }
        try {
            $token = $request->token;
            $email = HelperService::verifyToken($token);
            if ($email) {
                return view('customer.reset-password', compact('token'));
            } else {
                ResponseService::errorRedirectResponse('', trans('Invalid Token'));
            }
        } catch (Exception $e) {
            ResponseService::errorRedirectResponse('', trans('Something Went Wrong'));
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'password' => 'required|min:6',
            're_password' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $email = HelperService::verifyToken($request->token);
            if ($email) {
                $customerQuery = Customer::where(['email' => $email, 'logintype' => 3]);
                $customerCheck = $customerQuery->clone()->count();
                if (! $customerCheck) {
                    ResponseService::errorResponse('No User Found');
                }
                $password = Hash::make($request->password);
                $customerQuery->clone()->update(['password' => $password]);
                HelperService::expireToken($email);
                ResponseService::successResponse('Password Changed Successfully');
            } else {
                ResponseService::errorResponse('Token Expired');
            }
        } catch (Exception $e) {
            ResponseService::errorRedirectResponse('', 'Something Went Wrong');
        }
    }

    public function edit($id)
    {
        if (! has_permissions('update', 'customer')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $customer = Customer::findOrFail($id);
        $countryCodes = $this->getCountryCodes();

        return view('customer.edit', compact('customer', 'countryCodes'));
    }

    public function changePassword(Request $request)
    {
        if (! has_permissions('update', 'customer')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'password' => 'required|min:6',
            're_password' => 'required|same:password',
        ], [
            'password.required' => trans('Password is required'),
            'password.min' => trans('Password must be at least 6 characters long'),
            're_password.required' => trans('Re-password is required'),
            're_password.same' => trans('Re-password and password must match'),
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {

            $customer = Customer::findOrFail($request->id);
            $customer->update(['password' => Hash::make($request->password)]);
            ResponseService::successResponse('Password updated successfully');
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function destroy($id)
    {
        if (! has_permissions('delete', 'customer')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $customer = Customer::findOrFail($id);
            $profile = $customer->getRawOriginal('profile');
            if (! empty($profile)) {
                FileService::delete(config('global.USER_IMG_PATH'), $profile);
            }
            $customer->delete();
            ResponseService::successResponse('Customer deleted successfully');
        } catch (Exception $e) {
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    /**
     * Validate and format phone number based on country code
     *
     * @param  string  $mobile
     * @param  string  $countryCode
     * @return array
     */
    private function validateAndFormatPhoneNumber($mobile, $countryCode)
    {
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();

            // Get region code from country code (e.g., 91 -> IN)
            $regionCode = $this->getRegionCodeFromCountryCode($countryCode);

            if (! $regionCode) {
                return [
                    'valid' => false,
                    'message' => 'Invalid country code selected',
                    'formatted_number' => null,
                ];
            }

            // Parse the phone number with the region
            $phoneNumber = $phoneUtil->parse($mobile, $regionCode);

            // Validate the phone number
            if (! $phoneUtil->isValidNumber($phoneNumber)) {
                // Special validation for India (country code 91)
                if ($countryCode == '91') {
                    // Remove any non-digit characters for validation
                    $cleanNumber = preg_replace('/[^\d]/', '', $mobile);
                    if (strlen($cleanNumber) != 10) {
                        return [
                            'valid' => false,
                            'message' => 'Indian mobile number must be exactly 10 digits',
                            'formatted_number' => null,
                        ];
                    }
                }

                return [
                    'valid' => false,
                    'message' => 'Invalid phone number format for the selected country',
                    'formatted_number' => null,
                ];
            }

            // Get the national number (without country code) for storage
            $nationalNumber = $phoneNumber->getNationalNumber();

            // Additional validation for India: ensure it's exactly 10 digits
            if ($countryCode == '91' && strlen((string) $nationalNumber) != 10) {
                return [
                    'valid' => false,
                    'message' => 'Indian mobile number must be exactly 10 digits',
                    'formatted_number' => null,
                ];
            }

            return [
                'valid' => true,
                'message' => 'Valid phone number',
                'formatted_number' => (string) $nationalNumber,
            ];

        } catch (NumberParseException $e) {
            // Special handling for India
            if ($countryCode == '91') {
                $cleanNumber = preg_replace('/[^\d]/', '', $mobile);
                if (strlen($cleanNumber) == 10) {
                    return [
                        'valid' => true,
                        'message' => 'Valid phone number',
                        'formatted_number' => $cleanNumber,
                    ];
                }

                return [
                    'valid' => false,
                    'message' => 'Indian mobile number must be exactly 10 digits',
                    'formatted_number' => null,
                ];
            }

            return [
                'valid' => false,
                'message' => 'Invalid phone number format: '.$e->getMessage(),
                'formatted_number' => null,
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error validating phone number: '.$e->getMessage(),
                'formatted_number' => null,
            ];
        }
    }

    /**
     * Get region code from country code
     * For countries sharing the same code (e.g., US/CA both use +1), returns the first match
     *
     * @param  string  $countryCode
     * @return string|null
     */
    private function getRegionCodeFromCountryCode($countryCode)
    {
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $allRegions = $phoneUtil->getSupportedRegions();

            // Prefer common regions for shared country codes
            $preferredRegions = ['IN', 'US', 'GB', 'CA', 'AU', 'DE', 'FR', 'IT', 'ES', 'BR', 'MX', 'JP', 'CN', 'KR'];

            // First, try preferred regions
            foreach ($preferredRegions as $preferredRegion) {
                if (in_array($preferredRegion, $allRegions)) {
                    if ($phoneUtil->getCountryCodeForRegion($preferredRegion) == $countryCode) {
                        return $preferredRegion;
                    }
                }
            }

            // If not found in preferred, return first match
            foreach ($allRegions as $region) {
                if ($phoneUtil->getCountryCodeForRegion($region) == $countryCode) {
                    return $region;
                }
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function getCountryCodes()
    {
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();

            // 1. Get all supported region codes (e.g., ['US', 'IN', 'GB', ...])
            $allRegions = $phoneUtil->getSupportedRegions();

            $countryCodes = [];

            // // 2. Loop through each region to get its country code
            // foreach ($allRegions as $region) {
            //     $countryCode = $phoneUtil->getCountryCodeForRegion($region);

            //     // We can store it as +code => region for a clean list
            //     // Note: Many regions share a code (e.g., US and CA both use +1)
            //     $countryCodes['+' . $countryCode][] = $region;
            // }

            // To get a simple, unique list of just the codes:
            $uniqueCodes = array_unique(array_map(function ($region) use ($phoneUtil) {
                return $phoneUtil->getCountryCodeForRegion($region);
            }, $allRegions));

            sort($uniqueCodes);

            return $uniqueCodes;
            // $uniqueCodes will be [1, 7, 20, 27, 30, 31, ...]
        } catch (Exception $e) {
            ResponseService::errorRedirectResponse(route('customer.index'), 'Something Went Wrong');
        }
    }
}
