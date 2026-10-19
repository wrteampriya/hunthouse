<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\AgentVerification;
use App\Models\Customer;
use App\Models\NumberOtp;
use App\Models\Setting;
use App\Models\Usertokens;
use App\Models\VerifyCustomer;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Twilio\Exceptions\RestException;

class AuthApiController extends Controller
{
    public function user_signup(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'type' => 'required|in:0,1,2,3',
                'auth_id' => 'required_if:type,0,2',
                'email' => 'required_if:type,3',
                'password' => 'required_if:type,1,3',
                'country_code' => 'required_if:type,1',
                'mobile' => 'required_if:type,1',
            ],
            [
                'type.required' => trans('Type is required'),
                'auth_id.required_if' => trans('auth_id is required'),
                'email.required_if' => trans('Email is required'),
                'password.required_if' => trans('Password is required if type is email or number'),
                'type.in' => trans('Type is invalid'),
            ]
        );

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $type = $request->type;
        if ($type == 3 || $type == 1) {
            if ($type == 3) {
                $email = $request->email;
                $user = Customer::where(['email' => $email, 'logintype' => 3])->first();
            } else {
                $mobile = $request->mobile;
                $country_code = $request->country_code;
                $user = Customer::where(['mobile' => $mobile, 'country_code' => $country_code, 'logintype' => 1])->first();
            }

            if ($user) {
                if (! Hash::check($request->password, $user->password)) {
                    ApiResponseService::validationError('Invalid password');
                } elseif ($type == 3 && $user->is_email_verified == false) {
                    ApiResponseService::validationError('Email not verified', null, ['key' => config('constants.API_RESPONSE_KEY.EMAIL_NOT_VERIFIED', 'emailNotVerified')]);
                }
            } else {
                if ($type == 3) {
                    ApiResponseService::validationError('Account does not exist with this email');
                } else {
                    ApiResponseService::validationError('Account does not exist with this number');
                }
            }

            $auth_id = $user->auth_id;
        } else {
            $auth_id = $request->auth_id;
            $user = Customer::where('auth_id', $auth_id)->where('logintype', $type)->first();
        }
        if (collect($user)->isEmpty() && $type != 1 && $type != 3) {
            $validator = Validator::make(
                $request->all(),
                [
                    'mobile' => 'nullable',
                    'country_code' => 'nullable|required_with:mobile',
                    'name' => 'required',
                    'email' => 'nullable|email',
                    'auth_id' => 'required',
                    'type' => 'required',
                ],
                [
                    'country_code.required_with' => trans('Country code is required with mobile'),
                    'name.required' => trans('Name is required'),
                    'email.email' => trans('Email is invalid'),
                    'auth_id.required' => trans('Auth ID is required'),
                    'type.required' => trans('Type is required'),
                ]
            );
            $saveCustomer = new Customer;
            $saveCustomer->name = isset($request->name) ? $request->name : '';
            $saveCustomer->email = isset($request->email) ? $request->email : '';
            $saveCustomer->country_code = isset($request->country_code) ? $request->country_code : null;
            $saveCustomer->mobile = isset($request->mobile) && ! empty($request->mobile) ? str_replace(' ', '', $request->mobile) : null;
            $saveCustomer->slug_id = generateUniqueSlug($request->name, 5);
            $saveCustomer->logintype = isset($request->type) ? $request->type : '';
            $saveCustomer->address = isset($request->address) ? $request->address : '';
            $saveCustomer->auth_id = isset($request->auth_id) ? $request->auth_id : '';
            // $saveCustomer->about_me = isset($request->about_me) ? $request->about_me : '';
            // $saveCustomer->facebook_id = isset($request->facebook_id) ? $request->facebook_id : '';
            // $saveCustomer->twiiter_id = isset($request->twiiter_id) ? $request->twiiter_id : '';
            // $saveCustomer->instagram_id = isset($request->instagram_id) ? $request->instagram_id : '';
            // $saveCustomer->youtube_id = isset($request->youtube_id) ? $request->youtube_id : '';
            $saveCustomer->latitude = isset($request->latitude) ? $request->latitude : '';
            $saveCustomer->longitude = isset($request->longitude) ? $request->longitude : '';
            $saveCustomer->notification = 1;
            // $saveCustomer->about_me = isset($request->about_me) ? $request->about_me : '';
            // $saveCustomer->facebook_id = isset($request->facebook_id) ? $request->facebook_id : '';
            // $saveCustomer->twiiter_id = isset($request->twiiter_id) ? $request->twiiter_id : '';
            // $saveCustomer->instagram_id = isset($request->instagram_id) ? $request->instagram_id : '';
            $saveCustomer->isActive = '1';

            if ($request->hasFile('profile')) {
                $saveCustomer->profile = FileService::compressAndUpload($request->file('profile'), config('global.USER_IMG_PATH'));
            }
            $saveCustomer->save();
            // Create a new personal access token for the user
            $token = $saveCustomer->createToken('token-name');

            $response['error'] = false;
            $response['message'] = trans('User Register Successfully');

            $credentials = Customer::find($saveCustomer->id);
            $credentials = Customer::where('auth_id', $auth_id)->where('logintype', $type)->first();
            $credentials['is_demo_user'] = $credentials->is_demo_user;
            $credentials['is_agent'] = $credentials->is_agent;
            $credentials['is_appointment_available'] = $credentials->is_appointment_available;
            $credentials['is_user_verified'] = $credentials->is_user_verified;

            $response['token'] = $token->plainTextToken;
            $response['data'] = $credentials;
            // $response['become_agent_status']= AgentVerification::where('customer_id', $user->id)->first()?->status ?? "";

            if (! empty($credentials->email)) {
                Log::info('under Mail');
                $data = [
                    'appName' => env('APP_NAME'),
                    'email' => $credentials->email,
                ];
                try {
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes('welcome_mail');

                    // Email Template
                    $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
                    $appName = env('APP_NAME') ?? 'eBroker';
                    $variables = [
                        'app_name' => $appName,
                        'user_name' => ! empty($request->name) ? $request->name : "$appName User",
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
                } catch (Exception $e) {
                    Log::info('Welcome Mail Sending Issue with error :- '.$e->getMessage());
                }
            }
        } else {
            $credentials = Customer::where('auth_id', $auth_id)->where('logintype', $type)->first();
            if ($credentials->isActive == 0) {
                $response['error'] = true;
                $response['message'] = trans('Your account has been deactivated');
                $response['key'] = config('constants.API_RESPONSE_KEY.ACCOUNT_DEACTIVATED', 'accountDeactivated');
                $response['is_active'] = false;

                return response()->json($response);
            }
            $credentials->update();
            $token = $credentials->createToken('token-name');

            // Update or add FCM ID in UserToken for Current User
            if ($request->has('fcm_id') && ! empty($request->fcm_id)) {
                Usertokens::updateOrCreate(
                    ['fcm_id' => $request->fcm_id],
                    ['customer_id' => $credentials->id]
                );
            }

            $credentials['is_demo_user'] = $credentials->is_demo_user;
            // $credentials['is_agent'] = $credentials->is_agent;
            // $credentials['is_appointment_available'] = $credentials->is_appointment_available;
            // $credentials['is_user_verified'] = $credentials->is_user_verified;
            // $credentials['become_agent_status'] = AgentVerification::where('customer_id', $credentials->id)->where('form_type', 'become_agent')->first()?->status ?? "";
            // $credentials['agent_verification_status'] = AgentVerification::where('customer_id', $credentials->id)->where('form_type', 'verify_agent')->first()?->status ?? "";
            // $credentials['user_verification_status'] = VerifyCustomer::where('user_id',
            // $credentials->id)->first()?->status ?? "";
            $response['data'] = new CustomerResource($credentials, [
                'is_agent',
                'is_user_verified',
                'is_appointment_available',
                'become_agent_status',
                'agent_verification_status',
                'user_verification_status',
            ]);
            $response['error'] = false;
            $response['message'] = trans('Login Successfully');
            $response['token'] = $token->plainTextToken;
            // $response['data'] = $credentials;
        }

        return response()->json($response);
    }

    public function userRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:1,3',
            'firebase_id' => 'required_if:type,1',
            'name' => 'nullable',
            'mobile' => 'required_if:type,1',
            'country_code' => 'required_if:type,1',
            'email' => 'required_if:type,3|email',
            'password' => 'required|min:6',
            're_password' => 'required|same:password',
        ], [
            'firebase_id.required_if' => trans('Firebase ID is required if login type is number'),
            'name.required' => trans('Name is required'),
            'email.required' => trans('Email is required'),
            'email.email' => trans('Email is invalid'),
            'password.required' => trans('Password is required'),
            'password.min' => trans('Password must be at least 6 characters long'),
            're_password.required' => trans('Re-password is required'),
            're_password.same' => trans('Re-password and password must match'),
            'type.required' => trans('Type is required'),
            'type.in' => trans('Type is invalid'),
            'mobile.required_if' => trans('Mobile is required'),
            'country_code.required_if' => trans('Country code is required'),
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            if ($request->type == 3) {
                $customerExists = Customer::where(['email' => $request->email, 'logintype' => 3])->count();
                if ($customerExists) {
                    ApiResponseService::validationError('User Already Exists');
                }
            } else {
                $customerExists = Customer::where(['mobile' => $request->mobile, 'country_code' => $request->country_code, 'logintype' => 1])->count();
                if ($customerExists) {
                    ApiResponseService::validationError('User Already Exists');
                }
            }
            $lastID = Customer::orderBy('id', 'desc')->first()?->id ?? 0;
            $authId = $request->type == 1 ? $request->firebase_id : Str::uuid()->toString();
            if (Customer::where('auth_id', $authId)->count()) {
                ApiResponseService::validationError('Auth ID / Firebase ID already exists');
            }
            $name = $request->has('name') && ! empty($request->name) ? $request->name : 'User '.$lastID + 1;
            $customerData = [
                'name' => $name,
                'email' => $request->has('email') && ! empty($request->email) ? $request->email : '',
                'mobile' => $request->has('mobile') && ! empty($request->mobile) ? str_replace(' ', '', $request->mobile) : null,
                'country_code' => $request->has('country_code') && ! empty($request->country_code) ? $request->country_code : null,
                'password' => Hash::make($request->password),
                'auth_id' => $authId,
                'slug_id' => generateUniqueSlug($request->name, 5),
                'notification' => 1,
                'isActive' => 1,
                'logintype' => $request->type == 1 ? 1 : 3,
                'mobile' => $request->has('mobile') && ! empty($request->mobile) ? $request->mobile : null,
                'country_code' => $request->has('country_code') && ! empty($request->country_code) ? $request->country_code : null,
            ];
            Customer::create($customerData);

            if ($request->type == 3) {
                // IF login type is email, then send welcome and verify mail
                // Check if OTP already exists and is still valid
                $existingOtp = NumberOtp::where('email', $customerData['email'])->first();

                if ($existingOtp && now()->isBefore($existingOtp->expire_at)) {
                    // OTP is still valid
                    $otp = $existingOtp->otp;
                } else {
                    // Generate a new OTP
                    $otp = rand(123456, 999999);
                    $expireAt = now()->addMinutes(10); // Set OTP expiry time

                    // Update or create OTP entry in the database
                    NumberOtp::updateOrCreate(
                        ['email' => $customerData['email']],
                        ['otp' => $otp, 'expire_at' => $expireAt]
                    );
                }

                /** Register Mail */
                // Get Data of email type
                $emailTypeData = HelperService::getEmailTemplatesTypes('welcome_mail');

                // Email Template
                $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
                $appName = env('APP_NAME') ?? 'eBroker';
                $variables = [
                    'app_name' => $appName,
                    'user_name' => ! empty($request->name) ? $request->name : "$appName User",
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
                HelperService::sendMail($data, false, true);

                /** Send OTP mail for verification */
                // Get Data of email type
                $emailTypeData = HelperService::getEmailTemplatesTypes('verify_mail');

                // Email Template
                $propertyFeatureStatusTemplateData = system_setting($emailTypeData['type']);
                $appName = env('APP_NAME') ?? 'eBroker';
                $variables = [
                    'app_name' => $appName,
                    'otp' => $otp,
                ];
                if (empty($propertyFeatureStatusTemplateData)) {
                    $propertyFeatureStatusTemplateData = 'Your OTP :- '.$otp;
                }
                $propertyFeatureStatusTemplate = HelperService::replaceEmailVariables($propertyFeatureStatusTemplateData, $variables);

                $data = [
                    'email_template' => $propertyFeatureStatusTemplate,
                    'email' => $request->email,
                    'title' => $emailTypeData['title'],
                ];
                HelperService::sendMail($data, false, true);
            } else {
                // IF login type is number, then send welcome mail
                if ($request->has('email') && ! empty($request->email)) {
                    /** Register Mail */
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes('welcome_mail');
                    // Email Template
                    $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
                    $appName = env('APP_NAME') ?? 'eBroker';
                    $variables = [
                        'app_name' => $appName,
                        'user_name' => ! empty($request->name) ? $request->name : "$appName User",
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
                }
            }
            DB::commit();
            ApiResponseService::successResponse('User Registered Successfully');
        } catch (Exception $e) {
            DB::rollback();
            if (Str::contains($e->getMessage(), [
                'Failed',
                'Mail',
                'Mailer',
                'MailManager',
                'Connection could not be established',
            ])) {
                ApiResponseService::validationError('There is issue with mail configuration, kindly contact admin regarding this');
            } else {
                ApiResponseService::errorResponse();
            }
        }
    }

    public function checkNumberPasswordExists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required',
            'mobile' => 'required',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Customer::where(['mobile' => $request->mobile, 'country_code' => $request->country_code, 'logintype' => 1])->first();
            if ($user) {
                if ($user->isActive == 0) {
                    ApiResponseService::validationError(('User is not active'));
                }
                if ($user->password) {
                    $data = [
                        'user_exists' => true,
                        'password_exists' => true,
                    ];
                    ApiResponseService::successResponse(('Password Exists'), $data);
                } else {
                    $data = [
                        'user_exists' => true,
                        'password_exists' => false,
                    ];
                    ApiResponseService::validationError(('Password Does Not Exist'), $data);
                }
            } else {
                $data = [
                    'user_exists' => false,
                    'password_exists' => false,
                ];
                ApiResponseService::validationError(('User Does Not Exist'), $data);
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function updateNumberPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required',
            'country_code' => 'required',
            'password' => 'required|min:6',
            're_password' => 'required|same:password',
        ], [
            'mobile.required' => trans('Mobile is required'),
            'country_code.required' => trans('Country code is required'),
            'password.required' => trans('Password is required'),
            'password.min' => trans('Password must be at least 6 characters long'),
            're_password.required' => trans('Re-password is required'),
            're_password.same' => trans('Re-password and password must match'),
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Customer::where(['mobile' => $request->mobile, 'country_code' => $request->country_code, 'logintype' => 1])->first();
            if ($user) {
                $user->password = Hash::make($request->password);
                $user->save();
                ApiResponseService::successResponse('Password Updated Successfully');
            } else {
                ApiResponseService::validationError('User Not Found');
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function updateEmailPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required|min:6',
            're_password' => 'required|same:password',
        ], [
            'email.required' => trans('Email is required'),
            'password.required' => trans('Password is required'),
            'password.min' => trans('Password must be at least 6 characters long'),
            're_password.required' => trans('Re-password is required'),
            're_password.same' => trans('Re-password and password must match'),
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Customer::where(['email' => $request->email])->first();
            if ($user) {
                $user->password = Hash::make($request->password);
                $user->save();
                ApiResponseService::successResponse('Password Updated Successfully');
            } else {
                ApiResponseService::validationError('User Not Found');
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $isUserExists = Customer::where(['email' => $request->email, 'logintype' => 3])->count();
            if ($isUserExists) {
                $token = HelperService::generateToken();
                HelperService::storeToken($request->email, $token);

                $rootAdminUrl = env('APP_URL') ?? FacadesRequest::root();
                $trimmedEmail = ltrim($rootAdminUrl, '/'); // remove / from starting if exists
                $link = $trimmedEmail.'/reset-password?token='.$token;
                $data = [
                    'email' => $request->email,
                    'link' => $link,
                ];

                // Get Data of email type
                $emailTypeData = HelperService::getEmailTemplatesTypes('reset_password');

                // Email Template
                $verifyEmailTemplateData = system_setting('password_reset_mail_template');
                $variables = [
                    'app_name' => env('APP_NAME') ?? 'eBroker',
                    'email' => $request->email,
                    'link' => $link,
                ];
                if (empty($verifyEmailTemplateData)) {
                    $verifyEmailTemplateData = "Your reset password link is :- $link";
                }
                $verifyEmailTemplate = HelperService::replaceEmailVariables($verifyEmailTemplateData, $variables);

                $data = [
                    'email_template' => $verifyEmailTemplate,
                    'email' => $request->email,
                    'title' => $emailTypeData['title'],
                ];
                HelperService::sendMail($data, false, true);
                ApiResponseService::successResponse('Reset Link Sent Successfully');
            } else {
                ApiResponseService::validationError('No User Found');
            }
        } catch (Exception $e) {
            if (Str::contains($e->getMessage(), [
                'Failed',
                'Mail',
                'Mailer',
                'Connection could not be established',
            ])) {
                ApiResponseService::validationError('There is issue with mail configuration, kindly contact admin regarding this');
            } else {
                ApiResponseService::errorResponse();
            }
        }
    }

    public function beforeLogout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_id' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            if ($request->has('fcm_id')) {
                Usertokens::where(['fcm_id' => $request->fcm_id, 'customer_id' => $request->user()->id])->delete();
            }
            $response = [
                'error' => false,
                'message' => trans('Data Processed Successfully'),
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

    public function getOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number' => 'required_without:email|nullable',
            'country_code' => 'required_without:email|nullable',
            'email' => 'required_without:number|email|nullable|exists:customers,email',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $otpRecordDB = NumberOtp::query();
            if ($request->has('number') && ! empty($request->number)) {
                $requestNumber = $request->number; // Get data from Request
                $toNumber = '+'.$request->country_code.$requestNumber;

                // Initialize empty array
                $dbData = [];

                // make an array of types for database query and get data from settings table
                $twilioCredentialsTypes = ['twilio_account_sid', 'twilio_auth_token', 'twilio_my_phone_number'];
                $twilioCredentialsDB = Setting::select('type', 'data')->whereIn('type', $twilioCredentialsTypes)->get();

                // Loop the db result in such a way that type becomes key of array and data becomes its value in new array
                foreach ($twilioCredentialsDB as $value) {
                    $dbData[$value->type] = $value->data;
                }

                // Get Twilio credentials
                $sid = $dbData['twilio_account_sid'];
                $token = $dbData['twilio_auth_token'];
                $fromNumber = $dbData['twilio_my_phone_number'];

                // Instance Created of Twilio client with Twilio SID and token
                $client = new TwilioRestClient($sid, $token);

                // Validate phone number using Twilio Lookup API
                try {
                    $client->lookups->v1->phoneNumbers($toNumber)->fetch();
                } catch (RestException $e) {
                    return response()->json([
                        'error' => true,
                        'message' => trans('Invalid Phone Number'),
                    ]);
                }
                // Check if OTP already exists and is still valid
                $existingOtp = $otpRecordDB->clone()->where('number', $toNumber)->first();
            } elseif ($request->has('email') && ! empty($request->email)) {
                $toEmail = $request->email;
                // Check if OTP already exists and is still valid
                $existingOtp = $otpRecordDB->clone()->where('email', $toEmail)->first();
            } else {
                ApiResponseService::errorResponse();
            }

            // Check if OTP already exists and is still valid
            if ($existingOtp && now()->isBefore($existingOtp->expire_at)) {
                // OTP is still valid
                $otp = $existingOtp->otp;
            } else {
                // Generate a new OTP
                $otp = rand(123456, 999999);

                if ($request->has('number') && ! empty($request->number)) {
                    $expireAt = now()->addMinutes(3); // Set OTP expiry time
                    // Update or create OTP entry in the database
                    NumberOtp::updateOrCreate(
                        ['number' => $toNumber],
                        ['otp' => $otp, 'expire_at' => $expireAt]
                    );
                } elseif ($request->has('email') && ! empty($request->email)) {
                    $expireAt = now()->addMinutes(10); // Set OTP expiry time
                    // Update or create OTP entry in the database
                    NumberOtp::updateOrCreate(
                        ['email' => $toEmail],
                        ['otp' => $otp, 'expire_at' => $expireAt]
                    );
                } else {
                    ApiResponseService::errorResponse();
                }
            }

            if ($request->has('number') && ! empty($request->number)) {
                // Use the Client to make requests to the Twilio REST API
                $client->messages->create(
                    // The number you'd like to send the message to
                    $toNumber,
                    [
                        // A Twilio phone number you purchased at https://console.twilio.com
                        'from' => $fromNumber,
                        // The body of the text message you'd like to send
                        'body' => 'Here is the OTP: '.$otp.'. It expires in 3 minutes.',
                    ]
                );
                /** Note :- While using Trial accounts cannot send messages to unverified numbers, or purchase a Twilio number to send messages to unverified numbers.*/
            } elseif ($request->has('email') && ! empty($request->email)) {
                try {
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes('verify_mail');

                    // Email Template
                    $verifyEmailTemplateData = system_setting('verify_mail_template');
                    $variables = [
                        'app_name' => env('APP_NAME') ?? 'eBroker',
                        'otp' => $otp,
                    ];
                    if (empty($verifyEmailTemplateData)) {
                        $verifyEmailTemplateData = "Your OTP is :- $otp";
                    }
                    $verifyEmailTemplate = HelperService::replaceEmailVariables($verifyEmailTemplateData, $variables);

                    $data = [
                        'email_template' => $verifyEmailTemplate,
                        'email' => $toEmail,
                        'title' => $emailTypeData['title'],
                    ];

                    HelperService::sendMail($data, false, true);
                } catch (Exception $e) {
                    if (Str::contains($e->getMessage(), [
                        'Failed',
                        'Mail',
                        'Mailer',
                        'MailManager',
                        'Connection could not be established',
                    ])) {
                        ApiResponseService::validationError('There is issue with mail configuration, kindly contact admin regarding this');
                    } else {
                        ApiResponseService::errorResponse();
                    }
                }
            }

            // Return success response
            return response()->json([
                'error' => false,
                'message' => trans('OTP Sent Successfully'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number' => 'required_without:email|nullable',
            'country_code' => 'required_without:email|nullable',
            'email' => 'required_without:number|nullable',
            'otp' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $otpRecordDB = NumberOtp::query();
            if ($request->has('number') && ! empty($request->number)) {
                $requestNumber = $request->number; // Get data from Request
                $toNumber = '+'.$request->country_code.$requestNumber;

                // Fetch the OTP record from the database
                $otpRecord = $otpRecordDB->clone()->where('number', $toNumber)->first();
            } elseif ($request->has('email') && ! empty($request->email)) {
                $toEmail = $request->email;
                // Fetch the OTP record from the database
                $otpRecord = $otpRecordDB->clone()->where('email', $toEmail)->first();
            } else {
                ApiResponseService::errorResponse();
            }
            $userOtp = $request->otp;

            if (! $otpRecord) {
                return response()->json([
                    'error' => true,
                    'message' => trans('OTP Not Found'),
                ]);
            }

            // Check if the OTP is valid and not expired
            if ($otpRecord->otp == $userOtp && now()->isBefore($otpRecord->expire_at)) {

                if ($request->has('number') && ! empty($request->number)) {
                    // Check the number and login type exists in user table
                    $user = Customer::where(['mobile' => $requestNumber, 'country_code' => $request->country_code, 'logintype' => 1])->first();
                } elseif ($request->has('email') && ! empty($request->email)) {
                    // Check the email and login type exists in user table
                    $user = Customer::where('email', $toEmail)->where('logintype', 3)->first();
                } else {
                    ApiResponseService::errorResponse();
                }

                if (collect($user)->isNotEmpty()) {
                    $authId = $user->auth_id;
                } else {
                    // Generate a unique identifier
                    $authId = Str::uuid()->toString();
                }
                if ($request->has('email') && ! empty($request->email)) {
                    // Check the email and login type exists in user table
                    $user->is_email_verified = true;
                    $user->save();
                }

                return response()->json([
                    'error' => false,
                    'message' => trans('OTP Verified Successfully'),
                    'auth_id' => $authId,
                ]);
            } elseif ($otpRecord->otp != $userOtp) {
                ApiResponseService::validationError('Invalid OTP');
            } elseif (now()->isAfter($otpRecord->expire_at)) {
                ApiResponseService::validationError('OTP Expired');
            } else {
                ApiResponseService::errorResponse();
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeAccountTemp(Request $request)
    {
        try {
            Customer::where(['email' => $request->email, 'logintype' => 3])->delete();
            ApiResponseService::successResponse('Done');
        } catch (\Throwable $th) {
            ApiResponseService::errorResponse('Issue');
        }
    }
}
