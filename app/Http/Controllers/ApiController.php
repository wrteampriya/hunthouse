<?php

namespace App\Http\Controllers;



use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use Carbon\Carbon;

use App\Models\Faq;
use App\Models\User;
use App\Models\Chats;
use App\Models\CustomPage;
use Razorpay\Api\Api;

use App\Models\Slider;
use App\Models\Article;
use App\Models\Feature;
use App\Models\Package;
use App\Models\Setting;
use App\Models\PayAsYouGo;


use Stripe\ApiResource;
use Stripe\ApiResponse;
use App\Models\AdBanner;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Payments;
use App\Models\Projects;
use App\Models\Property;
use App\Libraries\Paypal;
use App\Models\CityImage;
use App\Models\Favourite;

// use GuzzleHttp\Client;
use App\Models\NumberOtp;
use App\Models\parameter;

use App\Models\OldPackage;

use App\Models\Usertokens;
use App\Models\Appointment;

use App\Models\ProjectView;
use App\Models\SeoSettings;
use App\Models\UserPackage;
use Carbon\CarbonInterface;

use Illuminate\Support\Str;
use App\Models\ProjectPlans;
use App\Models\PropertyView;

use App\Models\user_reports;
use App\Models\UserInterest;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\Notifications;
use App\Services\FileService;
use App\Models\InterestedUser;
use App\Models\PackageFeature;
use App\Models\PropertyImages;
use App\Models\report_reasons;
use App\Models\VerifyCustomer;
use App\Models\BankReceiptFile;
use App\Models\BlockedChatUser;
use App\Models\Contactrequests;
use App\Models\HomepageSection;
use App\Services\GeminiService;
use App\Services\HelperService;
use App\Models\AssignParameters;
use App\Models\ProjectDocuments;
use App\Models\UserPackageLimit;
use App\Models\AgentAvailability;
use App\Models\OutdoorFacilities;
use App\Models\ReportUserByAgent;
use App\Services\ResponseService;
use App\Models\AgentExtraTimeSlot;
use App\Models\PaymentTransaction;
use App\Models\PropertiesDocument;
use App\Models\VerifyCustomerForm;
use Illuminate\Support\Facades\DB;
use App\Models\AgentUnavailability;
use App\Models\VerifyCustomerValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Services\ApiResponseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Twilio\Exceptions\RestException;
use App\Models\AppointmentReschedule;
use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Cache;
use App\Models\AgentBookingPreference;
use App\Models\AppointmentCancellation;
use App\Models\OldUserPurchasedPackage;
use App\Models\VerifyCustomerFormValue;
use App\Services\Payment\PaymentService;
use App\Models\AssignedOutdoorFacilities;
use App\Models\BlockedUserForAppointment;
use Illuminate\Support\Facades\Validator;
use App\Services\PDF\PaymentReceiptService;
use Twilio\Rest\Client as TwilioRestClient;
use App\Services\AppointmentNotificationService;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use Illuminate\Support\Facades\Request as FacadesRequest;

class ApiController extends Controller
{
    //* START :: user_signup   *//
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
                'type.required' => trans("Type is required"),
                'auth_id.required_if' => trans("auth_id is required"),
                'email.required_if' => trans("Email is required"),
                'password.required_if' => trans("Password is required if type is email or number"),
                'type.in' => trans("Type is invalid")
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
                if (!Hash::check($request->password, $user->password)) {
                    ApiResponseService::validationError("Invalid password");
                } else if ($type == 3 && $user->is_email_verified == false) {
                    ApiResponseService::validationError("Email not verified", null, array('key' => config('constants.API_RESPONSE_KEY.EMAIL_NOT_VERIFIED', 'emailNotVerified')));
                }
            } else {
                if ($type == 3) {
                    ApiResponseService::validationError("Invalid email");
                } else {
                    ApiResponseService::validationError("Invalid number");
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
                    'country_code.required_with' => trans("Country code is required with mobile"),
                    'name.required' => trans("Name is required"),
                    'email.email' => trans("Email is invalid"),
                    'auth_id.required' => trans("Auth ID is required"),
                    'type.required' => trans("Type is required"),
                ]
            );
            $saveCustomer = new Customer();
            $saveCustomer->name = isset($request->name) ? $request->name : '';
            $saveCustomer->email = isset($request->email) ? $request->email : '';
            $saveCustomer->country_code = isset($request->country_code) ? $request->country_code : null;
            $saveCustomer->mobile = isset($request->mobile) && !empty($request->mobile) ? str_replace(' ', '', $request->mobile) : null;
            $saveCustomer->slug_id = generateUniqueSlug($request->name, 5);
            $saveCustomer->logintype = isset($request->type) ? $request->type : '';
            $saveCustomer->address = isset($request->address) ? $request->address : '';
            $saveCustomer->auth_id = isset($request->auth_id) ? $request->auth_id : '';
            $saveCustomer->about_me = isset($request->about_me) ? $request->about_me : '';
            $saveCustomer->facebook_id = isset($request->facebook_id) ? $request->facebook_id : '';
            $saveCustomer->twiiter_id = isset($request->twiiter_id) ? $request->twiiter_id : '';
            $saveCustomer->instagram_id = isset($request->instagram_id) ? $request->instagram_id : '';
            $saveCustomer->youtube_id = isset($request->youtube_id) ? $request->youtube_id : '';
            $saveCustomer->latitude = isset($request->latitude) ? $request->latitude : '';
            $saveCustomer->longitude = isset($request->longitude) ? $request->longitude : '';
            $saveCustomer->notification = 1;
            $saveCustomer->about_me = isset($request->about_me) ? $request->about_me : '';
            $saveCustomer->facebook_id = isset($request->facebook_id) ? $request->facebook_id : '';
            $saveCustomer->twiiter_id = isset($request->twiiter_id) ? $request->twiiter_id : '';
            $saveCustomer->instagram_id = isset($request->instagram_id) ? $request->instagram_id : '';
            $saveCustomer->isActive = '1';


            if ($request->hasFile('profile')) {
                $saveCustomer->profile = FileService::compressAndUpload($request->file('profile'), config('global.USER_IMG_PATH'));
            }
            $saveCustomer->save();
            // Create a new personal access token for the user
            $token = $saveCustomer->createToken('token-name');


            $response['error'] = false;
            $response['message'] = trans("User Register Successfully");

            $credentials = Customer::find($saveCustomer->id);
            $credentials = Customer::where('auth_id', $auth_id)->where('logintype', $type)->first();
            $credentials['is_demo_user'] = $credentials->is_demo_user;
            $credentials['is_agent'] = $credentials->is_agent;
            $credentials['is_appointment_available'] = $credentials->is_appointment_available;
            $credentials['is_user_verified'] = $credentials->is_user_verified;

            $response['token'] = $token->plainTextToken;
            $response['data'] = $credentials;

            if (!empty($credentials->email)) {
                Log::info('under Mail');
                $data = array(
                    'appName' => env("APP_NAME"),
                    'email' => $credentials->email
                );
                try {
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes("welcome_mail");

                    // Email Template
                    $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
                    $appName = env("APP_NAME") ?? "eBroker";
                    $variables = array(
                        'app_name' => $appName,
                        'user_name' => !empty($request->name) ? $request->name : "$appName User",
                        'email' => $request->email,
                    );
                    if (empty($welcomeEmailTemplateData)) {
                        $welcomeEmailTemplateData = "Welcome to $appName";
                    }
                    $welcomeEmailTemplate = HelperService::replaceEmailVariables($welcomeEmailTemplateData, $variables);

                    $data = array(
                        'email_template' => $welcomeEmailTemplate,
                        'email' => $request->email,
                        'title' => $emailTypeData['title'],
                    );
                    HelperService::sendMail($data);
                } catch (Exception $e) {
                    Log::info("Welcome Mail Sending Issue with error :- " . $e->getMessage());
                }
            }
        } else {
            $credentials = Customer::where('auth_id', $auth_id)->where('logintype', $type)->first();
            if ($credentials->isActive == 0) {
                $response['error'] = true;
                $response['message'] = trans("Your account has been deactivated");
                $response['key'] = config('constants.API_RESPONSE_KEY.ACCOUNT_DEACTIVATED', 'accountDeactivated');
                $response['is_active'] = false;
                return response()->json($response);
            }
            $credentials->update();
            $token = $credentials->createToken('token-name');

            // Update or add FCM ID in UserToken for Current User
            if ($request->has('fcm_id') && !empty($request->fcm_id)) {
                Usertokens::updateOrCreate(
                    ['fcm_id' => $request->fcm_id],
                    ['customer_id' => $credentials->id]
                );
            }

            $credentials['is_demo_user'] = $credentials->is_demo_user;
            $credentials['is_agent'] = $credentials->is_agent;
            $credentials['is_appointment_available'] = $credentials->is_appointment_available;
            $credentials['is_user_verified'] = $credentials->is_user_verified;
            $response['error'] = false;
            $response['message'] = trans("Login Successfully");
            $response['token'] = $token->plainTextToken;
            $response['data'] = $credentials;
        }
        return response()->json($response);
    }



    //* START :: get_slider   *//
    public function getSlider(Request $request)
    {
        $sliderData = Slider::select('id', 'type', 'image', 'web_image', 'category_id', 'propertys_id', 'show_property_details', 'link')->with(['category' => function ($query) {
            $query->select('id,category')->where('status', 1)->with('translations');
        }], 'property:id,title,title_image,price,propery_type as property_type')->orderBy('id', 'desc')->get()->map(function ($slider) {
            if (collect($slider->property)->isNotEmpty()) {
                $slider->property->parameters = $slider->property->parameters;
                if ($slider->category) {
                    $slider->category->translated_name = $slider->category->translated_name;
                }
            }
            return $slider;
        });

        if (collect($sliderData)->isNotEmpty()) {
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['data'] = $sliderData;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return response()->json($response);
    }

    //* END :: get_slider   *//


    //* START :: get_categories   *//
    public function get_categories(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $latitude = $request->has('latitude') ? $request->latitude : null;
        $longitude = $request->has('longitude') ? $request->longitude : null;
        $range = $request->has('range') ? $request->range : null;

        $categories = Category::select(
            'id',
            'category',
            'image',
            'parameter_types',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'slug_id'
        )
            ->where('status', 1)
            ->withCount(['properties as property_count' => function ($query) use ($latitude, $longitude, $range) {
                $query->onlyActive()
                    ->whereIn('propery_type', [0, 1])
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0);

                if ($latitude && $longitude) {
                    if ($range) {
                        $query->whereRaw("
                        (6371 * acos(
                            cos(radians(?)) * cos(radians(latitude)) *
                            cos(radians(longitude) - radians(?)) +
                            sin(radians(?)) * sin(radians(latitude))
                        )) < ?
                    ", [$latitude, $longitude, $latitude, $range]);
                    } else {
                        $query->where('latitude', $latitude)
                            ->where('longitude', $longitude);
                    }
                }
            }]);



        if ($request->boolean('has_property')) {
            $categories->whereHas('properties', function ($query) use ($latitude, $longitude, $range) {
                $query->onlyActive()
                    ->whereIn('propery_type', [0, 1])
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0);

                if ($latitude && $longitude) {
                    if ($range) {
                        $query->whereRaw("
                            (6371 * acos(
                                cos(radians(?)) * cos(radians(latitude)) *
                                cos(radians(longitude) - radians(?)) +
                                sin(radians(?)) * sin(radians(latitude))
                            )) < ?
                        ", [$latitude, $longitude, $latitude, $range]);
                    } else {
                        $query->where('latitude', $latitude)
                            ->where('longitude', $longitude);
                    }
                }
            });
        }

        if (isset($request->search) && !empty($request->search)) {
            $search = $request->search;
            $categories->where('category', 'LIKE', "%$search%")
                ->orWhere(function ($query) use ($search) {
                    $query->searchInAnyTranslation($search);
                });
        }

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $categories->where('id', $id);
        }
        if (isset($request->slug_id) && !empty($request->slug_id)) {
            $id = $request->slug_id;
            $categories->where('slug_id', $request->slug_id);
        }

        $total = $categories->clone()->count();
        $result = $categories->clone()->with('translations')->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($category) {
            if ($category) {
                $category->translated_name = $category->translated_name;
            }
            return $category;
        });

        $result->map(function ($result) {
            $result['meta_image'] = $result->image;
        });


        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            foreach ($result as $row) {
                $parameterData = $row->parameters;
                if (collect($parameterData)->isNotEmpty()) {
                    $parameterData = $parameterData->map(function ($item) {
                        $item->translated_name = $item->translated_name;
                        $item->translated_option_value = $item->translated_option_value;
                        unset($item->assigned_parameter);
                        return $item;
                    });
                }
                $row->parameter_types = collect($parameterData)->values()->toArray();
            }

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return response()->json($response);
    }
    //* END :: get_slider   *//


    //* START :: about_meofile   *//
    public function update_profile(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'country_code' => 'required',
                    'mobile' => 'required',
                    'name' => 'required',
                    'email' => 'required|email'
                ],
                [
                    'country_code.required' => trans("Country code is required"),
                    'mobile.required' => trans("Mobile is required"),
                    'name.required' => trans("Name is required"),
                    'email.required' => trans("Email is required"),
                    'email.email' => trans("Email is invalid")
                ]
            );
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();
            $currentUser = Auth::user();
            $customer =  Customer::find($currentUser->id);

            if (!empty($customer)) {

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
                    'country'
                ]);

                // Remove spaces from mobile number
                if (isset($fieldsToUpdate['mobile']) && !empty($fieldsToUpdate['mobile'])) {
                    $fieldsToUpdate['mobile'] = str_replace(' ', '', $fieldsToUpdate['mobile']);
                }

                if ($request->has('fcm_id') && !empty($request->fcm_id)) {
                    Usertokens::updateOrCreate(
                        ['fcm_id' => $request->fcm_id],
                        ['customer_id' => $customer->id,]
                    );
                }

                // Update Profile
                if ($request->hasFile('profile')) {
                    $rawImage = $customer->getRawOriginal('profile');
                    $customer->profile = FileService::compressAndReplace($request->file('profile'), config('global.USER_IMG_PATH'), $rawImage);
                }
                $customer->update($fieldsToUpdate);
                $customerData = $customer->fresh();

                DB::commit();
                return response()->json(['error' => false, 'data' => $customerData]);
            } else {
                return response()->json(['error' => false, 'message' => trans("No Data Found"), 'data' => []]);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => true, 'message' => trans("Something Went Wrong")], 500);
        }
    }

    //* END :: update_profile   *//


    //* START :: get_user_by_id   *//
    public function getUserData()
    {
        try {
            // Get LoggedIn User Data from Toke
            $userData = Auth::user();
            $userData->mobile = $userData->getRawOriginal('mobile') ?? $userData->full_mobile;
            $userData->is_demo_user = $userData->is_demo_user;
            $userData->is_agent = $userData->is_agent;
            $userData->is_user_verified = $userData->is_user_verified; // Expose verified status
            $userData->is_appointment_available = $userData->is_appointment_available;
            // Check the User Data is not Empty
            if (collect($userData)->isNotEmpty()) {
                $response['error'] = false;
                $response['data'] = $userData;
            } else {
                $response['error'] = false;
                $response['message'] = trans("No Data Found");
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }
    //* END :: get_user_by_id   *//


    //* START :: get_property   *//
    public function get_property(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
            $current_user = Auth::guard('sanctum')->user()->id;
        } else {
            $current_user = null;
        }
        $property = Property::with(['customer' => function ($query) {
            $query->withCount([
                'projects' => function ($query) {
                    $query->onlyActive();
                },
                'property' => function ($query) {
                    $query->onlyActive();
                }
            ]);
        }, 'user', 'category' => function ($categoryQuery) {
            $categoryQuery->select('id', 'category', 'image', 'slug_id')->with('translations');
        }, 'parameters', 'favourite', 'interested_users', 'translations'])->onlyActive();

        $max_price = isset($request->max_price) ? $request->max_price : Property::max('price');
        $min_price = isset($request->min_price) ? $request->min_price : 0;
        $totalClicks = 0;

        // If parameter ID passed
        if ($request->has('parameter_id') && !empty($request->parameter_id)) {
            $parameterId = $request->parameter_id;
            $property = $property->whereHas('parameters', function ($q) use ($parameterId) {
                $q->where('parameter_id', $parameterId);
            });
        }

        // If Max Price And Min Price passed
        if (isset($request->max_price) && isset($request->min_price) && (!empty($request->max_price) && !empty($min_price))) {
            $property = $property->whereBetween('price', [$min_price, $max_price]);
        }

        $property_type = $request->property_type;  //0 : Sell 1:Rent
        // If Property Type Passed
        if (isset($property_type) && (!empty($property_type) || $property_type == 0)) {
            $property = $property->where('propery_type', $property_type);
        }

        // If Posted Since 0 or 1 is passed
        if ($request->has('posted_since') && !empty($request->posted_since)) {
            $posted_since = $request->posted_since;
            // 0 - Last Week
            if ($posted_since == 0) {
                $startDateOfWeek = Carbon::now()->subWeek()->startOfWeek();
                $endDateOfWeek = Carbon::now()->subWeek()->endOfWeek();
                $property = $property->whereBetween('created_at', [$startDateOfWeek, $endDateOfWeek]);
            }
            // 1 - Yesterday
            if ($posted_since == 1) {
                $yesterdayDate = Carbon::yesterday();
                $property =  $property->whereDate('created_at', $yesterdayDate);
            }
            // 2 - Last Month
            if ($posted_since == 2) {
                $property = $property->where('created_at', '>=', Carbon::now()->subMonth());
            }
            // 3 - Last 3 Months
            if ($posted_since == 3) {
                $property = $property->where('created_at', '>=', Carbon::now()->subMonths(3));
            }
            // 4 - Last 6 Months
            if ($posted_since == 4) {
                $property = $property->where('created_at', '>=', Carbon::now()->subMonths(6));
            }
        }

        // If Category Id is Passed
        if ($request->has('category_id') && !empty($request->category_id)) {
            $property = $property->where('category_id', $request->category_id);
        }

        // If Id is passed
        if ($request->has('id') && !empty($request->id)) {
            $property = $property->where('id', $request->id);
            if (!$request->has('with_seo') || ($request->has('with_seo') && $request->with_seo != 1)) {
                HelperService::incrementTotalClick('property', $request->id);
            }
        }

        if ($request->has('category_slug_id') && !empty($request->category_slug_id)) {
            // Get the category date on category slug id
            $category = Category::where('slug_id', $request->category_slug_id)->first();
            // if category data exists then get property on the category id
            if (collect($category)->isNotEmpty()) {
                $property = $property->where('category_id', $category->id);
            }
        }

        // If Property Slug is passed
        if ($request->has('slug_id') && !empty($request->slug_id)) {
            $property = $property->where('slug_id', $request->slug_id);
            if (!$request->has('with_seo') || ($request->has('with_seo') && $request->with_seo != 1)) {
                HelperService::incrementTotalClick('property', null, $request->slug_id);
            }
        }

        // If Country is passed
        if ($request->has('country') && !empty($request->country)) {
            $property = $property->where('country', $request->country);
        }

        // If State is passed
        if ($request->has('state') && !empty($request->state)) {
            $property = $property->where('state', $request->state);
        }

        // If City is passed
        $cityParam = $request->city ?? $request->City ?? null;
        if (!empty($cityParam) && !empty(trim($cityParam))) {
            $cleanCity = trim($cityParam);
            $property = $property->where(function ($q) use ($cleanCity) {
                $q->where('city', 'like', '%' . $cleanCity . '%')
                  ->orWhere('address', 'like', '%' . $cleanCity . '%')
                  ->orWhere('client_address', 'like', '%' . $cleanCity . '%');
            });
        }

        // If place ID is passed, resolve it to city name
        if ($request->has('place_id') && !empty($request->place_id)) {
            $locationData = $this->resolvePlaceIdToLocation($request->place_id);
            if ($locationData) {
                if ($locationData['city']) {
                    $property = $property->where('city', $locationData['city']);
                }
                if ($locationData['state']) {
                    $property = $property->where('state', $locationData['state']);
                }
                if ($locationData['country']) {
                    $property = $property->where('country', $locationData['country']);
                }
            }
        }

        // If promoted is passed then get the properties according to advertisement's data except the advertisement's slider data
        if ($request->has('promoted') && !empty($request->promoted)) {
            $propertiesId = Advertisement::whereNot('type', 'Slider')->where('is_enable', 1)->pluck('property_id');
            $property = $property->whereIn('id', $propertiesId)->inRandomOrder();
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }

        // IF User Promoted Param Passed then show the User's Advertised data
        if ($request->has('users_promoted') && !empty($request->users_promoted)) {
            $propertiesId = Advertisement::where('customer_id', $current_user)->pluck('property_id');
            $property = $property->whereIn('id', $propertiesId);
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $property = $property->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%$search%")
                    ->orWhere('address', 'LIKE', "%$search%")
                    ->orWhereHas('category', function ($query1) use ($search) {
                        $query1->where('category', 'LIKE', "%$search%");
                    });
            });
        }

        // If Top Rated passed then show the property data with Order by on Total Click Descending
        if ($request->has('top_rated') && $request->top_rated == 1) {
            $property = $property->orderBy('total_click', 'DESC');
        }

        // IF Most Liked Passed then show the data according to
        if ($request->has('most_liked') && !empty($request->most_liked)) {
            $property = $property->withCount('favourite')->orderBy('favourite_count', 'DESC');
        }

        $total = $property->count();
        $result = $property->orderBy('id', 'DESC')->skip($offset)->take($limit)->get()->map(function ($item) {
            if ($item->category) {
                $item->category->translated_name = $item->category->translated_name;
            }
            return $item;
        });

        if (!$result->isEmpty()) {
            $property_details  = get_property_details($result, $current_user, true);

            // Check that Property Details exists or not
            if (isset($property_details) && collect($property_details)->isNotEmpty()) {
                /**
                 * Check that id or slug id passed and get the similar properties data according to param passed
                 * If both passed then priority given to id param
                 * */
                $similarPropertyQuery = Property::onlyActive()->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image', 'request_status', 'is_premium')->where(['category_id' => $property_details[0]['category']['id']])->inRandomOrder()->with(['category.translations', 'translations', 'customer' => function ($query) {
                    $query->withCount([
                        'projects' => function ($query) {
                            $query->onlyActive();
                        },
                        'property' => function ($query) {
                            $query->onlyActive();
                        }
                    ]);
                }])->limit(10);
                if ((isset($id) && !empty($id))) {
                    $getSimilarPropertiesQueryData = $similarPropertyQuery->where('id', '!=', $id)->get()->map(function ($item) {
                        if ($item->category) {
                            $item->category->translated_name = $item->category->translated_name;
                        }
                        return $item;
                    });
                    $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $current_user);
                } else if ((isset($request->slug_id) && !empty($request->slug_id))) {
                    $getSimilarPropertiesQueryData = $similarPropertyQuery->where('slug_id', '!=', $request->slug_id)->get()->map(function ($item) {
                        if ($item->category) {
                            $item->category->translated_name = $item->category->translated_name;
                        }
                        return $item;
                    });
                    $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $current_user, true);
                }
            }


            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['similar_properties'] = $getSimilarProperties ?? array();
            $response['total'] = $total;
            $response['data'] = $property_details;
        } else {

            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return ($response);
    }
    //* END :: get_property   *//



    //* START :: post_property   *//
    public function post_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'             => 'required',
            'description'       => 'required',
            'category_id'       => 'required',
            'property_type'     => 'required',
            'address'           => 'required',
            'title_image'       => 'required|file|max:3000|mimes:jpeg,png,jpg,webp',
            'three_d_image'     => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:3000',
            'documents.*'       => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
            'video_type'        => 'nullable|required_with:video_link,custom_video|in:0,1,2',
            'video_link'        => 'nullable|url|required_if:video_type,1,2',
            'custom_video'      => 'nullable|file|mimes:mp4,webm,ogg|max:20480|required_if:video_type,0',
            'latitude'          => 'required',
            'longitude'         => 'required',
            'rentduration'      => 'required_if:property_type,==,1',
            'meta_title'        => 'nullable|max:255',
            'meta_image'        => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description'  => 'nullable|max:255',
            'meta_keywords'     => 'nullable|max:255',
            'price'             => ['required', 'numeric', 'min:1', 'max:9223372036854775807', function ($attribute, $value, $fail) {
                if ($value >= 9223372036854775807) {
                    $fail("The Price must not exceed more than 9223372036854775807.");
                }
            }],
            //    'video_link' => ['nullable', 'url', function ($attribute, $value, $fail) {
            //         $youtubePattern = '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/(watch\?v=|embed\/|shorts\/)?([a-zA-Z0-9_-]{11})([\/\?\&\=\w\-]*)?$/';

            //         if (!preg_match($youtubePattern, $value)) {
            //             $headers = @get_headers($value);
            //             if (!$headers || strpos($headers[0], '200') === false) {
            //                 return $fail('Invalid Video Link');
            //             }
            //             return $fail('Invalid Video Link');
            //         }
            //     }],
            'translations.*.title.translation_id' => 'nullable|exists:translations,id',
            'translations.*.title.language_id' => 'nullable|exists:languages,id',
            'translations.*.title.value' => 'nullable',
            'translations.*.description.translation_id' => 'nullable|exists:translations,id',
            'translations.*.description.language_id' => 'nullable|exists:languages,id',
            'translations.*.description.value' => 'nullable',

        ], [], [
            'documents.*' => 'document :position',
            'meta_title.max' => trans('The Meta Title must not exceed more than 255 characters.'),
            'meta_image.image' => trans('The Meta Image must be an image.'),
            'meta_image.mimes' => trans('The Meta Image must be a JPG, PNG, or JPEG file.'),
            'meta_image.max' => trans('The Meta Image must not exceed more than 5MB.'),
            'meta_description.max' => trans('The Meta Description must not exceed more than 255 characters.'),
            'meta_keywords.max' => trans('The Meta Keywords must not exceed more than 255 characters.'),
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        Log::info('post_property called', [
            'has_title_image' => $request->hasFile('title_image'),
            'has_custom_video' => $request->hasFile('custom_video'),
            'all_files' => array_keys($request->allFiles()),
            'video_type' => $request->video_type
        ]);

        $videoType = $request->video_type;
        $videoLink = $request->video_link;



        if (in_array($videoType, [Property::VIDEO_YOUTUBE, Property::VIDEO_VIMEO])) {

            $youtubePattern = '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/';
            $vimeoPattern   = '/^(https?:\/\/)?(www\.)?vimeo\.com\/[0-9]+([?#][^\s]*)?$/';

            if (
                ($videoType == Property::VIDEO_YOUTUBE && !preg_match($youtubePattern, $videoLink)) ||
                ($videoType == Property::VIDEO_VIMEO && !preg_match($vimeoPattern, $videoLink))
            ) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid video link for selected video type.'
                ]);
            }
        }


        try {
            DB::beginTransaction();
            $limitResult = HelperService::updatePackageLimit('property_list', false, true);
            $isPayAsYouGo = ($limitResult === 'pay_as_you_go');
            $loggedInUserId = Auth::user()->id;
            $alertNewPropertyNotification = false;

            $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;
            $saveProperty = new Property();
            $saveProperty->category_id = $request->category_id;
            $saveProperty->slug_id = generateUniqueSlug($slugData, 1);
            $saveProperty->title = $request->title;
            $saveProperty->description = $request->description;
            $saveProperty->address = $request->address;
            $saveProperty->client_address = (isset($request->client_address)) ? $request->client_address : '';
            $saveProperty->propery_type = $request->property_type;
            $saveProperty->price = $request->price;
            $saveProperty->country = (isset($request->country)) ? $request->country : '';
            $saveProperty->state = (isset($request->state)) ? $request->state : '';
            $saveProperty->city = (isset($request->city)) ? $request->city : '';
            $saveProperty->latitude = (isset($request->latitude)) ? $request->latitude : '';
            $saveProperty->longitude = (isset($request->longitude)) ? $request->longitude : '';
            $saveProperty->rentduration = (isset($request->rentduration)) ? $request->rentduration : '';
            $saveProperty->meta_title = (isset($request->meta_title)) ? $request->meta_title : '';
            $saveProperty->meta_description = (isset($request->meta_description)) ? $request->meta_description : '';
            $saveProperty->meta_keywords = (isset($request->meta_keywords)) ? $request->meta_keywords : '';
            $saveProperty->added_by = $loggedInUserId;
            // $saveProperty->video_link = (isset($request->video_link)) ? $request->video_link : "";
            $saveProperty->video_type = $videoType;

            $videoType = $request->video_type;
            $videoLink = $request->video_link;

            $directUploadEnabled = HelperService::getSettingData('show_direct_video_upload');

            if ($videoType !== null && (int)$videoType === Property::VIDEO_CUSTOM && (int)$directUploadEnabled !== 1) {
                return response()->json([
                    'error' => true,
                    'message' => 'Direct video upload is currently disabled by admin.'
                ]);
            }


            $saveProperty->video_type = $videoType;

            if ($videoType !== null && (int)$videoType === Property::VIDEO_CUSTOM && $request->hasFile('custom_video')) {
                $path = config('global.PROPERTY_VIDEO_PATH');
                $file = $request->file('custom_video');
                $saveProperty->video_link = FileService::compressAndUpload($file, $path);
            } else {
                $saveProperty->video_link = $videoLink;
            }
            $saveProperty->package_id = $request->package_id;
            $saveProperty->post_type = 1;

            $autoApproveStatus = $this->getAutoApproveStatus($loggedInUserId);
            if ($autoApproveStatus) {
                $saveProperty->request_status = 'approved';
                $alertNewPropertyNotification = true;
            } else {
                $saveProperty->request_status = 'pending';
            }
            $saveProperty->status = 1;

            if ($isPayAsYouGo) {
                $saveProperty->expiry_date = Carbon::now()->addDays(30);
            } elseif ($autoApproveStatus) {
                $saveProperty->expiry_date = HelperService::calculateExpirationDate($loggedInUserId);
            }

            //Title Image
            if ($request->hasFile('title_image')) {
                $path = config('global.PROPERTY_TITLE_IMG_PATH');
                $file = $request->file('title_image');
                $saveProperty->title_image = FileService::compressAndUpload($file, $path, true);
            } else {
                $saveProperty->title_image  = '';
            }

            // Meta Image
            if ($request->hasFile('meta_image')) {
                $path = config('global.PROPERTY_SEO_IMG_PATH');
                $file = $request->file('meta_image');
                $saveProperty->meta_image = FileService::compressAndUpload($file, $path);
            }

            // three_d_image
            if ($request->hasFile('three_d_image')) {
                $path = config('global.3D_IMG_PATH');
                $file = $request->file('three_d_image');
                $saveProperty->three_d_image = FileService::compressAndUpload($file, $path);
            } else {
                $saveProperty->three_d_image  = '';
            }


            $showPremiumToggle = system_setting('show_premium_toggle');
            if ($showPremiumToggle == 1) {
                $saveProperty->is_premium = isset($request->is_premium) ? ($request->is_premium) : 0;
            } else {
                $saveProperty->is_premium = 0;
            }
            $saveProperty->save();

            // Link payment transaction to property (pay-as-you-go only)
            if ($isPayAsYouGo && HelperService::$lastConsumedPaymentTransactionId) {
                PaymentTransaction::where('id', HelperService::$lastConsumedPaymentTransactionId)
                    ->update(['property_id' => $saveProperty->id]);
            }

            if ($request->facilities) {
                foreach ($request->facilities as $key => $value) {
                    if (isset($value['facility_id']) && !empty($value['facility_id']) && isset($value['distance']) && !empty($value['distance'])) {
                        $facilities = new AssignedOutdoorFacilities();
                        $facilities->facility_id = $value['facility_id'];
                        $facilities->property_id = $saveProperty->id;
                        $facilities->distance = $value['distance'];
                        $facilities->save();
                    }
                }
            }
            if ($request->parameters) {
                foreach ($request->parameters as $key => $parameter) {
                    if (isset($parameter['value']) && $parameter['value'] !== null) {
                        $AssignParameters = new AssignParameters();
                        $AssignParameters->modal()->associate($saveProperty);
                        $AssignParameters->parameter_id = $parameter['parameter_id'];
                        if ($request->hasFile('parameters.' . $key . '.value')) {
                            $profile = $request->file('parameters.' . $key . '.value');
                            // Validate that the uploaded file is an image or a supported document type
                            $allowedMimeTypes = [
                                'image/jpeg',
                                'image/png',
                                'image/jpg',
                                'image/gif',
                                'image/webp',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'text/plain'
                            ];
                            $mimeType = $profile->getMimeType();
                            if (!in_array($mimeType, $allowedMimeTypes)) {
                                return ResponseService::validationError(trans('The parameter file must be an image (jpeg, png, jpg, gif, webp) or a document (pdf, doc, docx, txt).'));
                            }
                            $path = config('global.PARAMETER_IMG_PATH');
                            $AssignParameters->value = FileService::compressAndUpload($profile, $path);
                        } else {
                            $AssignParameters->value = $parameter['value'];
                        }
                        $AssignParameters->save();
                    }
                }
            }

            /// START :: UPLOAD GALLERY IMAGE
            if ($request->hasfile('gallery_images')) {
                $path = config('global.PROPERTY_GALLERY_IMG_PATH') . $saveProperty->id . '/';
                $gallaryImageData = array();
                foreach ($request->file('gallery_images') as $file) {
                    $gallaryImageData[] = array(
                        'propertys_id' => $saveProperty->id,
                        'image' => FileService::compressAndUpload($file, $path, true),
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if (!empty($gallaryImageData)) {
                    PropertyImages::insert($gallaryImageData);
                }
            }
            /// END :: UPLOAD GALLERY IMAGE


            /// START :: UPLOAD DOCUMENTS
            if ($request->hasfile('documents')) {
                $path = config('global.PROPERTY_DOCUMENT_PATH') . $saveProperty->id . '/';
                $documentsData = array();
                foreach ($request->file('documents') as $file) {
                    $documentsData[] = array(
                        'property_id' => $saveProperty->id,
                        'name' => FileService::compressAndUpload($file, $path),
                        'type' => $file->extension(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if (!empty($documentsData)) {
                    PropertiesDocument::insert($documentsData);
                }
            }
            /// END :: UPLOAD DOCUMENTS

            // START :: ADD CITY DATA
            if (isset($request->city) && !empty($request->city)) {
                CityImage::updateOrCreate(array('city' => $request->city));
            }
            // END :: ADD CITY DATA

            // START ::Add Translations
            if (isset($request->translations) && !empty($request->translations)) {
                $translationData = array();
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        if (isset($value['language_id']) && !empty($value['language_id']) && isset($value['value']) && !empty($value['value'])) {
                            $translationData[] = array(
                                'id'                => $value['translation_id'] ?? null,
                                'translatable_id'   => $saveProperty->id,
                                'translatable_type' => 'App\Models\Property',
                                'language_id'       => $value['language_id'],
                                'key'               => $key,
                                'value'             => $value['value'],
                            );
                        }
                    }
                }
                if (!empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            $result = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $saveProperty->id)->get();
            $property_details = get_property_details($result, null, true);
            if ($alertNewPropertyNotification) {
                HelperService::AlertUserForNewListing($saveProperty->id);
            }

            DB::commit();
            $response['error'] = false;
            $response['message'] = trans("Property Posted Successfully");
            $response['data'] = $property_details;
        } catch (Exception $e) {
            return ResponseService::errorResponse($e);
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
        return response()->json($response);
    }

    //* END :: post_property   *//
    //* START :: update_post_property   *//
    /// This api use for update and delete  property
    public function update_post_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action_type'           => 'required',
            'three_d_image'         => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:3000',
            'remove_three_d_image'  => 'nullable|in:0,1',
            'documents.*'           => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
            'latitude'              => 'required',
            'longitude'             => 'required',
            'rentduration'          => 'required_if:property_type,==,1',
            'meta_title'            => 'nullable|max:255',
            'meta_image'            => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description'      => 'nullable|max:255',
            'meta_keywords'         => 'nullable|max:255',
            'price'                 => ['required', 'numeric', 'min:1', 'max:9223372036854775807', function ($attribute, $value, $fail) {
                if ($value >= 9223372036854775807) {
                    $fail("The Price must not exceed more than 9223372036854775807.");
                }
            }],
            'video_type'        => 'nullable|in:0,1,2',
            'video_link' => ['nullable', 'url', function ($attribute, $value, $fail) use ($request) {
                if ($request->video_type === null) return;
                $videoType = (int) $request->video_type;
                $youtubePattern = '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/';
                $vimeoPattern   = '/^(https?:\/\/)?(www\.)?vimeo\.com\/[0-9]+([?#][^\s]*)?$/';

                if ($videoType == Property::VIDEO_YOUTUBE && !preg_match($youtubePattern, $value)) {
                    return $fail('Invalid YouTube video link.');
                }
                if ($videoType == Property::VIDEO_VIMEO && !preg_match($vimeoPattern, $value)) {
                    return $fail('Invalid Vimeo video link.');
                }
            }],
            'translations.*.title.translation_id' => 'nullable|exists:translations,id',
            'translations.*.title.language_id' => 'nullable|exists:languages,id',
            'translations.*.title.value' => 'nullable',
            'translations.*.description.translation_id' => 'nullable|exists:translations,id',
            'translations.*.description.language_id' => 'nullable|exists:languages,id',
            'translations.*.description.value' => 'nullable',

        ], [], [
            'documents.*'           => 'document :position',
            'meta_title.max'        => trans('The Meta Title must not exceed more than 255 characters.'),
            'meta_image.image'      => trans('The Meta Image must be an image.'),
            'meta_image.mimes'      => trans('The Meta Image must be a JPG, PNG, or JPEG file.'),
            'meta_image.max'        => trans('The Meta Image must not exceed more than 5MB.'),
            'meta_description.max'  => trans('The Meta Description must not exceed more than 255 characters.'),
            'meta_keywords.max'     => trans('The Meta Keywords must not exceed more than 255 characters.'),
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $id = $request->id;
            $action_type = $request->action_type;
            if ($request->slug_id) {
                $property = Property::where('added_by', $loggedInUserId)->where('slug_id', $request->slug_id)->first();
                if (!$property) {
                    $property = Property::where('added_by', $loggedInUserId)->find($id);
                }
            } else {
                $property = Property::where('added_by', $loggedInUserId)->find($id);
            }
            if ($property->getRawOriginal('propery_type') == '2') {
                ApiResponseService::validationError(trans("Sold property cannot be updated"));
            }
            if ($property->getRawOriginal('propery_type') == '3') {
                ApiResponseService::validationError(trans("Rented property cannot be updated"));
            }
            if (($property)) {
                // 0: Update 1: Delete
                if ($action_type == 0) {

                    if (isset($request->category_id)) {
                        $property->category_id = $request->category_id;
                    }

                    if (isset($request->title)) {
                        $property->title = $request->title;
                        $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;
                        $property->slug_id = generateUniqueSlug($slugData, 1, null, $id);
                    }

                    if (isset($request->slug_id) && !empty($request->slug_id)) {
                        $property->slug_id = generateUniqueSlug($request->slug_id, 1, null, $id);
                    }

                    if (isset($request->description)) {
                        $property->description = $request->description;
                    }

                    if (isset($request->address)) {
                        $property->address = $request->address;
                    }

                    if (isset($request->client_address)) {
                        $property->client_address = $request->client_address;
                    }

                    if (isset($request->property_type)) {
                        $property->propery_type = $request->property_type;
                    }

                    if (isset($request->price)) {
                        $property->price = $request->price;
                    }
                    if (isset($request->country)) {
                        $property->country = $request->country;
                    }
                    if (isset($request->state)) {
                        $property->state = $request->state;
                    }
                    if (isset($request->city)) {
                        $property->city = $request->city;
                    }
                    if (isset($request->status)) {
                        $property->status = $request->status;
                    }
                    if (isset($request->latitude)) {
                        $property->latitude = $request->latitude;
                    }
                    if (isset($request->longitude)) {
                        $property->longitude = $request->longitude;
                    }
                    if (isset($request->rentduration)) {
                        $property->rentduration = $request->rentduration;
                    }
                    if ($request->has('remove_video') && $request->remove_video == 1) {
                        if ($property->video_type == Property::VIDEO_CUSTOM && !empty($property->getRawOriginal('video_link'))) {
                            FileService::delete(config('global.PROPERTY_VIDEO_PATH'), $property->getRawOriginal('video_link'));
                        }
                        $property->video_type = null;
                        $property->video_link = null;
                    } else {
                        if (isset($request->video_type)) {
                            $property->video_type = $request->video_type;
                        }
                        $property->video_link = isset($request->video_link) && !empty($request->video_link) ? $request->video_link : null;
                    }

                    $property->meta_title = isset($request->meta_title) && !empty($request->meta_title) ? $request->meta_title : null;
                    $property->meta_description = isset($request->meta_description) && !empty($request->meta_description) ? $request->meta_description : null;
                    $property->meta_keywords = isset($request->meta_keywords) && !empty($request->meta_keywords) ? $request->meta_keywords : null;
                    $showPremiumToggle = system_setting('show_premium_toggle');
                    if ($showPremiumToggle == 1) {
                        $property->is_premium = !empty($request->is_premium) && $request->is_premium == "true" ? 1 : 0;
                    } else {
                        $property->is_premium = 0;
                    }

                    $autoApproveStatus = $this->getAutoApproveStatus($loggedInUserId);
                    if (!$autoApproveStatus) {
                        if (HelperService::getSettingData('auto_approve_edited_listings') == 0) {
                            $property->request_status = 'pending';
                        }
                    }

                    if ($request->hasFile('title_image')) {
                        $path = config('global.PROPERTY_TITLE_IMG_PATH');
                        $profile = $request->file('title_image');
                        $rawImage = $property->getRawOriginal('title_image');
                        FileService::clearCachedBlurImageUrl('blur_property_title_image_' . $property->id);
                        $property->title_image = FileService::compressAndReplace($profile, $path, $rawImage, true);
                    }


                    if ($request->has('remove_meta_image') && $request->remove_meta_image == 1) {
                        if (!empty($property->meta_image)) {
                            $url = $property->meta_image;
                            $relativePath = parse_url($url, PHP_URL_PATH);
                            $path = config('global.PROPERTY_SEO_IMG_PATH');
                            FileService::delete($path, $relativePath);
                        }
                    }

                    if ($request->has('meta_image')) {
                        if ($request->meta_image != $property->meta_image) {
                            if (!empty($request->meta_image) && $request->hasFile('meta_image')) {
                                $path = config('global.PROPERTY_SEO_IMG_PATH');
                                $profile = $request->file('meta_image');
                                $rawImage = $property->getRawOriginal('meta_image');
                                $property->meta_image = FileService::compressAndReplace($profile, $path, $rawImage);
                            }
                        }
                    }

                    if ($request->has('remove_three_d_image') && $request->remove_three_d_image == 1) {
                        $threeDImage = $property->getRawOriginal('three_d_image');
                        $path = config('global.3D_IMG_PATH');
                        FileService::delete($path, $threeDImage);
                    }

                    if ($request->hasFile('three_d_image')) {
                        $path = config('global.3D_IMG_PATH');
                        $profile = $request->file('three_d_image');
                        $rawImage = $property->getRawOriginal('three_d_image');
                        $property->three_d_image = FileService::compressAndReplace($profile, $path, $rawImage);
                    }

                    if ($request->parameters) {
                        $path = config('global.PARAMETER_IMAGE_PATH');
                        foreach ($request->parameters as $key => $parameter) {
                            $AssignParameters = AssignParameters::where('modal_id', $property->id)->where('parameter_id', $parameter['parameter_id'])->pluck('id');
                            if (count($AssignParameters)) {
                                $update_data = AssignParameters::find($AssignParameters[0]);
                                if ($request->hasFile('parameters.' . $key . '.value')) {
                                    $profile = $request->file('parameters.' . $key . '.value');
                                    $allowedMimeTypes = [
                                        'image/jpeg',
                                        'image/png',
                                        'image/jpg',
                                        'image/gif',
                                        'image/webp',
                                        'application/pdf',
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'text/plain'
                                    ];
                                    $mimeType = $profile->getMimeType();
                                    if (!in_array($mimeType, $allowedMimeTypes)) {
                                        return ResponseService::validationError(trans('The parameter file must be an image (jpeg, png, jpg, gif, webp) or a document (pdf, doc, docx, txt).'));
                                    }
                                    $rawImage = $update_data->getRawOriginal('value');
                                    $update_data->value = FileService::compressAndReplace($profile, $path, $rawImage);
                                } else {
                                    $update_data->value = $parameter['value'];
                                }
                                $update_data->save();
                            } else {
                                $AssignParameters = new AssignParameters();
                                $AssignParameters->modal()->associate($property);
                                $AssignParameters->parameter_id = $parameter['parameter_id'];
                                if ($request->hasFile('parameters.' . $key . '.value')) {
                                    $profile = $request->file('parameters.' . $key . '.value');
                                    $AssignParameters->value = FileService::compressAndUpload($profile, $path);
                                } else {
                                    $AssignParameters->value = $parameter['value'];
                                }
                                $AssignParameters->save();
                            }
                            Cache::forget("property_parameters_{$property->id}");
                        }
                    }

                    if ($request->id) {
                        $prop_id = $request->id;
                        AssignedOutdoorFacilities::where('property_id', $request->id)->delete();
                    } else {
                        $prop = Property::where('slug_id', $request->slug_id)->first();
                        $prop_id = $prop->id;
                        AssignedOutdoorFacilities::where('property_id', $prop->id)->delete();
                    }
                    // AssignedOutdoorFacilities::where('property_id', $request->id)->delete();
                    if ($request->facilities) {
                        foreach ($request->facilities as $key => $value) {
                            if (isset($value['facility_id']) && !empty($value['facility_id']) && isset($value['distance']) && !empty($value['distance'])) {
                                $facilities = new AssignedOutdoorFacilities();
                                $facilities->facility_id = $value['facility_id'];
                                $facilities->property_id = $prop_id;
                                $facilities->distance = $value['distance'];
                                $facilities->save();
                                Cache::forget("property_assign_facilities_{$property->id}");
                            }
                        }
                    }

                    $property->save();
                    $update_property = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $request->id)->get();
                    $propertyId = $request->id;

                    /// START :: UPLOAD GALLERY IMAGE
                    if ($request->remove_gallery_images) {
                        $path = config('global.PROPERTY_GALLERY_IMG_PATH') . $propertyId . "/";
                        foreach ($request->remove_gallery_images as $key => $value) {
                            $gallary_images = PropertyImages::find($value);
                            $rawImage = $gallary_images->getRawOriginal('image');
                            FileService::delete($path, $rawImage);
                            $gallary_images->delete();
                        }
                    }
                    if ($request->hasfile('gallery_images')) {
                        $path = config('global.PROPERTY_GALLERY_IMG_PATH') . $propertyId . "/";
                        $galleryImagesData = array();
                        foreach ($request->file('gallery_images') as $file) {
                            $image = FileService::compressAndUpload($file, $path, true);
                            $galleryImagesData[] = array(
                                'propertys_id' => $propertyId,
                                'image' => $image,
                                'created_at' => now(),
                                'updated_at' => now(),
                            );
                        }
                        if (collect($galleryImagesData)->isNotEmpty()) {
                            PropertyImages::insert($galleryImagesData);
                        }
                    }
                    /// END :: UPLOAD GALLERY IMAGE



                    /// START :: UPLOAD DOCUMENTS
                    if ($request->remove_documents) {
                        foreach ($request->remove_documents as $key => $value) {
                            $document = PropertiesDocument::find($value);
                            $rawImage = $document->getRawOriginal('name');
                            $path = config('global.PROPERTY_DOCUMENT_PATH') . $document->propertys_id . "/";
                            FileService::delete($path, $rawImage);
                            $document->delete();
                        }
                    }

                    if ($request->hasfile('documents')) {
                        $path = config('global.PROPERTY_DOCUMENT_PATH') . $propertyId . "/";
                        $documentsData = array();
                        foreach ($request->file('documents') as $file) {
                            $type = $file->extension();
                            $name = FileService::compressAndUpload($file, $path);
                            $documentsData[] = array(
                                'property_id' => $propertyId,
                                'name' => $name,
                                'type' => $type
                            );
                        }

                        if (collect($documentsData)->isNotEmpty()) {
                            PropertiesDocument::insert($documentsData);
                        }
                    }
                    /// END :: UPLOAD DOCUMENTS

                    // START :: ADD CITY DATA
                    if (isset($request->city) && !empty($request->city)) {
                        CityImage::updateOrCreate(array('city' => $request->city));
                    }
                    // END :: ADD CITY DATA

                    // START ::Add Translations
                    if (isset($request->translations) && !empty($request->translations)) {
                        $translationData = array();
                        foreach ($request->translations as $translation) {
                            foreach ($translation as $key => $value) {
                                if (isset($value['language_id']) && !empty($value['language_id']) && isset($value['value']) && !empty($value['value'])) {
                                    $translationData[] = array(
                                        'id'                => $value['translation_id'] ?? null,
                                        'translatable_id'   => $property->id,
                                        'translatable_type' => 'App\Models\Property',
                                        'language_id'       => $value['language_id'],
                                        'key'               => $key,
                                        'value'             => $value['value'],
                                    );
                                }
                            }
                        }
                        if (!empty($translationData)) {
                            HelperService::storeTranslations($translationData);
                        }
                    }


                    $current_user = Auth::user()->id;
                    $property_details = get_property_details($update_property, $current_user, true);
                    $response['error'] = false;
                    $response['message'] = trans("Property Updated Successfully");
                    $response['data'] = $property_details;
                } elseif ($action_type == 1) {
                    if ($property->delete()) {
                        $response['error'] = false;
                        $response['message'] = trans("Data Deleted Successfully");
                    } else {
                        $response['error'] = true;
                        $response['message'] = trans("Something Went Wrong");
                    }
                }
            } else {
                $response['error'] = false;
                $response['message'] = trans("No Data Found");
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }

        return response()->json($response);
    }
    //* END :: update_post_property   *//


    //* START :: remove_post_images   *//
    public function remove_post_images(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if (!$validator->fails()) {
            $id = $request->id;
            $getImage = PropertyImages::where('id', $id)->first();
            $image = $getImage->image;
            $propertys_id =  $getImage->propertys_id;

            if (PropertyImages::where('id', $id)->delete()) {
                $path = config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id . "/";
                FileService::delete($path, $image);
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }

            $countImage = PropertyImages::where('propertys_id', $propertys_id)->get();
            if ($countImage->count() == 0) {
                rmdir(storage_path('app/public') . config('global.PROPERTY_GALLERY_IMG_PATH') . $propertys_id);
            }

            $response['error'] = false;
            $response['message'] = trans("Property Image Removed Successfully");
        } else {
            $response['error'] = true;
            $response['message'] = trans("Please fill all data and Submit");
        }

        return response()->json($response);
    }
    //* END :: remove_post_images   *//

    //* START :: set_property_inquiry   *//




    //* START :: get_notification_list   *//
    public function get_notification_list(Request $request)
    {
        $loggedInUserData = Auth::user();
        $loggedInUserId = $loggedInUserData->id;
        $loggedInCreatedAt = $loggedInUserData->created_at;
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $notificationQuery = Notifications::where(function ($query) use ($loggedInUserId, $loggedInCreatedAt) {
            $query->where(["customers_id" => $loggedInUserId, "send_type" => 0])
                ->orWhere(function ($query) use ($loggedInCreatedAt) {
                    $query->where('send_type', 1)
                        ->where('created_at', '>=', $loggedInCreatedAt);
                });
        })
            ->with('property:id,title_image')
            ->select('id', 'title', 'message', 'image', 'type', 'send_type', 'customers_id', 'propertys_id', 'created_at')
            ->orderBy('id', 'DESC');

        $total = $notificationQuery->count();

        $result = $notificationQuery->clone()
            ->skip($offset)
            ->take($limit)
            ->get();


        if (!$result->isEmpty()) {
            $result = $result->map(function ($notification) {
                $notification->created = $notification->created_at->diffForHumans();
                $notification->notification_image = !empty($notification->image) ? $notification->image : (!empty($notification->propertys_id) && !empty($notification->property) ? $notification->property->title_image : "");
                unset($notification->image);
                return $notification;
            });

            $response = [
                'error' => false,
                'total' => $total,
                'data' => $result->toArray(),
            ];
        } else {
            $response = [
                'error' => false,
                'message' => trans("No Data Found"),
                'data' => [],
            ];
        }

        return response()->json($response);
    }
    //* END :: get_notification_list   *//


    //* START :: delete_user   *//
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
            $response['message'] = trans("Data Deleted Successfully");
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollBack();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }
    //* END :: delete_user   *//
    public function bearerToken($request)
    {
        $header = $request->header('Authorization', '');
        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }
    }
    //*START :: add favoutite *//
    public function add_favourite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'property_id' => 'required',


        ]);

        if (!$validator->fails()) {
            //add favourite
            $current_user = Auth::user()->id;
            if ($request->type == 1) {


                $fav_prop = Favourite::where('user_id', $current_user)->where('property_id', $request->property_id)->get();

                if (count($fav_prop) > 0) {
                    $response['error'] = false;
                    $response['message'] = trans("Property Already Added To Favourite");
                    return response()->json($response);
                }
                $favourite = new Favourite();
                $favourite->user_id = $current_user;
                $favourite->property_id = $request->property_id;
                $favourite->save();
                $response['error'] = false;
                $response['message'] = trans("Property Added To Favourite Successfully");
            }
            //delete favourite
            if ($request->type == 0) {
                Favourite::where('property_id', $request->property_id)->where('user_id', $current_user)->delete();

                $response['error'] = false;
                $response['message'] = trans("Property Removed From Favourite Successfully");
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }


        return response()->json($response);
    }

    public function get_articles(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $article = Article::with('category:id,category,slug_id', 'category.translations', 'translations')->select('id', 'slug_id', 'image', 'title', 'description', 'view_count', 'meta_title', 'meta_description', 'meta_keywords', 'category_id', 'created_at');

        if (isset($request->category_id)) {
            $category_id = $request->category_id;
            if ($category_id == 0) {
                $article = $article->clone()->where('category_id', '');
            } else {

                $article = $article->clone()->where('category_id', $category_id);
            }
        }

        if (isset($request->id)) {
            $similarArticles = $article->clone()->where('id', '!=', $request->id)->get()->map(function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                $item->translated_title = $item->translated_title;
                $item->translated_description = $item->translated_description;
                return $item;
            });
            $article = $article->clone()->where('id', $request->id);
            $article->increment('view_count');
        } else if (isset($request->slug_id)) {
            $similarArticles = $article->clone()->where('slug_id', '!=', $request->slug_id)->get()->map(function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                $item->translated_title = $item->translated_title;
                $item->translated_description = $item->translated_description;
                return $item;
            });
            $article = $article->clone()->where('slug_id', $request->slug_id);
            if (!$request->has('with_seo') || ($request->has('with_seo') && $request->with_seo != 1)) {
                $article->increment('view_count');
            }
        }


        $total = $article->clone()->get()->count();
        $result = $article->clone()->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($item) {
            if ($item->category) {
                $item->category->translated_name = $item->category->translated_name;
            }
            $item->translated_title = $item->translated_title;
            $item->translated_description = $item->translated_description;

            unset($item->meta_image);
            $item->meta_image = $item->image;

            $item->posted_on = $item->created_at->diffForHumans();
            return $item;
        });
        if (!$result->isEmpty()) {
            $response['data'] = $result;
            $response['similar_articles'] = $similarArticles ?? array();
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['total'] = $total;
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
            $advertisementQuery = Advertisement::whereIn('status', [0, 1]);
            if ($request->feature_for == 'property') {
                $packageData = HelperService::updatePackageLimit('property_feature', true);
                $checkAdvertisement = $advertisementQuery->clone()->where('property_id', $request->property_id)->count();
            } else {
                $packageData = HelperService::updatePackageLimit('project_feature', true);
                $checkAdvertisement = $advertisementQuery->clone()->where('project_id', $request->project_id)->count();
            }
            if (collect($packageData)->isEmpty()) {
                ApiResponseService::validationError("Package not found");
            }
            if (!empty($checkAdvertisement)) {
                ApiResponseService::validationError("Advertisement already exists");
            }
            $advertisementData = new Advertisement();
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
            $autoApproveStatus = $this->getAutoApproveStatus($current_user);
            if ($autoApproveStatus) {
                $advertisementData->status = 0;
                $advertisementData->is_enable = true;
            } else {
                $advertisementData->status = 1;
                $advertisementData->is_enable = false;
            }
            $advertisementData->save();

            DB::commit();
            ApiResponseService::successResponse("Advertisement added successfully");
        } catch (\Throwable $th) {
            DB::rollback();
            ApiResponseService::errorResponse();
        }
    }

    public function get_advertisement(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $date = date('Y-m-d');

        $adv = Advertisement::select('id', 'image', 'category_id', 'property_id', 'type', 'customer_id', 'is_enable', 'status')->with('customer:id,name')->where('end_date', '>', $date);
        if (isset($request->customer_id)) {
            $adv->where('customer_id', $request->customer_id);
        }
        $total = $adv->get()->count();
        $result = $adv->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        if (!$result->isEmpty()) {
            foreach ($adv as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.ADVERTISEMENT_IMAGE_PATH') . $row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }


        return response()->json($response);
    }
    public function get_package(Request $request)
    {
        if ($request->platform == "ios") {
            $packages = OldPackage::where('status', 1)
                ->where('ios_product_id', '!=', '')
                ->orderBy('price', 'ASC')
                ->get();
        } else {
            $packages = Package::where('status', 1)
                ->orderBy('price', 'ASC')
                ->get();
        }

        $packages->transform(function ($item) use ($request) {
            if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                $currentDate = Carbon::now()->format("Y-m-d");

                $loggedInUserId = Auth::guard('sanctum')->user()->id;
                $user_package = OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->where(function ($query) use ($currentDate) {
                    $query->whereDate('start_date', '<=', $currentDate)
                        ->whereDate('end_date', '>=', $currentDate);
                });

                if ($request->type == 'property') {
                    $user_package->where('prop_status', 1);
                } else if ($request->type == 'advertisement') {
                    $user_package->where('adv_status', 1);
                }

                $user_package = $user_package->where('package_id', $item->id)->first();


                if (!empty($user_package)) {
                    $startDate = new DateTime(Carbon::now());
                    $endDate = new DateTime($user_package->end_date);

                    // Calculate the difference between two dates
                    $interval = $startDate->diff($endDate);

                    // Get the difference in days
                    $diffInDays = $interval->days;

                    $item['is_active'] = 1;
                    $item['type'] = $item->type === "premium_user" ? "premium_user" : "product_listing";

                    if (!($item->type === "premium_user")) {
                        $item['used_limit_for_property'] = $user_package->used_limit_for_property;
                        $item['used_limit_for_advertisement'] = $user_package->used_limit_for_advertisement;
                        $item['property_status'] = $user_package->prop_status;
                        $item['advertisement_status'] = $user_package->adv_status;
                    }

                    $item['start_date'] = $user_package->start_date;
                    $item['end_date'] = $user_package->end_date;
                    $item['remaining_days'] = $diffInDays;
                } else {
                    $item['is_active'] = 0;
                }
            }

            if (!($item->type === "premium_user")) {
                $item['advertisement_limit'] = $item->advertisement_limit == '' ? "unlimited" : ($item->advertisement_limit == 0 ? "not_available" : $item->advertisement_limit);
                $item['property_limit'] = $item->property_limit == '' ? "unlimited" : ($item->property_limit == 0 ? "not_available" : $item->property_limit);
            } else {
                unset($item['property_limit']);
                unset($item['advertisement_limit']);
            }


            return $item;
        });

        // Sort the packages based on is_active flag (active packages first)
        $packages = $packages->sortByDesc('is_active');

        $response = [
            'error' => false,
            'message' => trans("Data Fetched Successfully"),
            'data' => $packages->values()->all(), // Reset the keys after sorting
        ];

        return response()->json($response);
    }
    public function user_purchase_package(Request $request)
    {

        $start_date =  Carbon::now();
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
        ]);

        if (!$validator->fails()) {
            $loggedInUserId = Auth::user()->id;
            if (isset($request->flag)) {
                $user_exists = OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->get();
                if ($user_exists) {
                    OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->delete();
                }
            }

            $package = Package::find($request->package_id);
            $user = Customer::find($loggedInUserId);
            $data_exists = OldUserPurchasedPackage::where('modal_id', $loggedInUserId)->get();
            if (count($data_exists) == 0 && $package) {
                $user_package = new OldUserPurchasedPackage();
                $user_package->modal()->associate($user);
                $user_package->package_id = $request->package_id;
                $user_package->start_date = $start_date;
                $user_package->end_date = $package->duratio != 0 ? Carbon::now()->addDays($package->duration) : NULL;
                $user_package->save();

                $user->subscription = 1;
                $user->update();

                $response['error'] = false;
                $response['message'] = trans("Purchased Package Added Successfully");
            } else {
                $response['error'] = true;
                $response['message'] = trans("Data Already Exists Or Package Not Found Or Add Flag For Add New Package");
            }
        } else {
            $response['error'] = true;
            $response['message'] = trans("Please fill all data and Submit");
        }
        return response()->json($response);
    }
    public function get_favourite_property(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;

        $current_user = Auth::user()->id;

        $favourite = Favourite::where('user_id', $current_user)->select('property_id')->get();
        $arr = array();
        foreach ($favourite as $p) {
            $arr[] =  $p->property_id;
        }

        $property_details = Property::whereIn('id', $arr)->onlyActive()->with('category:id,category,image', 'category.translations', 'translations')->with('assignfacilities.outdoorfacilities')->with('parameters');
        $result = $property_details->clone()->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($property) {
            $property->category->translated_name = $property->category->translated_name;
            $property->translated_title = $property->translated_title;
            $property->translated_description = $property->translated_description;
            return $property;
        });

        $total = $property_details->clone()->count();

        if (!$result->isEmpty()) {

            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['data'] =  get_property_details($result, $current_user, true);
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function delete_advertisement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',

        ]);

        if (!$validator->fails()) {
            $adv = Advertisement::find($request->id);
            if (!$adv) {
                $response['error'] = false;
                $response['message'] = trans("Data Not Found");
            } else {

                $adv->delete();
                $response['error'] = false;
                $response['message'] = trans("Advertisement Deleted Successfully");
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function interested_users(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
            'type' => 'required'


        ]);
        if (!$validator->fails()) {
            $current_user = Auth::user()->id;

            $interested_user = InterestedUser::where('customer_id', $current_user)->where('property_id', $request->property_id);

            if ($request->type == 1) {

                if (count($interested_user->get()) > 0) {
                    $response['error'] = false;
                    $response['message'] = trans("Already Added To Interested Users");
                } else {
                    $interested_user = new InterestedUser();
                    $interested_user->property_id = $request->property_id;
                    $interested_user->customer_id = $current_user;
                    $interested_user->save();
                    $response['error'] = false;
                    $response['message'] = trans("Interested Users Added Successfully");
                }
            }
            if ($request->type == 0) {

                if (count($interested_user->get()) == 0) {
                    $response['error'] = false;
                    $response['message'] = trans("No Data Found To Delete");
                } else {
                    $interested_user->delete();

                    $response['error'] = false;
                    $response['message'] = trans("Interested Users Removed Successfully");
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }
        return response()->json($response);
    }

    public function user_interested_property(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;

        $current_user = Auth::user()->id;


        $favourite = InterestedUser::where('customer_id', $current_user)->select('property_id')->get();
        $arr = array();
        foreach ($favourite as $p) {
            $arr[] =  $p->property_id;
        }
        $property_details = Property::whereIn('id', $arr)->with('category:id,category')->with('parameters');
        $result = $property_details->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();


        $total = $result->count();

        if (!$result->isEmpty()) {
            foreach ($property_details as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('') . config('global.IMG_PATH') . config('global.PROPERTY_TITLE_IMG_PATH') . $row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['data'] = $result;
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_languages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language_code' => 'required',
        ]);

        if (!$validator->fails()) {
            if ($request->language_code == 'en') {
                $request->language_code = 'en-new';
            }
            $language = Language::where('code', $request->language_code)->first();

            if ($language) {
                if ($request->web_language_file) {
                    $json_file_path = public_path('web_languages/' . $request->language_code . '.json');
                } else {
                    $json_file_path = public_path('languages/' . $request->language_code . '.json');
                }

                if (file_exists($json_file_path)) {
                    $json_string = file_get_contents($json_file_path);
                    $json_data = json_decode($json_string);

                    if ($json_data !== null) {
                        $language->file_name = $json_data;
                        $response['error'] = false;
                        $response['message'] = trans("Data Fetched Successfully");
                        $response['data'] = $language;
                    } else {
                        $response['error'] = true;
                        $response['message'] = trans("Invalid JSON format in the language file");
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = trans("Language file not found");
                }
            } else {
                $response['error'] = true;
                $response['message'] = trans("Language not found");
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }

        return response()->json($response);
    }
    public function getPaymentTransactionDetails(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_type' => 'nullable|string|in:online payment,bank transfer,free'
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            // Get offset and limit
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            // Get logged in user id
            $loggedInUserId = Auth::user()->id;

            // Get payment query
            $paymentQuery = PaymentTransaction::where('user_id', $loggedInUserId)
                // Filter by payment type if provided
                ->when($request->payment_type, function ($query) use ($request) {
                    $query->where('payment_type', $request->payment_type);
                });
            // Get total count of filtered results
            $total = $paymentQuery->clone()->count();
            // Get paginated results
            $result = $paymentQuery->with('package:id,name,price')->orderBy('created_at', 'DESC')->skip($offset)->take($limit)->get();

            if (count($result)) {
                ApiResponseService::successResponse("Data Fetched Successfully", $result, array('total' => $total));
            } else {
                ApiResponseService::successResponse("No Data Found");
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }
    public function get_payment_settings(Request $request)
    {
        $payment_settings = Setting::select('type', 'data')->whereIn('type', ['paypal_gateway', 'razorpay_gateway', 'paystack_gateway', 'stripe_gateway', 'flutterwave_status', 'cashfree_gateway', 'bank_transfer_status', 'phonepe_gateway', 'midtrans_gateway'])->get();
        foreach ($payment_settings as $setting) {
            if ($setting->type === 'stripe_secret_key') {
                $publicKey = file_get_contents(base_path('public_key.pem')); // Load the public key
                $encryptedData = '';
                if (openssl_public_encrypt($setting->data, $encryptedData, $publicKey)) {
                    $setting->data = base64_encode($encryptedData);
                }
            }
        }

        if (count($payment_settings)) {
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['data'] = $payment_settings;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return (response()->json($response));
    }
    public function send_message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sender_id' => 'required',
            'receiver_id' => 'required',
            'property_id' => 'required',
            'file' => 'nullable|mimes:png,jpg,jpeg,webp,pdf,doc,docx|max:2024',
            'audio' => 'nullable|mimes:mpeg,m4a,mp3,mp4|max:5024'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        $customer = Customer::select('id', 'name', 'profile')->with(['usertokens' => function ($q) {
            $q->select('fcm_id', 'id', 'customer_id');
        }])->find($request->receiver_id);
        if (collect($customer)->isNotEmpty()) {
            $senderBlockedReciever = BlockedChatUser::where(['by_user_id' => $request->sender_id, 'user_id' => $request->receiver_id])->count();
            if ($senderBlockedReciever) {
                ApiResponseService::validationError("You have blocked user");
            }
            $recieverBlockedSender = BlockedChatUser::where(['by_user_id' => $request->receiver_id, 'user_id' => $request->sender_id])->count();
            if ($recieverBlockedSender) {
                ApiResponseService::validationError("You are blocked by user");
            }
        } else {
            $senderBlockedReciever = BlockedChatUser::where(['by_user_id' => $request->sender_id, 'admin' => 1])->count();
            if ($senderBlockedReciever) {
                ApiResponseService::validationError("You have blocked admin");
            }
            $recieverBlockedSender = BlockedChatUser::where(['by_admin' => 1, 'user_id' => $request->sender_id])->count();
            if ($recieverBlockedSender) {
                ApiResponseService::validationError("You are blocked by admin");
            }
        }

        $fcm_id = array();
        $chat = new Chats();
        $chat->sender_id = $request->sender_id;
        $chat->receiver_id = $request->receiver_id;
        $chat->property_id = $request->property_id;
        $chat->message = $request->message;

        // Files upload
        if ($request->hasFile('file')) {
            $path = config('global.CHAT_FILE');
            $file = $request->file('file');
            $chat->file = FileService::compressAndUpload($file, $path);
        }

        if ($request->hasFile('audio')) {
            $path = config('global.CHAT_AUDIO');
            $file = $request->file('audio');
            $chat->audio = FileService::compressAndUpload($file, $path);
        }
        $chat->save();

        if ($customer) {
            foreach ($customer->usertokens as $usertokens) {
                array_push($fcm_id, $usertokens->fcm_id);
            }
            $username = $customer->name;
        } else {

            $user_data = User::select('fcm_id', 'name')->get();
            $username = "Admin";
            foreach ($user_data as $user) {
                array_push($fcm_id, $user->fcm_id);
            }
        }
        $senderUser = Customer::select('fcm_id', 'name', 'profile')->find($request->sender_id);
        if ($senderUser) {
            $profile = $senderUser->profile;
        } else {
            $profile = "";
        }

        $Property = Property::find($request->property_id);






        $chat_message_type = "";

        if (!empty($request->file('audio'))) {
            $chat_message_type = "audio";
        } else if (!empty($request->file('file')) && $request->message == "") {
            $chat_message_type = "file";
        } else if (!empty($request->file('file')) && $request->message != "") {
            $chat_message_type = "file_and_text";
        } else if (empty($request->file('file')) && $request->message != "" && empty($request->file('audio'))) {
            $chat_message_type = "text";
        } else {
            $chat_message_type = "text";
        }


        // Get UnRead Messages Count
        $unreadMessagesCount = Chats::where(['property_id' => $request->property_id, 'receiver_id' => $request->sender_id, 'is_read' => false])->count();


        $fcmMsg = array(
            'title' => 'Message',
            'message' => $request->message,
            'type' => 'chat',
            'body' => $request->message,
            'sender_id' => $request->sender_id,
            'sender_name' => $senderUser->name ?? 'User',
            'sender_profile' => $senderUser->profile ?? '',
            'receiver_id' => $request->receiver_id,
            'file' => $chat->file,
            'username' => $username,
            'user_profile' => $profile,
            'audio' => $chat->audio,
            'date' => $chat->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'sound' => 'default',
            'time_ago' => $chat->created_at->diffForHumans(now(), CarbonInterface::DIFF_RELATIVE_AUTO, true),
            'property_id' => (string)$Property->id,
            'property_title_image' => $Property->title_image,
            'title' => $Property->title,
            'chat_message_type' => $chat_message_type,
            'created_at' => Carbon::parse($chat->created_at)->toIso8601ZuluString(),
            'unread_messages_count' => (string)$unreadMessagesCount
        );


        file_put_contents(storage_path('logs/debug_notification.log'), "ApiController: send_message called at " . now() . "\n", FILE_APPEND);
        $send = send_push_notification($fcm_id, $fcmMsg);
        file_put_contents(storage_path('logs/debug_notification.log'), "ApiController: send_message notification sent at " . now() . "\n", FILE_APPEND);
        $response['error'] = false;
        $response['message'] = "Data Store Successfully";
        $response['id'] = $chat->id;
        // $response['data'] = $send;
        return (response()->json($response));
    }
    public function get_messages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
            'user_id' => 'required'

        ]);
        if (!$validator->fails()) {
            $currentUser = Auth::user();
            $adminData = User::where('type', 0)->select('id', 'name', 'profile')->first();
            $userId = $request->user_id;

            // update is_read to true for the current user
            Chats::where(['property_id' => $request->property_id, 'receiver_id' => $currentUser->id, 'is_read' => false])
                ->update(['is_read' => true]);

            $perPage = $request->per_page ? $request->per_page : 15; // Number of results to display per page
            $page = $request->page ?? 1; // Get the current page from the query string, or default to 1
            $chat = Chats::where('property_id', $request->property_id)
                ->where(function ($query) use ($currentUser, $userId) {
                    $query->where(function ($query) use ($currentUser, $userId) {
                        $query->where(['sender_id' => $currentUser->id, 'receiver_id' => $userId]);
                    })->orWhere(function ($query) use ($currentUser, $userId) {
                        $query->where(['sender_id' => $userId, 'receiver_id' => $currentUser->id]);
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage, ['*'], 'page', $page);

            // You can then pass the $chat object to your view to display the paginated results.




            $isChatWithAdmin = false;
            $isChatWithCustomer = false;
            $chat_message_type = "";
            if ($chat) {
                $chat->map(function ($chat) use ($chat_message_type, $currentUser, $adminData, &$isChatWithAdmin, &$isChatWithCustomer) {
                    if (!empty($chat->audio)) {
                        $chat_message_type = "audio";
                    } else if (!empty($chat->file) && $chat->message == "") {
                        $chat_message_type = "file";
                    } else if (!empty($chat->file) && $chat->message != "") {
                        $chat_message_type = "file_and_text";
                    } else if (empty($chat->file) && !empty($chat->message) && empty($chat->audio)) {
                        $chat_message_type = "text";
                    } else {
                        $chat_message_type = "text";
                    }
                    $chat['chat_message_type'] = $chat_message_type;
                    $chat['user_profile'] = $currentUser->profile;
                    $chat['time_ago'] = $chat->created_at->diffForHumans();
                    if ($chat->sender_id == $adminData->id || $chat->receiver_id == $adminData->id) {
                        $isChatWithAdmin = true;
                    } else {
                        $isChatWithCustomer = true;
                    }
                });

                $isBlockedByMe = false;
                $isBlockedByUser = false;
                if ($isChatWithAdmin == true) {
                    // For admin chats, check if user blocked admin or admin blocked user
                    $isBlockedByMe = BlockedChatUser::where('by_user_id', $currentUser->id)
                        ->where('admin', 1)
                        ->exists();
                    $isBlockedByUser = BlockedChatUser::where('by_admin', 1)
                        ->where('user_id', $currentUser->id)
                        ->exists();
                } else {
                    // For regular user chats, check blocking between users
                    $isBlockedByMe = BlockedChatUser::where('by_user_id', $currentUser->id)
                        ->where('user_id', $userId)
                        ->exists();
                    $isBlockedByUser = BlockedChatUser::where('by_user_id', $userId)
                        ->where('user_id', $currentUser->id)
                        ->exists();
                }

                $response['error'] = false;
                $response['message'] = trans("Data Fetched Successfully");
                $response['total_page'] = $chat->lastPage();
                $response['data'] = array_merge($chat->toArray(), [
                    'is_blocked_by_me' => $isBlockedByMe,
                    'is_blocked_by_user' => $isBlockedByUser
                ]);
            } else {
                $response['error'] = false;
                $response['message'] = trans("No Data Found");
                $response['data'] = [];
            }
        } else {
            $response['error'] = true;
            $response['message'] = trans("Please fill all data and Submit");
        }
        return response()->json($response);
    }

    public function get_chats(Request $request)
    {
        $current_user = Auth::user()->id;
        $perPage = $request->per_page ? $request->per_page : 15; // Number of results to display per page
        $page = $request->page ?? 1;

        $adminData = User::where('type', 0)->select('id', 'name', 'profile')->first();

        $chat = Chats::with(['sender', 'receiver'])->with('property.translations')
            ->select(
                'id',
                'sender_id',
                'receiver_id',
                'property_id',
                'created_at',
                DB::raw('LEAST(sender_id, receiver_id) as user1_id'),
                DB::raw('GREATEST(sender_id, receiver_id) as user2_id'),
                DB::raw('COUNT(CASE WHEN receiver_id = ' . $current_user . ' AND is_read = 0 THEN 1 END) AS unread_count')
            )
            ->where(function ($query) use ($current_user) {
                $query->where('sender_id', $current_user)
                    ->orWhere('receiver_id', $current_user);
            })
            ->orderBy('id', 'desc')
            ->groupBy('user1_id', 'user2_id', 'property_id')
            ->paginate($perPage, ['*'], 'page', $page);

        if (!$chat->isEmpty()) {

            $rows = array();

            $count = 1;

            $response['total_page'] = $chat->lastPage();

            foreach ($chat as $key => $row) {
                $tempRow = array();
                $tempRow['property_id'] = $row->property_id;
                $tempRow['title'] = $row->property->title;
                $tempRow['translated_title'] = $row->property->translated_title;
                $tempRow['title_image'] = $row->property->title_image;
                $tempRow['date'] = $row->created_at;
                $tempRow['property_id'] = $row->property_id;
                $tempRow['unread_count'] = $row->unread_count;
                if (!$row->receiver || !$row->sender) {
                    $user = Customer::where('id', $row->sender_id)->orWhere('id', $row->receiver_id)->select('id')->first();

                    $isBlockedByMe = false;
                    $isBlockedByUser = false;

                    $blockedByMe = BlockedChatUser::where('by_user_id', $user->id)
                        ->where('admin', 1)
                        ->exists();

                    $blockedByAdmin = BlockedChatUser::where('by_admin', 1)
                        ->where('user_id', $user->id)
                        ->exists();
                    $tempRow['is_blocked_by_me'] = $blockedByMe;
                    $tempRow['is_blocked_by_user'] = $blockedByAdmin;


                    $tempRow['user_id'] = 0;
                    $tempRow['name'] = "Admin";
                    $tempRow['profile'] = !empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg');

                    // $tempRow['fcm_id'] = $row->receiver->fcm_id;
                } else {

                    $isBlockedByMe = false;
                    $isBlockedByUser = false;
                    if ($row->sender->id == $current_user) {
                        $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                            ->where('user_id', $row->receiver->id)
                            ->exists();

                        $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->receiver->id)
                            ->where('user_id', $current_user)
                            ->exists();

                        $tempRow['is_blocked_by_me'] = $isBlockedByMe;
                        $tempRow['is_blocked_by_user'] = $isBlockedByUser;

                        $tempRow['user_id'] = $row->receiver->id;
                        $tempRow['name'] = $row->receiver->name;
                        $tempRow['profile'] = $row->receiver->profile;
                        $tempRow['fcm_id'] = $row->receiver->fcm_id;
                    }
                    if ($row->receiver->id == $current_user) {

                        $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                            ->where('user_id', $row->sender->id)
                            ->exists();

                        $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->sender->id)
                            ->where('user_id', $current_user)
                            ->exists();

                        $tempRow['is_blocked_by_me'] = $isBlockedByMe;
                        $tempRow['is_blocked_by_user'] = $isBlockedByUser;

                        $tempRow['user_id'] = $row->sender->id;
                        $tempRow['name'] = $row->sender->name;
                        $tempRow['profile'] = $row->sender->profile;
                        $tempRow['fcm_id'] = $row->sender->fcm_id;
                    }
                }
                $rows[] = $tempRow;
                $count++;
            }


            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");
            $response['data'] = $rows;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function getPropertiesOnMap(Request $request)
    {
        try {
            // Create reusable property mapper function
            $propertyMapper = function ($propertyData) {
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->parameters = $propertyData->parameters;
                $propertyData->is_premium = $propertyData->is_premium == 1;
                $propertyData->category->translated_name = $propertyData->category->translated_name;
                $propertyData->translated_title = $propertyData->translated_title;
                $propertyData->translated_description = $propertyData->translated_description;
                unset($propertyData->propery_type);
                return $propertyData;
            };

            // Base property query that will be reused
            $propertyQuery = Property::select(
                'id',
                'slug_id',
                'category_id',
                'city',
                'state',
                'country',
                'price',
                'propery_type',
                'title',
                'title_image',
                'is_premium',
                'rentduration',
                'latitude',
                'longitude'
            )
                ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                ->onlyActive()
                ->whereIn('propery_type', [0, 1]);

            // If Property Type Passed
            $property_type = $request->property_type;  //0 : Sell 1:Rent
            if (isset($property_type) && (!empty($property_type) || $property_type == 0)) {
                $propertyQuery = $propertyQuery->clone()->where('propery_type', $property_type);
            }

            // If Category Id is Passed
            if ($request->has('category_id') && !empty($request->category_id)) {
                $propertyQuery = $propertyQuery->clone()->where('category_id', $request->category_id);
            }

            // If parameter id passed
            if ($request->has('parameter_id') && !empty($request->parameter_id)) {
                $parametersId = explode(",", $request->parameter_id);
                $propertyQuery = $propertyQuery->clone()->whereHas('assignParameter', function ($query) use ($parametersId) {
                    $query->whereIn('parameter_id', $parametersId)->whereNotNull('value');
                });
            }

            // If Category Slug is Passed
            if ($request->has('category_slug_id') && !empty($request->category_slug_id)) {
                $categorySlugId = $request->category_slug_id;
                $propertyQuery = $propertyQuery->clone()->whereHas('category', function ($query) use ($categorySlugId) {
                    $query->where('slug_id', $categorySlugId);
                });
            }

            // If Country is passed
            if ($request->has('country') && !empty($request->country)) {
                $propertyQuery = $propertyQuery->clone()->where('country', $request->country);
            }

            // If State is passed
            if ($request->has('state') && !empty($request->state)) {
                $propertyQuery = $propertyQuery->clone()->where('state', $request->state);
            }

            // If City is passed
            $cityMapParam = $request->city ?? $request->City ?? null;
            if (!empty($cityMapParam) && !empty(trim($cityMapParam))) {
                $cleanCity = trim($cityMapParam);
                $propertyQuery = $propertyQuery->clone()->where(function ($q) use ($cleanCity) {
                    $q->where('city', 'like', '%' . $cleanCity . '%')
                      ->orWhere('address', 'like', '%' . $cleanCity . '%')
                      ->orWhere('client_address', 'like', '%' . $cleanCity . '%');
                });
            }

            // If place ID is passed, resolve it to city name
            if ($request->has('place_id') && !empty($request->place_id)) {
                $locationData = $this->resolvePlaceIdToLocation($request->place_id);
                if ($locationData) {
                    if ($locationData['city']) {
                        $propertyQuery = $propertyQuery->clone()->where('city', $locationData['city']);
                    }
                    if ($locationData['state']) {
                        $propertyQuery = $propertyQuery->clone()->where('state', $locationData['state']);
                    }
                    if ($locationData['country']) {
                        $propertyQuery = $propertyQuery->clone()->where('country', $locationData['country']);
                    }
                }
            } else {
                // Latitude and Longitude
                if ($request->has('latitude') && !empty($request->latitude) && $request->has('longitude') && !empty($request->longitude)) {
                    $propertyQuery = $propertyQuery->clone()->where('latitude', $request->latitude)->where('longitude', $request->longitude);
                }
            }


            // If Max Price And Min Price passed
            if ($request->has('min_price') && !empty($request->min_price)) {
                $minPrice = $request->min_price;
                $propertyQuery = $propertyQuery->clone()->where('price', '>=', $minPrice);
            }

            if (isset($request->max_price) && !empty($request->max_price)) {
                $maxPrice = $request->max_price;
                $propertyQuery = $propertyQuery->clone()->where('price', '<=', $maxPrice);
            }

            // If Posted Since 0 or 1 is passed
            if ($request->has('posted_since')) {
                $posted_since = $request->posted_since;

                // 0 - Last Week (from today back to the same day last week)
                if ($posted_since == 0) {
                    $oneWeekAgo = Carbon::now()->subWeek()->startOfDay();
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$oneWeekAgo, $today]);
                }
                // 1 - Yesterday
                if ($posted_since == 1) {
                    $yesterdayDate = Carbon::yesterday();
                    $propertyQuery =  $propertyQuery->clone()->whereDate('created_at', $yesterdayDate);
                }

                // 2 - Last Month
                if ($posted_since == 2) {
                    $lastMonthDate = Carbon::now()->subMonth();
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$lastMonthDate, $today]);
                }

                // 3 - Last 3 Months
                if ($posted_since == 3) {
                    $lastThreeMonthsDate = Carbon::now()->subMonths(3);
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$lastThreeMonthsDate, $today]);
                }

                // 4 - Last 6 Months
                if ($posted_since == 4) {
                    $lastSixMonthsDate = Carbon::now()->subMonths(6);
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$lastSixMonthsDate, $today]);
                }
            }

            // Search the property
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $propertyQuery = $propertyQuery->clone()->where(function ($query) use ($search) {
                    $query->where('title', 'LIKE', "%$search%")
                        ->orWhere('address', 'LIKE', "%$search%")
                        ->orWhereHas('category', function ($query1) use ($search) {
                            $query1->where('category', 'LIKE', "%$search%");
                        });
                });
            }

            // IF Promoted Passed then show the data according to
            if ($request->has('promoted') && $request->promoted == 1) {
                $propertyQuery = $propertyQuery->clone()->whereHas('advertisement', function ($query) {
                    $query->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // If get_all_premium_properties is passed then show the data according to
            if ($request->has('get_all_premium_properties') && $request->get_all_premium_properties == 1) {
                $propertyQuery = $propertyQuery->clone()->where('is_premium', 1);
            }

            // Get total properties
            $totalProperties = $propertyQuery->clone()->count();

            // If Most Viewed Passed then show the property data with Order by on Total Click Descending
            if ($request->has('most_viewed') && $request->most_viewed == 1) {
                $propertyQuery = $propertyQuery->clone()->orderBy('total_click', 'DESC');
            }
            // If Most Liked Passed then show the property data with Order by on Total Click Descending
            else if ($request->has('most_liked') && $request->most_liked == 1) {
                $propertyQuery = $propertyQuery->clone()->orderBy('favourite_count', 'DESC');
            } else {
                // If No Most Viewed or Most Liked Passed then show the property data with Order by on Id Descending
                $propertyQuery = $propertyQuery->clone()->orderBy('id', 'DESC');
            }


            // Check the city and state params and query the params according to it
            if (isset($request->city) || isset($request->state)) {
                $propertyQuery->where(function ($query) use ($request) {
                    $query->where('state', 'LIKE', "%{$request->state}%")
                        ->orWhere('city', 'LIKE', "%{$request->city}%");
                });
            }

            // Check the type params and query the params according to it
            if (isset($request->type)) {
                $propertyQuery->where('propery_type', $request->type);
            }

            // If place ID is passed, resolve it to city, state, and country
            if ($request->has('place_id') && !empty($request->place_id)) {
                $locationData = $this->resolvePlaceIdToLocation($request->place_id);
                if ($locationData) {
                    if ($locationData['city']) {
                        $propertyQuery = $propertyQuery->clone()->where('city', $locationData['city']);
                    }
                    if ($locationData['state']) {
                        $propertyQuery = $propertyQuery->clone()->where('state', $locationData['state']);
                    }
                    if ($locationData['country']) {
                        $propertyQuery = $propertyQuery->clone()->where('country', $locationData['country']);
                    }
                }
            }

            // Get Final Data
            $propertiesData = $propertyQuery->get()->map($propertyMapper);

            // Pass data as json
            if (collect($propertiesData)->isNotEmpty()) {
                ApiResponseService::successResponse("Data Fetched Successfully", $propertiesData);
            } else {
                ApiResponseService::successResponse("No Data Found", array());
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
    public function update_property_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:1,2,3',
            'property_id' => 'required|exists:propertys,id'

        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $property = Property::find($request->property_id);

            if ($property->getRawOriginal('propery_type') == 0 && $request->status != 2) {
                ApiResponseService::validationError("You can only change sell property to sold");
            } else if ($property->getRawOriginal('propery_type') == 1 && $request->status != 3) {
                ApiResponseService::validationError("You can only change rent property to rented");
            } else if ($property->getRawOriginal('propery_type') != 0 && $property->getRawOriginal('propery_type') != 1 && $property->getRawOriginal('propery_type') != 3) {
                ApiResponseService::validationError("You can only change status of sell, rent and rented properties");
            }
            $property->propery_type = $request->status;
            $property->save();
            $response['error'] = false;
            $response['message'] = trans("Data Updated Successfully");
            return response()->json($response);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
    public function getCitiesData(Request $request)
    {
        $cityImageStyle = Setting::where('type', 'city_image_style')->first();
        $cityImageStyle = $cityImageStyle->data ?? 'style_1';
        $withImage = $cityImageStyle == 'style_1' ? true : false;
        // Get Offset and Limit from payload request
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $city_arr = array();
        $citiesQuery = CityImage::where('status', 1)->withCount(['property' => function ($query) {
            $query->whereIn('propery_type', [0, 1])->onlyActive();
        }])->having('property_count', '>', 0);
        $totalData = $citiesQuery->clone()->count();
        $citiesData = $citiesQuery->clone()->orderByDesc('property_count')->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();
        foreach ($citiesData as $city) {
            if (!empty($city->getRawOriginal('image'))) {
                $url = $city->image;
                $relativePath = parse_url($url, PHP_URL_PATH);
                if (file_exists(public_path()  . $relativePath)) {
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
        $response['message'] = trans("Data Fetched Successfully");

        return response()->json($response);
    }

    public function get_facilities(Request $request)
    {
        $facilities = OutdoorFacilities::with('translations');

        // if (isset($request->search) && !empty($request->search)) {
        //     $search = $request->search;
        //     $facilities->where('category', 'LIKE', "%$search%");
        // }

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $facilities->where('id', '=', $id);
        }
        $total = $facilities->clone()->count();
        $result = $facilities->clone()->get()->map(function ($facility) {
            $facility->translated_name = $facility->translated_name;
            return $facility;
        });


        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function get_report_reasons(Request $request)
    {
        $reportReasonQuery = report_reasons::with('translations');

        if (isset($request->id) && !empty($request->id)) {
            $id = $request->id;
            $reportReasonQuery->where('id', $id);
        }
        $total = $reportReasonQuery->clone()->count();
        $result = $reportReasonQuery->clone()->get()->map(function ($reportReason) {
            $reportReason->translated_reason = $reportReason->translated_reason;
            return $reportReason;
        });

        if (!$result->isEmpty()) {
            $response['error'] = false;
            $response['message'] = trans("Data Fetched Successfully");

            $response['total'] = $total;
            $response['data'] = $result;
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return response()->json($response);
    }
    public function add_reports(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason_id' => 'required',
            'property_id' => 'required',
        ]);
        $current_user = Auth::user()->id;
        if (!$validator->fails()) {
            $report_count = user_reports::where('property_id', $request->property_id)->where('customer_id', $current_user)->get();
            if (!count($report_count)) {
                $report_reason = new user_reports();
                $report_reason->reason_id = $request->reason_id ? $request->reason_id : 0;
                $report_reason->property_id = $request->property_id;
                $report_reason->customer_id = $current_user;
                $report_reason->other_message = $request->other_message ? $request->other_message : '';



                $report_reason->save();


                $response['error'] = false;
                $response['message'] = trans("Report Submitted Successfully");
            } else {
                $response['error'] = false;
                $response['message'] = trans("Already Reported");
            }
        } else {
            $response['error'] = true;
            $response['message'] = trans("Please Fill All Data And Submit");
        }
        return response()->json($response);
    }
    public function delete_chat_message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            // Get Customer IDs

            // Get FCM IDs
            $fcmId = Usertokens::select('fcm_id')->where('customer_id', $request->receiver_id)->pluck('fcm_id')->toArray();

            if (isset($request->message_id)) {
                $chat = Chats::find($request->message_id);
                if ($chat) {
                    if (!empty($fcmId)) {
                        $registrationIDs = array_filter($fcmId);
                        $fcmMsg = array(
                            'title' => "Delete Chat Message",
                            'message' => "Message Deleted Successfully",
                            "image" => '',
                            'type' => 'delete_message',
                            'message_id' => $request->message_id,
                            'body' => "Message Deleted Successfully",
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',

                        );

                        file_put_contents(storage_path('logs/laravel.log'), "ApiController: delete_chat_message (single) called at " . now() . "\n", FILE_APPEND);
                        send_push_notification($registrationIDs, $fcmMsg);
                    }
                    $chat->delete();
                    ApiResponseService::successResponse("Message Deleted Successfully");
                } else {
                    ApiResponseService::successResponse("No Data Found");
                }
            } else if (isset($request->sender_id) && isset($request->receiver_id) && isset($request->property_id)) {

                $userChat = Chats::where('property_id', $request->property_id)->where(function ($query) use ($request) {
                    $query->where(function ($query) use ($request) {
                        $query->where('sender_id', $request->sender_id)
                            ->orWhere('receiver_id', $request->receiver_id);
                    })->orWhere(function ($query) use ($request) {
                        $query->where('sender_id', $request->receiver_id)
                            ->orWhere('receiver_id', $request->sender_id);
                    });
                });

                if (collect($userChat->clone()->get())->isNotEmpty()) {
                    $userChat->delete();
                    $registrationIDs = array_filter($fcmId);
                    $fcmMsg = array(
                        'title' => "Chat Messages Deleted",
                        'message' => "Chat Messages Deleted Successfully",
                        "image" => '',
                        'type' => 'chat_message_deleted',
                        'body' => "Chat Messages Deleted Successfully",
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',

                    );
                    send_push_notification($registrationIDs, $fcmMsg);
                    ApiResponseService::successResponse("Chat Deleted Successfully");
                } else {
                    ApiResponseService::successResponse("No Data Found");
                }
            } else {
                ApiResponseService::successResponse("No Data Found");
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
    public function get_user_recommendation(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $current_user = Auth::user()->id;


        $user_interest = UserInterest::where('user_id', $current_user)->first();
        if (collect($user_interest)->isNotEmpty()) {

            $property = Property::with('customer')->with('user')->with('category:id,category,image', 'category.translations')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->onlyActive();


            $property_type = $request->property_type;
            if ($user_interest->category_ids != '') {

                $category_ids = explode(',', $user_interest->category_ids);

                $property = $property->whereIn('category_id', $category_ids);
            }

            if ($user_interest->price_range != '') {

                $max_price = explode(',', $user_interest->price_range)[1];

                $min_price = explode(',', $user_interest->price_range)[0];

                if (isset($max_price) && isset($min_price)) {
                    $min_price = floatval($min_price);
                    $max_price = floatval($max_price);

                    $property = $property->where(function ($query) use ($min_price, $max_price) {
                        $query->whereRaw("CAST(price AS DECIMAL(10, 2)) >= ?", [$min_price])
                            ->whereRaw("CAST(price AS DECIMAL(10, 2)) <= ?", [$max_price]);
                    });
                }
            }


            if ($user_interest->city != '') {
                $city = $user_interest->city;
                $property = $property->where('city', $city);
            }
            if ($user_interest->property_type != '') {
                $property_type = explode(',',  $user_interest->property_type);
            }
            if ($user_interest->outdoor_facilitiy_ids != '') {


                $outdoor_facilitiy_ids = explode(',', $user_interest->outdoor_facilitiy_ids);
                $property = $property->whereHas('assignfacilities.outdoorfacilities', function ($q) use ($outdoor_facilitiy_ids) {
                    $q->whereIn('id', $outdoor_facilitiy_ids);
                });
            }



            if (isset($property_type)) {
                if (count($property_type) == 2) {
                    $property_type = $property->where(function ($query) use ($property_type) {
                        $query->where('propery_type', $property_type[0])->orWhere('propery_type', $property_type[1]);
                    });
                } else {
                    if (isset($property_type[0])  &&  $property_type[0] == 0) {

                        $property = $property->where('propery_type', $property_type[0]);
                    }
                    if (isset($property_type[0])  &&  $property_type[0] == 1) {

                        $property = $property->where('propery_type', $property_type[0]);
                    }
                }
            }



            $total = $property->get()->count();

            $result = $property->skip($offset)->take($limit)->get()->map(function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                return $item;
            });
            $property_details = get_property_details($result, $current_user, true);

            if (!empty($result)) {
                $response['error'] = false;
                $response['message'] = trans("Data Fetched Successfully");
                $response['total'] = $total;
                $response['data'] = $property_details;
            } else {

                $response['error'] = false;
                $response['message'] = trans("No Data Found");
                $response['data'] = [];
            }
        } else {
            $response['error'] = false;
            $response['message'] = trans("No Data Found");
            $response['data'] = [];
        }
        return ($response);
    }
    public function contactUs(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'subject' => 'required',
            'message' => 'required',
        ]);

        if (!$validator->fails()) {

            $contactrequest = new Contactrequests();
            $contactrequest->first_name = $request->first_name;
            $contactrequest->last_name = $request->last_name;
            $contactrequest->email = $request->email;
            $contactrequest->subject = $request->subject;
            $contactrequest->message = $request->message;
            $contactrequest->save();
            $response['error'] = false;
            $response['message'] = trans("Contact Request Send Successfully");
        } else {


            $response['error'] = true;
            $response['message'] =  $validator->errors()->first();
        }
        return response()->json($response);
    }
    public function delete_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:propertys,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            Property::findOrFail($request->id)->delete();
            $response['error'] = false;
            $response['message'] = trans("Property Deleted Successfully");
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }
    public function assign_package(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required_without:pay_as_you_go_id|exists:packages,id',
            'pay_as_you_go_id' => 'required_without:package_id|exists:pay_as_you_gos,id',
            'product_id' => 'required_if:in_app,true',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $loggedInUserId = Auth::user()->id;

            $payAsYouGo = null;
            $package = null;

            if ($request->in_app == 'true' || $request->in_app === true) {
                $payAsYouGo = \App\Models\PayAsYouGo::where('ios_product_id', $request->product_id)->first();
                if (!$payAsYouGo) {
                    $package = Package::where('ios_product_id', $request->product_id)->first();
                }
            } else {
                if ($request->has('pay_as_you_go_id') && !empty($request->pay_as_you_go_id)) {
                    $payAsYouGo = \App\Models\PayAsYouGo::where('id', $request->pay_as_you_go_id)->first();
                    // if ($payAsYouGo->price > 0) {
                    //     ApiResponseService::validationError("Pay As You Go package is paid cannot assign directly");
                    // }
                } else {
                    $package = Package::where('id', $request->package_id)->first();
                    if ($package->package_type == 'paid') {
                        ApiResponseService::validationError("Package is paid cannot assign directly");
                    }
                }
            }
            if ($package) {
                // Check if user already has an active package
                $isAllFeatureLimitExits = $this->checkPackageLimitExists($loggedInUserId, $package->id);
                if ($isAllFeatureLimitExits == true) {
                    ApiResponseService::validationError("same package purchase in past have all features limits available");
                }

                // Check if package is one_time and user already purchased it
                if ($package->purchase_type == 'one_time' && $this->checkUserPurchasedPackage($loggedInUserId, $package->id)) {
                    ApiResponseService::validationError("This package can only be purchased once");
                }
            }

            if (collect($payAsYouGo)->isNotEmpty()) {
                DB::beginTransaction();

                // Create Payment Transaction log first
                $paymentTransaction = PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'pay_as_you_go_id' => $payAsYouGo->id,
                    'amount' => 0, // Assigned directly or via in app
                    'payment_gateway' => ($request->in_app == 'true' || $request->in_app === true) ? 'in_app' : null,
                    'payment_type' => ($request->in_app == 'true' || $request->in_app === true) ? 'in app purchase' : 'free',
                    'payment_status' => 'success',
                    'order_id' => Str::uuid(),
                    'transaction_id' => Str::uuid()
                ]);

                // Assign Pay As You Go Credit to user
                \App\Models\UserPayAsYouGoCredit::create([
                    'user_id' => $loggedInUserId,
                    'pay_as_you_go_id' => $payAsYouGo->id,
                    'payment_transaction_id' => $paymentTransaction->id,
                    'used' => 0
                ]);
                DB::commit();
                ApiResponseService::successResponse("Pay As You Go Package Assigned Successfully");

            } else if (collect($package)->isNotEmpty()) {
                DB::beginTransaction();
                // Assign Package to user
                $userPackage = UserPackage::create([
                    'package_id'  => $package->id,
                    'user_id'     => $loggedInUserId,
                    'start_date'  => Carbon::now(),
                    'end_date'    => $package->package_type == "unlimited" ? null : Carbon::now()->addHours($package->duration),
                ]);

                // Create Payment Transaction
                PaymentTransaction::create([
                    'user_id' => $loggedInUserId,
                    'package_id' => $package->id,
                    'amount' => 0,
                    'payment_gateway' => ($request->in_app == 'true' || $request->in_app === true) ? 'in_app' : null,
                    'payment_type' => ($request->in_app == 'true' || $request->in_app === true) ? 'in app purchase' : 'free',
                    'payment_status' => 'success',
                    'order_id' => Str::uuid(),
                    'transaction_id' => Str::uuid()
                ]);

                // Assign limited count feature to user with limits
                $packageFeatures = PackageFeature::where(['package_id' => $package->id, 'limit_type' => 'limited'])->get();
                if (collect($packageFeatures)->isNotEmpty()) {
                    $userPackageLimitData = array();
                    foreach ($packageFeatures as $key => $feature) {
                        $userPackageLimitData[] = array(
                            'user_package_id' => $userPackage->id,
                            'package_feature_id' => $feature->id,
                            'total_limit' => $feature->limit,
                            'used_limit' => 0,
                            'created_at' => now(),
                            'updated_at' => now()
                        );
                    }

                    if (!empty($userPackageLimitData)) {
                        UserPackageLimit::insert($userPackageLimitData);
                    }
                }
                DB::commit();
                ApiResponseService::successResponse("Package Purchased Successfully");
            } else {
                ApiResponseService::validationError("Package not found");
            }
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Assign Package Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            ApiResponseService::errorResponse();
        }
    }

    public function get_app_settings(Request $request)
    {
        $result =  Setting::select('type', 'data')->whereIn('type', ['app_home_screen', 'placeholder_logo', 'light_tertiary', 'light_secondary', 'light_primary', 'dark_tertiary', 'dark_secondary', 'dark_primary'])->get();


        $tempRow = [];

        if (($request->user_id) != "") {
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

            if ($row->type == 'app_home_screen' || $row->type == "placeholder_logo") {

                $tempRow[$row->type] = url('/assets/images/logo/') . '/' . $row->data;
            }
        }

        $response['error'] = false;
        $response['data'] = $tempRow;
        return response()->json($response);
    }
    public function get_seo_settings(Request $request)
    {
        try {
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            $seoSettingQuery = SeoSettings::select('id', 'page', 'image', 'title', 'description', 'keywords', 'schema_markup')
                ->when($request->page, function ($query) use ($request) {
                    $query->where('page', 'LIKE', "%$request->page%");
                })
                ->when(!$request->page, function ($query) {
                    $query->where('page', 'LIKE', "%homepage%");
                });

            $total = $seoSettingQuery->clone()->count();
            $result = $seoSettingQuery->skip($offset)->take($limit)->get();

            if (collect($result)->isNotEmpty()) {
                ApiResponseService::successResponse("Data Fetched Successfully", $result, ['total' => $total]);
            } else {
                ApiResponseService::successResponse("No Data Found");
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
    public function getInterestedUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'property_id' => 'required_without:slug_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->errors()->first(),
                ]);
            }

            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            if (isset($request->slug_id)) {
                $property = Property::where('slug_id', $request->slug_id)->first();
                $property_id = $property->id;
            } else {
                $property_id = $request->property_id;
            }

            $interestedUserQuery = InterestedUser::has('customer')->with('customer:id,name,profile,email,mobile')->where('property_id', $property_id);
            $totalData = $interestedUserQuery->clone()->count();
            $interestedData = $interestedUserQuery->take($limit)->skip($offset)->get()->map(function ($interestedData) {
                if (env('DEMO_MODE') && Auth::check() != false && Auth::user()->email != 'superadmin@gmail.com') {
                    $interestedData->customer->email = "****************************";
                }
                return $interestedData;
            });
            if (collect($interestedData)->isNotEmpty()) {
                $data = $interestedData->pluck('customer');
                ApiResponseService::successResponse("Data Fetched Successfully", $data, ['total' => $totalData]);
            } else {
                ApiResponseService::successResponse("No Data Found");
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
    public function post_project(Request $request)
    {
        $videoRules = [
            'video_type'        => 'nullable|required_with:video_link,custom_video|in:0,1,2',
            'video_link'        => ['nullable', 'required_if:video_type,1,2', function ($attribute, $value, $fail) use ($request) {
                if (in_array($request->video_type, [1, 2])) {
                    $youtubePattern = '/^(https?\:\/\/)?(www\.youtube\.com|youtu\.be)\/.+$/';
                    $vimeoPattern   = '/^(https?:\/\/)?(www\.)?vimeo\.com\/[0-9]+([?#][^\s]*)?$/';

                    if ($request->video_type == 1 && !preg_match($youtubePattern, $value)) {
                        return $fail("The Video Link must be a valid YouTube URL.");
                    }
                    if ($request->video_type == 2 && !preg_match($vimeoPattern, $value)) {
                        return $fail("The Video Link must be a valid Vimeo URL.");
                    }

                    if ($request->video_type == 1 && strpos($value, 'youtu.be') !== false) {
                        $value = 'https://www.youtube.com/watch?v=' . substr(parse_url($value, PHP_URL_PATH), 1);
                    }

                    $headers = @get_headers($value);
                    if (!$headers || strpos($headers[0], '200') === false) {
                        return $fail("The Video Link must be accessible.");
                    }
                }
            }],
            'custom_video'      => 'nullable|file|mimes:mp4,webm,ogg|max:20480|required_if:video_type,0',
        ];

        if ($request->has('id')) {
            $validator = Validator::make($request->all(), array_merge([
                'title' => 'required',
            ], $videoRules));
        } else {
            $validator = Validator::make($request->all(), array_merge([
                'title'         => 'required',
                'description'   => 'required',
                'image'         => 'required|file|max:3000|mimes:jpeg,png,jpg,webp',
                'category_id'   => 'required',
                'city'          => 'required',
                'state'         => 'required',
                'country'       => 'required',
                'translations.*.title.translation_id' => 'nullable|exists:translations,id',
                'translations.*.title.language_id' => 'nullable|exists:languages,id',
                'translations.*.title.value' => 'nullable',
                'translations.*.description.translation_id' => 'nullable|exists:translations,id',
                'translations.*.description.language_id' => 'nullable|exists:languages,id',
                'translations.*.description.value' => 'nullable',
            ], $videoRules));
        }
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            DB::beginTransaction();
            $isPayAsYouGo = false;
            if (!$request->id) {
                $limitResult = HelperService::updatePackageLimit('project_list', false, true);
                $isPayAsYouGo = ($limitResult === 'pay_as_you_go');
            }
            $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;

            $currentUserId = Auth::user()->id;
            if (!(isset($request->id))) {
                $project = new Projects();

                // Check the auto approve and verified user status and make project auto enable or disable
                $autoApproveStatus = $this->getAutoApproveStatus($currentUserId);
                if ($autoApproveStatus) {
                    $project->status = 1;
                    $project->request_status = 'approved';
                } else {
                    $project->status = 0;
                    $project->request_status = 'pending';
                }

                if ($isPayAsYouGo) {
                    $project->expiry_date = Carbon::now()->addDays(30);
                } elseif ($autoApproveStatus) {
                    $project->expiry_date = HelperService::calculateExpirationDate($currentUserId);
                }

                $project->is_premium = isset($request->is_premium) ? ($request->is_premium == "true" ? 1 : 0) : 0;
            } else {
                $project = Projects::where('added_by', $currentUserId)->find($request->id);
                if (!$project) {
                    $response['error'] = false;
                    $response['message'] = trans("Project Not Found");
                    return response()->json($response);
                }
                $autoApproveStatus = $this->getAutoApproveStatus($currentUserId);
                if (!$autoApproveStatus) {
                    if (HelperService::getSettingData('auto_approve_edited_listings') == 0) {
                        $project->request_status = 'pending';
                    }
                }
            }

            // is_premium check
            $showPremiumToggle = system_setting('show_premium_toggle');
            if ($showPremiumToggle == 1) {
                $project->is_premium = isset($request->is_premium) ? ($request->is_premium) : 0;
            } else {
                $project->is_premium = 0;
            }

            if ($request->category_id) {
                $project->category_id = $request->category_id;
            }
            if ($request->description) {
                $project->description = $request->description;
            }
            if ($request->location) {
                $project->location = $request->location;
            }
            // Meta details
            $project->meta_title = isset($request->meta_title) && !empty($request->meta_title) ? $request->meta_title : null;
            $project->meta_description = isset($request->meta_description) && !empty($request->meta_description) ? $request->meta_description : null;
            $project->meta_keywords = isset($request->meta_keywords) && !empty($request->meta_keywords) ? $request->meta_keywords : null;

            $project->added_by = $currentUserId;
            if ($request->country) {
                $project->country = $request->country;
            }
            if ($request->state) {
                $project->state = $request->state;
            }
            if ($request->city) {
                $project->city = $request->city;
            }
            if ($request->latitude) {
                $project->latitude = $request->latitude;
            }
            if ($request->longitude) {
                $project->longitude = $request->longitude;
            }
            // Video Logic
            if ($request->has('remove_video') && $request->remove_video == 1) {
                if ($project->video_type == Projects::VIDEO_CUSTOM && !empty($project->getRawOriginal('video_link'))) {
                    FileService::delete(config('global.PROJECT_VIDEO_PATH'), $project->getRawOriginal('video_link'));
                }
                $project->video_type = null;
                $project->video_link = null;
            } else {
                $videoType = $request->video_type;
                $directUploadEnabled = HelperService::getSettingData('show_direct_video_upload');

                if ($videoType !== null && (int)$videoType === Projects::VIDEO_CUSTOM && (int)$directUploadEnabled !== 1) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Direct video upload is currently disabled by admin.'
                    ]);
                }

                $project->video_type = $videoType;
                if ($videoType !== null && (int)$videoType === Projects::VIDEO_CUSTOM && $request->hasFile('custom_video')) {
                    $file = $request->file('custom_video');
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = config('global.PROJECT_VIDEO_PATH');
                        if (isset($request->id) && $project->getRawOriginal('video_link')) {
                            $project->video_link = FileService::compressAndReplace($file, $path, $project->getRawOriginal('video_link'));
                        } else {
                            $project->video_link = FileService::compressAndUpload($file, $path);
                        }
                    } else {
                        Log::error('custom_video is not an instance of UploadedFile', ['value' => $file]);
                    }
                } else if (isset($request->video_link)) {
                    $project->video_link = $request->video_link;
                }
            }
            if ($request->type) {
                $project->type = $request->type;
            }
            if ($request->id) {
                if ($project->title !== $request->title) {
                    $title = !empty($request->title) ? $request->title : $project->title;
                    $project->title = $title;
                } else {
                    $title = $request->title;
                    $project->title = $title;
                }
                $project->slug_id = generateUniqueSlug($slugData, 4, null, $request->id);
                if ($request->hasFile('image')) {
                    $path = config('global.PROJECT_TITLE_IMG_PATH');
                    $rawImage = $project->getRawOriginal('image');
                    $project->image = FileService::compressAndReplace($request->file('image'), $path, $rawImage, true);
                }

                if ($request->has('remove_meta_image') && $request->remove_meta_image == 1) {
                    if (!empty($project->meta_image)) {
                        $file = $project->getRawOriginal('meta_image');
                        FileService::delete(config('global.PROJECT_SEO_IMG_PATH'), $file);
                    }
                    $project->meta_image = null;
                }

                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROJECT_SEO_IMG_PATH');
                    $rawImage = $project->getRawOriginal('meta_image');
                    $project->meta_image = FileService::compressAndReplace($request->file('meta_image'), $path, $rawImage);
                }
            } else {
                $project->title = $request->title;
                if ($request->hasFile('image')) {
                    $path = config('global.PROJECT_TITLE_IMG_PATH');
                    $project->image = FileService::compressAndUpload($request->file('image'), $path, true);
                }
                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROJECT_SEO_IMG_PATH');
                    $project->meta_image = FileService::compressAndUpload($request->file('meta_image'), $path);
                }
                $title = $request->title;
                $project->slug_id = generateUniqueSlug($slugData, 4);
            }

            $project->save();

            // Link payment transaction to project (pay-as-you-go only, new projects)
            if ($isPayAsYouGo && HelperService::$lastConsumedPaymentTransactionId) {
                PaymentTransaction::where('id', HelperService::$lastConsumedPaymentTransactionId)
                    ->update(['project_id' => $project->id]);
            }

            if ($request->remove_gallery_images) {
                $removeGalleryImagesIds = explode(',', $request->remove_gallery_images);
                $galleryImagesQuery = ProjectDocuments::whereIn('id', $removeGalleryImagesIds);
                $galleryImagesData = $galleryImagesQuery->get();
                if (collect($galleryImagesData)->isNotEmpty()) {
                    foreach ($galleryImagesData as $row) {
                        $file = $row->getRawOriginal('name');
                        $path = config('global.PROJECT_DOCUMENT_PATH');
                        FileService::delete($path, $file);
                    }
                    $galleryImagesQuery->delete();
                }
            }

            if ($request->remove_documents) {
                $removeDocumentsIds = explode(',', $request->remove_documents);
                $documentsQuery = ProjectDocuments::whereIn('id', $removeDocumentsIds);
                $documentsData = $documentsQuery->get();
                if (collect($documentsData)->isNotEmpty()) {
                    foreach ($documentsData as $row) {
                        $file = $row->getRawOriginal('name');
                        $path = config('global.PROJECT_DOCUMENT_PATH');
                        FileService::delete($path, $file);
                    }
                    $documentsQuery->delete();
                }
            }

            if ($request->hasfile('gallery_images')) {
                $galleryImagesData = array();
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImagesData[] = array(
                        'project_id' => $project->id,
                        'name' => FileService::compressAndUpload($file, config('global.PROJECT_DOCUMENT_PATH'), true),
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if (collect($galleryImagesData)->isNotEmpty()) {
                    ProjectDocuments::insert($galleryImagesData);
                }
            }

            if ($request->hasfile('documents')) {
                $documentsData = array();
                foreach ($request->file('documents') as $file) {
                    $documentsData[] = array(
                        'project_id' => $project->id,
                        'name' => FileService::compressAndUpload($file, config('global.PROJECT_DOCUMENT_PATH')),
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if (collect($documentsData)->isNotEmpty()) {
                    ProjectDocuments::insert($documentsData);
                }
            }

            if (!empty($request->plans)) {

                $path = config('global.PROJECT_DOCUMENT_PATH');
                $planIds = collect($request->plans)->pluck('id')->filter()->all();

                // Preload existing plans in one query
                $existingPlans = ProjectPlans::whereIn('id', $planIds)->get()->keyBy('id');

                foreach ($request->plans as $planData) {

                    // Use existing plan or new instance
                    $projectPlan = $existingPlans->get($planData['id'] ?? null) ?? new ProjectPlans();

                    // Handle document upload if present
                    if (!empty($planData['document'])) {
                        $oldFile = $projectPlan->getRawOriginal('document');
                        $projectPlan->document = FileService::compressAndReplace($planData['document'], $path, $oldFile, true);
                    }

                    // Fill common fields
                    $projectPlan->fill([
                        'title' => $planData['title'] ?? '',
                        'project_id' => $project->id,
                    ]);

                    $projectPlan->save();
                }
            }



            if (!empty($request->remove_plans)) {
                $removePlanIds = array_filter(explode(',', $request->remove_plans));
                if (!empty($removePlanIds)) {
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    // Fetch all plans in one query
                    $plans = ProjectPlans::whereIn('id', $removePlanIds)->get();
                    // Delete associated files
                    foreach ($plans as $plan) {
                        $file = $plan->getRawOriginal('document');
                        if ($file) {
                            FileService::delete($path, $file);
                        }
                    }
                    // Delete all plans from DB in a single query
                    ProjectPlans::whereIn('id', $removePlanIds)->delete();
                }
            }


            // START ::Add Translations
            if (isset($request->translations) && !empty($request->translations)) {
                $translationData = array();
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = array(
                            'id'                => $value['translation_id'] ?? null,
                            'translatable_id'   => $project->id,
                            'translatable_type' => 'App\Models\Projects',
                            'language_id'       => $value['language_id'],
                            'key'               => $key,
                            'value'             => $value['value'],
                        );
                    }
                }
                if (!empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }
            $result = Projects::with('customer')->with('gallary_images')->with('documents')->with('plans')->with('category:id,category,image')->where('id', $project->id)->get();

            DB::commit();
            $response['error'] = false;
            $response['message'] = isset($request->id) ? trans("Project Updated Successfully") : trans("Project Posted Successfully");
            $response['data'] = $result;
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }
    public function delete_project(Request $request)
    {
        $current_user = Auth::user()->id;

        $validator = Validator::make($request->all(), [

            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        $project = Projects::where('added_by', $current_user)->with('gallary_images')->with('documents')->with('plans')->find($request->id);

        if ($project) {
            if ($project->title_image != '') {
                $path = config('global.PROJECT_TITLE_IMG_PATH');
                FileService::clearCachedBlurImageUrl('blur_project_title_image_' . $project->id);
                FileService::delete($path, $project->getRawOriginal('image'));
            }
            if (collect($project->gallary_images)->isNotEmpty()) {
                foreach ($project->gallary_images as $row) {
                    $file = $row->getRawOriginal('name');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->gallary_images()->delete();
            }

            if (collect($project->documents)->isNotEmpty()) {
                foreach ($project->documents as $row) {
                    $file = $row->getRawOriginal('name');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->documents()->delete();
            }
            if (collect($project->plans)->isNotEmpty()) {
                foreach ($project->plans as $row) {
                    $file = $row->getRawOriginal('document');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->plans()->delete();
            }
            $project->delete();
            $response['error'] = false;
            $response['message'] = trans("Project Deleted Successfully");
        } else {
            $response['error'] = true;
            $response['message'] = trans("No Data Found");
        }
        return response()->json($response);
    }

    public function renew_listing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:property,project',
            'id'   => 'required|integer',
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

            if (!$listing) {
                return ApiResponseService::errorResponse("Listing not found");
            }

            // 2. Check if actually expired
            if ($listing->expiry_date === null || Carbon::parse($listing->expiry_date)->isFuture()) {
                return ApiResponseService::errorResponse("Listing is not expired");
            }

            // 3. Consume package slot (same as creating new listing)
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

            return ApiResponseService::successResponse("Listing Renewed Successfully");
        } catch (Exception $e) {
            return ApiResponseService::errorResponse("Something went wrong");
        }
    }

    public function getUserPersonalisedInterest(Request $request)
    {
        try {
            // Get Current User's ID From Token
            $loggedInUserId = Auth::user()->id;
            $data = array();

            // Get User Interest Data on the basis of current User
            $userInterest = UserInterest::where('user_id', $loggedInUserId)->first();
            if (collect($userInterest)->isNotEmpty()) {
                // Get Data
                $categoriesIds = !empty($userInterest->category_ids) ? explode(',', $userInterest->category_ids) : '';
                $priceRange = $userInterest->property_type != null ? explode(',', $userInterest->price_range) : '';
                $propertyType = $userInterest->property_type == 0 || $userInterest->property_type == 1 ? explode(',', $userInterest->property_type) : '';
                $outdoorFacilitiesIds = !empty($userInterest->outdoor_facilitiy_ids) ? explode(',', $userInterest->outdoor_facilitiy_ids) : '';
                $city = !empty($userInterest->city) ?  $userInterest->city : '';
                // Custom Data Array
                $data = array(
                    'user_id'               => $loggedInUserId,
                    'category_ids'          => $categoriesIds,
                    'price_range'           => $priceRange,
                    'property_type'         => $propertyType,
                    'outdoor_facilitiy_ids' => $outdoorFacilitiesIds,
                    'city'                  => $city,
                );
            }
            $response = array(
                'error' => false,
                'data' => $data,
                'message' => trans("Data Fetched Successfully")
            );


            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }

    public function storeUserPersonalisedInterest(Request $request)
    {
        try {
            DB::beginTransaction();
            // Get Current User's ID From Token
            $loggedInUserId = Auth::user()->id;

            // Get User Interest
            $userInterest = UserInterest::where('user_id', $loggedInUserId)->first();

            // If data Exists then update or else insert new data
            if (collect($userInterest)->isNotEmpty()) {
                $response['error'] = false;
                $response['message'] = trans("Data Updated Successfully");
            } else {
                $userInterest = new UserInterest();
                $response['error'] = false;
                $response['message'] = trans("Data Submitted Successfully");
            }

            // Change the values
            $userInterest->user_id = $loggedInUserId;
            $userInterest->category_ids = (isset($request->category_ids) && !empty($request->category_ids)) ? $request->category_ids : "";
            $userInterest->outdoor_facilitiy_ids = (isset($request->outdoor_facilitiy_ids) && !empty($request->outdoor_facilitiy_ids)) ? $request->outdoor_facilitiy_ids : null;
            $userInterest->price_range = (isset($request->price_range) && !empty($request->price_range)) ? $request->price_range : "";
            $userInterest->city = (isset($request->city) && !empty($request->city)) ? $request->city : "";
            $userInterest->property_type = isset($request->property_type) && ($request->property_type == 0 || $request->property_type == 1) ? $request->property_type : "0,1";
            $userInterest->save();

            DB::commit();

            // Get Datas
            $categoriesIds = !empty($userInterest->category_ids) ? explode(',', $userInterest->category_ids) : '';
            $priceRange = !empty($userInterest->price_range) ? explode(',', $userInterest->price_range) : '';
            $propertyType = explode(',', $userInterest->property_type);
            $outdoorFacilitiesIds = !empty($userInterest->outdoor_facilitiy_ids) ? explode(',', $userInterest->outdoor_facilitiy_ids) : '';
            $city = !empty($userInterest->city) ?  $userInterest->city : '';

            // Custom Data Array
            $data = array(
                'user_id'               => $userInterest->user_id,
                'category_ids'          => $categoriesIds,
                'price_range'           => $priceRange,
                'property_type'         => $propertyType,
                'outdoor_facilitiy_ids' => $outdoorFacilitiesIds,
                'city'                  => $city,
            );
            $response['data'] = $data;
            $response['message'] = trans("Data Fetched Successfully");
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }

    public function deleteUserPersonalisedInterest(Request $request)
    {
        try {
            DB::beginTransaction();
            // Get Current User From Token
            $loggedInUserId = Auth::user()->id;

            // Get User Interest
            UserInterest::where('user_id', $loggedInUserId)->delete();
            DB::commit();
            $response = array(
                'error' => false,
                'message' => trans("Data Deleted Successfully")
            );
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }

    public function removeAllPackages(Request $request)
    {
        try {
            DB::beginTransaction();

            $loggedInUserId = Auth::user()->id;

            // Cannot directly delete the payment transaction and user package because it has foreign key constraint
            $paymentTransaction = PaymentTransaction::where('user_id', $loggedInUserId)->get();
            foreach ($paymentTransaction as $transaction) {
                $transaction->bank_receipt_files()->delete();
                $transaction->delete();
            }
            $userPackage = UserPackage::where('user_id', $loggedInUserId)->get();
            foreach ($userPackage as $package) {
                $package->delete();
            }

            DB::commit();
            $response = array(
                'error' => false,
                'message' => trans("Data Deleted Successfully")
            );
            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }


    public function getAddedProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_type' => 'nullable|in:0,1,2,3',
            'request_status' => 'nullable|in:approved,rejected,pending,expired',
            'is_promoted' => 'nullable|in:1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Get Logged In User data
            $loggedInUserData = Auth::user();
            // Get Current Logged In User ID
            $loggedInUserID = $loggedInUserData->id;

            // when is_promoted is passed then show only property who has been featured (advertised)
            if ($request->has('is_promoted') && $request->is_promoted == 1) {
                // Create Advertisement Query which has Property Data
                $advertisementQuery = Advertisement::whereHas('property', function ($query) use ($loggedInUserID) {
                    $query->where(['post_type' => 1, 'added_by' => $loggedInUserID]);
                })->with('property:id,category_id,slug_id,title,propery_type,city,state,country,price,title_image', 'property.category:id,category,image');

                // Get Total Advertisement Data
                $advertisementTotal = $advertisementQuery->clone()->count();

                // Get Advertisement Data with custom Data
                $advertisementData = $advertisementQuery->clone()->skip($offset)->take($limit)->orderBy('id', 'DESC')->get()->map(function ($advertisement) {
                    if (collect($advertisement->property)->isNotEmpty()) {
                        $otherData = array();
                        $otherData['id'] = $advertisement->property->id;
                        $otherData['slug_id'] = $advertisement->property->slug_id;
                        $otherData['property_type'] = $advertisement->property->propery_type;
                        $otherData['title'] = $advertisement->property->title;
                        $otherData['city'] = $advertisement->property->city;
                        $otherData['state'] = $advertisement->property->state;
                        $otherData['country'] = $advertisement->property->country;
                        $otherData['price'] = $advertisement->property->price;
                        $otherData['title_image'] = $advertisement->property->title_image;
                        $otherData['advertisement_id'] = $advertisement->id;
                        $otherData['advertisement_status'] = $advertisement->status;
                        $otherData['advertisement_type'] = $advertisement->type;
                        $otherData['category'] = $advertisement->property->category;
                        unset($advertisement); // remove the original data
                        return $otherData; // return custom created data
                    }
                });
                $response = array(
                    'error' => false,
                    'data' => $advertisementData,
                    'total' => $advertisementTotal,
                    'message' => trans("Data Fetched Successfully")
                );
            } else {
                // Check the property's post is done by customer and added by logged in user
                $propertyQuery = Property::where(['post_type' => 1, 'added_by' => $loggedInUserID])
                    // When property type is passed in payload show data according property type that is sell or rent
                    ->when($request->filled('property_type'), function ($query) use ($request) {
                        return $query->where('propery_type', $request->property_type);
                    })
                    ->when($request->filled('id'), function ($query) use ($request) {
                        return $query->where('id', $request->id);
                    })
                    ->when($request->filled('slug_id'), function ($query) use ($request) {
                        return $query->where('slug_id', $request->slug_id);
                    })
                    ->when($request->filled('status'), function ($query) use ($request) {
                        // IF Status is passed and status has active (1) or deactive (0) or both
                        $statusData = explode(',', $request->status);
                        return $query->whereIn('status', $statusData)->where('request_status', 'approved');
                    })
                    ->when($request->filled('request_status'), function ($query) use ($request) {
                        // IF Request Status is passed and status has approved or rejected or pending or expired
                        if ($request->request_status == 'expired') {
                            return $query->whereNotNull('expiry_date')->where('expiry_date', '<', now());
                        }
                        return $query->where('request_status', $request->request_status);
                    })

                    // Pass the Property Data with Category and Advertisement Relation Data
                    ->with('category.translations', 'advertisement', 'interested_users:id,property_id,customer_id', 'interested_users.customer:id,name,profile', 'translations');

                // Get Total Views by Sum of total click of each property
                $totalViews = $propertyQuery->sum('total_click');

                // Get total properties
                $totalProperties = $propertyQuery->count();

                // Get the property data with extra data and changes :- is_premium, post_created and promoted
                $propertyData = $propertyQuery->skip($offset)->take($limit)->orderBy('id', 'DESC')->get()->map(function ($property) use ($loggedInUserData) {
                    // Add lastest Reject reason when request status is rejected
                    $property->reject_reason = (object)array();
                    if ($property->request_status == 'rejected') {
                        $property->reject_reason = $property->reject_reason()->latest()->first();
                    }
                    $property->is_premium = $property->is_premium == 1 ? true : false;
                    $property->property_type = $property->propery_type;
                    $property->post_created = $property->created_at->diffForHumans();
                    $property->promoted = $property->is_promoted;
                    $property->parameters = $property->parameters;
                    $property->assign_facilities = $property->assign_facilities;
                    $property->is_feature_available = $property->is_feature_available;
                    if ($property->category) {
                        $property->category->translated_name = $property->category->translated_name;
                    }
                    $property->translated_title = $property->translated_title;
                    $property->translated_description = $property->translated_description;
                    $property->translated_address = $property->translated_address;

                    // Interested Users
                    $interestedUsers = $property->interested_users;
                    unset($property->interested_users);
                    $property->interested_users = $interestedUsers->map(function ($interestedUser) {
                        unset($property->id);
                        unset($property->property_id);
                        unset($property->customer_id);
                        return $interestedUser->customer;
                    });

                    // Add User's Details
                    $property->customer_name = $loggedInUserData->name;
                    $property->email = $loggedInUserData->email;
                    $property->mobile = $loggedInUserData->mobile;
                    $property->profile = $loggedInUserData->profile;
                    return $property;
                });

                $response = array(
                    'error' => false,
                    'data' => $propertyData,
                    'total' => $totalProperties,
                    'total_views' => $totalViews,
                    'message' => trans("Data Fetched Successfully")
                );

                $getSimilarProperties = array();
                if ($propertyData->isNotEmpty()) {
                    if ($request->has('id')) {
                        $getSimilarPropertiesQueryData = Property::where(['post_type' => 1, 'added_by' => $loggedInUserID, 'category_id' => $propertyData[0]['category_id']])->where('id', '!=', $request->id)->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image', 'is_premium', 'expiry_date')->orderBy('id', 'desc')->limit(10)->get();
                        $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $loggedInUserData, true);
                    } else if ($request->has('slug_id')) {
                        $getSimilarPropertiesQueryData = Property::where(['post_type' => 1, 'added_by' => $loggedInUserID, 'category_id' => $propertyData[0]['category_id']])->where('slug_id', '!=', $request->slug_id)->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image', 'is_premium', 'expiry_date')->orderBy('id', 'desc')->limit(10)->get();
                        $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $loggedInUserData, true);
                    }
                }
                $response['similiar_properties'] = $getSimilarProperties;
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }


    /**
     * Homepage Data API
     * Params :- None
     */
    // public function homepageData(Request $request)
    // {
    //     try {
    //         $latitude = $request->latitude;
    //         $longitude = $request->longitude;
    //         $radius = $request->radius;
    //         $homepageLocationDataAvailable = false;

    //         $propertyMapper = function ($propertyData) {
    //             $propertyData->promoted = $propertyData->is_promoted;
    //             $propertyData->property_type = $propertyData->propery_type;
    //             $propertyData->is_premium = $propertyData->is_premium == 1;
    //             $propertyData->parameters = $propertyData->parameters;
    //             if ($propertyData->category) {
    //                 $propertyData->category->translated_name = $propertyData->category->translated_name;
    //             }
    //             $propertyData->translated_title = $propertyData->translated_title;
    //             $propertyData->translated_description = $propertyData->translated_description;
    //             return $propertyData;
    //         };

    //         $propertyBaseQuery = Property::select(
    //             'id', 'slug_id', 'category_id', 'city', 'state', 'country',
    //             'price', 'propery_type', 'title', 'title_image', 'is_premium',
    //             'address', 'rentduration', 'latitude', 'longitude', 'added_by',
    //             'description'
    //         )
    //         ->with(['category:id,slug_id,image,category', 'category.translations', 'translations'])
    //         ->onlyActive()
    //         ->whereIn('propery_type', [0, 1]);

    //         $locationBasedPropertyQuery = clone $propertyBaseQuery;
    //         if ($latitude && $longitude) {
    //             if ($radius) {
    //                 $locationBasedPropertyQuery->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$latitude, $longitude, $latitude])
    //                     ->where('latitude', '!=', 0)
    //                     ->where('longitude', '!=', 0)
    //                     ->having('distance', '<', $radius);
    //             } else {
    //                 $locationBasedPropertyQuery->where(['latitude' => $latitude, 'longitude' => $longitude]);
    //             }
    //         }

    //         $projectsBaseQuery = Projects::select(
    //             'id', 'slug_id', 'city', 'state', 'country', 'title',
    //             'type', 'image', 'location', 'category_id', 'added_by', 'latitude', 'longitude'
    //         )
    //         ->where(['request_status' => 'approved', 'status' => 1])
    //         ->with([
    //             'category:id,slug_id,image,category',
    //             'category.translations',
    //             'gallary_images:id,project_id,name',
    //             'customer:id,name,profile,email,mobile',
    //             'translations'
    //         ]);

    //         $locationBasedProjectsQuery = clone $projectsBaseQuery;
    //         if ($latitude && $longitude) {
    //             if ($radius) {
    //                 $locationBasedProjectsQuery->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$latitude, $longitude, $latitude])
    //                     ->where('latitude', '!=', 0)
    //                     ->where('longitude', '!=', 0)
    //                     ->having('distance', '<', $radius);
    //             } else {
    //                 $locationBasedProjectsQuery->where(['latitude' => $latitude, 'longitude' => $longitude]);
    //             }

    //             if ($locationBasedProjectsQuery->exists() || $locationBasedPropertyQuery->exists()) {
    //                 $homepageLocationDataAvailable = true;
    //             } else {
    //                 $locationBasedPropertyQuery = clone $propertyBaseQuery;
    //                 $locationBasedProjectsQuery = clone $projectsBaseQuery;
    //             }
    //         }

    //         if (env('DEMO_MODE') == true) {
    //             $homepageLocationDataAvailable = true;
    //         }

    //         $sections = $this->getHomepageSections(
    //             $latitude,
    //             $longitude,
    //             $propertyBaseQuery,
    //             $propertyMapper,
    //             $projectsBaseQuery,
    //             $homepageLocationDataAvailable,
    //             $locationBasedPropertyQuery,
    //             $locationBasedProjectsQuery
    //         );

    //         $slidersData = Slider::select('id', 'type', 'image', 'web_image', 'category_id', 'propertys_id', 'show_property_details', 'link')
    //             ->with([
    //                 'category' => function ($query) {
    //                     $query->where('status', 1)
    //                         ->select('id', 'slug_id', 'category','image')
    //                         ->with('translations');
    //                 },
    //                 'property' => function ($query) {
    //                     $query->whereIn('propery_type', [0, 1])
    //                         ->onlyActive()
    //                         ->with('translations')
    //                         ->select('id','slug_id','propery_type','title_image','title','price','city','state','country','rentduration','added_by','is_premium','latitude','longitude','total_click');
    //                 }
    //             ])
    //             ->orderBy('id', 'desc')
    //             ->get()
    //             ->map(function ($slider) {
    //                 $type = $slider->getRawOriginal('type');
    //                 $slider->slider_type = $type;

    //                 if ($type == 2) {
    //                     // Only keep slider if category exists
    //                     if (!empty($slider->category)) {
    //                         $slider->category->translated_name = $slider->category->translated_name;
    //                         return $slider;
    //                     }
    //                     return null; // discard slider
    //                 }

    //                 if ($type == 3) {
    //                     // Only keep slider if property exists
    //                     if (!empty($slider->property)) {
    //                         $slider->property->parameters = $slider->property->parameters;
    //                         $slider->property->translated_title = $slider->property->translated_title;
    //                         $slider->property->translated_description = $slider->property->translated_description;
    //                         $slider->property->property_type = $slider->property->propery_type;
    //                         $slider->property->is_premium = $slider->property->is_premium == 1 ? true : false;
    //                         return $slider;
    //                     }
    //                     return null; // discard slider
    //                 }

    //                 return $slider; // keep all other types
    //             })
    //             ->filter() // removes null values
    //             ->values();


    //         $data = [
    //             'sections' => $sections,
    //             'slider_section' => $slidersData,
    //             'homepage_location_data_available' => $homepageLocationDataAvailable
    //         ];

    //         ApiResponseService::successResponse("Data Fetched Successfully", $data);
    //     } catch (\Exception $e) {
    //         ApiResponseService::errorResponse();
    //     }
    // }

    /**
     * Agent List API
     * Params :- limit and offset
     */
 public function getAgentList(Request $request)
{
    try {
        // Get Offset and Limit from payload request
        $offset = $request->input('offset', 0);
        
        // Fix: Don't subtract 1 from limit
        $limit = $request->input('limit', 10);
        
        $latitude = $request->has('latitude') ? $request->latitude : null;
        $longitude = $request->has('longitude') ? $request->longitude : null;

        // Get agents who have properties or projects
        $agentsListQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'facebook_id', 'twiiter_id as twitter_id', 'instagram_id', 'youtube_id', 'mobile', 'about_me', 'is_premium', 'created_at')
            ->where('isActive', 1)
            // Temporarily remove the verification requirement for testing
            // ->whereHas('verify_customer', function ($query) {
            //     $query->where('status', 'success');
            // })
            ->where(function ($query) use ($latitude, $longitude) {
                $query->whereHas('projects', function ($query) use ($latitude, $longitude) {
                    $query->onlyActive()->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                        $query->where('latitude', $latitude)->where('longitude', $longitude);
                    });
                })->orWhereHas('property', function ($query) use ($latitude, $longitude) {
                    $query->onlyActive()->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                        $query->where('latitude', $latitude)->where('longitude', $longitude);
                    });
                });
            })
            ->withCount([
                'projects' => function ($query) use ($latitude, $longitude) {
                    $query->onlyActive()->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                        $query->where('latitude', $latitude)->where('longitude', $longitude);
                    });
                },
                'property' => function ($query) use ($latitude, $longitude) {
                    $query->onlyActive()->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                        $query->where('latitude', $latitude)->where('longitude', $longitude);
                    });
                }
            ]);

        // Clone for count before filtering
        $agentListCountQuery = clone $agentsListQuery;
        $agentListDataQuery = clone $agentsListQuery;
        
        // Get all results first for proper filtering
        $allAgents = $agentListDataQuery->get();
        
        // Filter to only include those with properties or projects
        $filteredAgents = $allAgents->filter(function ($customer) {
            return $customer->projects_count > 0 || $customer->property_count > 0;
        })->map(function ($customer) {
            $customer->is_verified = true; // Temporarily set to true
            $customer->is_verified_user = true;
            $customer->total_count = $customer->projects_count + $customer->property_count;
            $customer->is_admin = false;
            $customer->profile_image = $customer->profile ? url('storage/app/public/users/' . $customer->profile) : null;
            $customer->bio = $customer->about_me;
            $customer->phone = $customer->mobile;
            return $customer;
        })->sortByDesc(function ($customer) {
            return [$customer->is_verified, $customer->total_count];
        });
        
        $agentListCount = $filteredAgents->count();
        
        // Apply offset and limit
        $agentListData = $filteredAgents->skip($offset)->take($limit)->values();

        // Get admin List
        $adminEmail = system_setting('company_email');
        $adminData = array();
        $adminPropertiesCount = Property::onlyActive()->where(['added_by' => 0])->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
            $query->where('latitude', $latitude)->where('longitude', $longitude);
        })->count();
        
        $adminProjectsCount = Projects::onlyActive()->where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
            $query->where('latitude', $latitude)->where('longitude', $longitude);
        })->count();
        
        $totalCount = $adminPropertiesCount + $adminProjectsCount;

        $adminQuery = User::where('type', 0)->select('id', 'slug_id', 'name', 'profile')->first();
        
        if ($adminQuery && ($adminPropertiesCount > 0 || $adminProjectsCount > 0)) {
            $adminData = array(
                'id' => $adminQuery->id,
                'name' => 'Admin',
                'slug_id' => $adminQuery->slug_id,
                'email' => !empty($adminEmail) ? $adminEmail : "",
                'property_count' => $adminPropertiesCount,
                'projects_count' => $adminProjectsCount,
                'total_count' => $totalCount,
                'is_verified' => true,
                'is_verified_user' => true,
                'profile' => !empty($adminQuery->getRawOriginal('profile')) ? url('storage/app/public/admin_profile/' . $adminQuery->profile) : url('assets/images/faces/2.jpg'),
                'profile_image' => !empty($adminQuery->getRawOriginal('profile')) ? url('storage/app/public/admin_profile/' . $adminQuery->profile) : url('assets/images/faces/2.jpg'),
                'is_admin' => true,
                'phone' => '',
                'bio' => '',
                'mobile' => ''
            );
            if ($offset == 0) {
                $agentListData->prepend((object) $adminData);
                $agentListCount++;
            }
        }
        
        $response = array(
            'error' => false,
            'total' => $agentListCount,
            'data' => $agentListData,
            'message' => trans("Data Fetched Successfully")
        );

        return response()->json($response);
        
    } catch (Exception $e) {
        \Log::error('getAgentList Error: ' . $e->getMessage() . ' on line ' . $e->getLine());
        
        $response = array(
            'error' => true,
            'total' => 0,
            'data' => array(),
            'message' => trans("Something Went Wrong") . ': ' . $e->getMessage()
        );
        return response()->json($response, 500);
    }
}

    /**
     * Agent Properties API
     * Params :- id or slug_id, limit, offset and is_project
     */
    public function getAgentProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug_id' => 'required_without_all:id,is_admin',
            'is_projects' => 'nullable|in:1',
            'is_admin' => 'nullable|in:1',
            'search' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $response = array(
                'package_available' => false,
                'feature_available' => false,
                'limit_available' => false,
            );
            // Get Limit Status of premium properties feature
            if (Auth::guard('sanctum')) {
                $response = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true);
            }

            // dd($response);
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            $isAdminListing = false;

            if ($request->has('is_admin') && $request->is_admin == 1) {
                $addedBy = 0;
                $isAdminListing = true;
                $settings = HelperService::getMultipleSettingData(['company_email', 'company_tel1', 'company_address']);
                $adminEmail = $settings['company_email'];
                $adminCompanyTel1 = $settings['company_tel1'];
                $adminAddress = $settings['company_address'];
                $customerData = array();
                $adminPropertiesCount = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->count();
                $adminProjectsCount = Projects::where(['is_admin_listing' => 1, 'status' => 1])->count();
                $totalCount = $adminPropertiesCount + $adminProjectsCount;

                $adminData = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id', 'type')->first();
                $adminData['is_agent'] = $adminData->is_agent;
                $adminData['is_appointment_available'] = $adminData->is_appointment_available;
                if ($adminData) {
                    $customerData = array(
                        'id' => $adminData->id,
                        'name' => 'Admin',
                        'slug_id' => $adminData->slug_id,
                        'email' => !empty($adminEmail) ? $adminEmail : "",
                        'mobile' => !empty($adminCompanyTel1) ? $adminCompanyTel1 : "",
                        'address' => !empty($adminAddress) ? $adminAddress : "",
                        'property_count' => $adminPropertiesCount,
                        'projects_count' => $adminProjectsCount,
                        'total_count' => $totalCount,
                        'is_verify' => true,
                        'is_verified_user' => true,
                        'profile' => !empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg'),
                        'is_agent' => $adminData->is_agent,
                        'is_appointment_available' => $adminData->is_appointment_available
                    );
                }
            } else {
                // Customer Query
                $customerQuery = Customer::select('id', 'slug_id', 'name', 'profile', 'mobile', 'email', 'address', 'city', 'country', 'state', 'facebook_id', 'twiiter_id as twitter_id', 'youtube_id', 'instagram_id', 'about_me', 'latitude', 'longitude')->where(function ($query) {
                    $query->where('isActive', 1);
                })->withCount(['projects' => function ($query) {
                    $query->where('status', 1);
                }, 'property' => function ($query) use ($response) {
                    if ($response['package_available'] == true && $response['feature_available'] == true) {
                        $query->onlyActive();
                    } else {
                        $query->where(['status' => 1, 'request_status' => 'approved', 'is_premium' => 0]);
                    }
                }]);
                // Check if id exists or slug id on the basis of get agent id
                if ($request->has('id') && !empty($request->id)) {
                    $addedBy = $request->id;
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('id', $request->id)->first();
                    $addedBy = !empty($customerData) ? $customerData->id : "";
                } else if ($request->has('slug_id')) {
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('slug_id', $request->slug_id)->first();
                    $addedBy = !empty($customerData) ? $customerData->id : "";
                }
                // Add Is User Verified Status in Customer Data
                // is_verified_user = true means the user has submitted a verification request and admin has approved it
                !empty($customerData) ? $customerData->is_verify = $customerData->is_user_verified : "";
                !empty($customerData) ? $customerData->is_verified_user = $customerData->is_user_verified : "";
                !empty($customerData) ? $customerData->is_agent = $customerData->is_agent : "";
                !empty($customerData) ? $customerData->is_appointment_available = $customerData->is_appointment_available : "";
            }

            // if there is agent id then only get properties of it
            if (!empty($addedBy) || $addedBy == 0) {
                if (($request->has('is_projects') && !empty($request->is_projects) && $request->is_projects == 1)) {
                    // Always fetch non-premium projects regardless of package
                    $projectQuery = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location', 'category_id', 'added_by', 'is_premium')->when($request->has('search') && !empty($request->search), function ($query) use ($request) {
                        $query->where('title', 'LIKE', "%$request->search%");
                    });
                    if ($isAdminListing == true) {
                        $projectQuery = $projectQuery->clone()->where(['status' => 1, 'is_admin_listing' => 1]);
                    } else {
                        $projectQuery = $projectQuery->clone()->onlyActive()->where(['added_by' => $addedBy]);
                    }
                    // Only show non-premium projects if feature is not available (no valid package)
                    if ($response['feature_available'] == false) {
                        $projectQuery = $projectQuery->where('is_premium', 0);
                    }
                    $totalProjects = $projectQuery->clone()->count();
                    $totalData = $totalProjects;
                    // Always return project data (no package check)
                    $projectData = $projectQuery->clone()->with('gallary_images', 'category:id,slug_id,image,category', 'category.translations', 'translations')->skip($offset)->take($limit)->get()->map(function ($project) {
                        if ($project->category) {
                            $project->category->translated_name = $project->category->translated_name;
                        }
                        $project->translated_title = $project->translated_title;
                        $project->translated_description = $project->translated_description;
                        return $project;
                    });
                    // Force feature_available = true for projects response
                    $response['feature_available'] = true;
                    $response['package_available'] = $response['package_available'] ?? true;
                } else {
                    // Create a proeprty query
                    $propertiesQuery = Property::onlyActive()->where(['added_by' => $addedBy])
                        ->when($request->has('search') && !empty($request->search), function ($query) use ($request) {
                            $query->where('title', 'LIKE', "%$request->search%");
                        });
                    // Count premium properties without the condition
                    $premiumPropertiesCount = $propertiesQuery->clone()->where('is_premium', 1)->count();

                    $propertiesQuery = $propertiesQuery->when(($response['feature_available'] == false), function ($query) {
                        $query->where('is_premium', 0);
                    });

                    // Count total properties
                    $totalProperties = $propertiesQuery->clone()->count();


                    // Get Propertis Data
                    $propertiesData = $propertiesQuery->clone()
                        ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                        ->select('id', 'slug_id', 'city', 'state', 'category_id', 'country', 'price', 'propery_type', 'title', 'title_image', 'is_premium', 'address', 'added_by')
                        ->orderBy('is_premium', 'DESC')->skip($offset)->take($limit)->get()->map(function ($property) {
                            $property->property_type = $property->propery_type;
                            $property->parameters = $property->parameters;
                            $property->promoted = $property->is_promoted;
                            if ($property->category) {
                                $property->category->translated_name = $property->category->translated_name;
                            }
                            $property->translated_title = $property->translated_title;
                            $property->translated_description = $property->translated_description;
                            unset($property->propery_type);
                            return $property;
                        });
                    $totalData = $totalProperties;
                    $totalSoldProperties = $propertiesQuery->clone()->where('propery_type', 2)->count();
                    $totalRentedProperties = $propertiesQuery->clone()->where('propery_type', 3)->count();
                }
            }
            // Add Sold and Rented Count in Customer Data
            $customerData['properties_sold_count'] = $totalSoldProperties ?? 0;
            $customerData['properties_rented_count'] = $totalRentedProperties ?? 0;


            $response = array(
                'error'                     => false,
                'total'                     => $totalData ?? 0,
                'data' => array(
                    'customer_data'             => $customerData ?? array(),
                    'properties_data'           => $propertiesData ?? array(),
                    'projects_data'             => $projectData ?? array(),
                    'premium_properties_count'  => $premiumPropertiesCount ?? 0,
                    'package_available'         => $response['package_available'] ?? true,
                    'feature_available'         => $response['feature_available'] ?? true,

                ),
                'message' => trans("Data Fetched Successfully")
            );
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }


    public function getWebSettings(Request $request)
    {
        try {
            // Types for web requirement only
            $types = array('company_name', 'currency_symbol', 'default_language', 'number_with_suffix', 'web_maintenance_mode', 'company_tel', 'company_tel2', 'system_version', 'web_favicon', 'web_logo', 'web_footer_logo', 'web_placeholder_logo', 'company_email', 'latitude', 'longitude', 'company_address', 'system_color', 'iframe_link', 'facebook_id', 'instagram_id', 'twitter_id', 'youtube_id', 'playstore_id', 'sell_background', 'appstore_id', 'category_background', 'web_maintenance_mod', 'seo_settings', 'company_tel1', 'place_api_key', 'stripe_publishable_key', 'paystack_public_key', 'sell_web_color', 'sell_web_background_color', 'rent_web_color', 'rent_web_background_color', 'number_with_otp_login', 'social_login', 'distance_option', 'otp_service_provider', 'text_property_submission', 'auto_approve', 'verification_required_for_user', 'allow_cookies', 'currency_code', 'bank_details', 'schema_for_deeplink', 'min_radius_range', 'max_radius_range', 'homepage_location_alert_status', 'email_password_login', 'gemini_ai_enabled', 'show_direct_video_upload', 'show_premium_toggle');

            // Query the Types to Settings Table to get its data
            $result =  Setting::whereIn('type', $types)->with('translations')->select('id', 'type', 'data')->get();

            // Check the result data is not empty
            if (collect($result)->isNotEmpty()) {
                $settingsData = array();

                // Loop on the result data
                foreach ($result as $row) {
                    // Change data according to conditions
                    if ($row->type == 'company_logo') {
                        // Add logo image with its url
                        $settingsData[$row->type] = url('/assets/images/logo/logo.png');
                    } else if ($row->type == 'seo_settings') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } else if ($row->type == 'allow_cookies') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } else if ($row->type == 'verification_required_for_user') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } else if ($row->type == 'web_favicon' || $row->type == 'web_logo' || $row->type == 'web_placeholder_logo' || $row->type == 'web_footer_logo') {
                        // Add Full URL to the specified type
                        $settingsData[$row->type] = url('/assets/images/logo/') . '/' . $row->data;
                    } else if ($row->type == 'currency_code') {
                        // Change Value to Bool
                        $settingsData['selected_currency_data'] = HelperService::getCurrencyData($row->data);
                    } else if ($row->type == 'bank_details') {
                        // Change Value to Bool
                        $bankDetails = json_decode($row->data, true);
                        $settingsData['bank_details'] = $this->processBankDetails($bankDetails);
                    } else if ($row->type == 'default_language') {
                        // Add Code in Data
                        $rowData = $row->data;
                        $languageCode = HelperService::getCustomerDefaultLanguage() ?? $rowData;
                        $settingsData[$row->type] = $languageCode;
                        if ($languageCode == 'en') {
                            $languageCode = 'en-new';
                        }

                        // Add Default language's name
                        $languageData = Language::where('code', $languageCode)->first();
                        if (collect($languageData)->isNotEmpty()) {
                            $settingsData['default_language_name'] = $languageData->name;
                            $settingsData['default_language_rtl'] = $languageData->rtl == 1 ? 1 : 0;
                        } else {
                            $settingsData['default_language_name'] = "";
                            $settingsData['default_language_rtl'] = 0;
                        }
                    } else if ($row->type == 'gemini_ai_enabled') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
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
                $sections = HomepageSection::where('is_active', 1)
                    ->orderBy('sort_order')
                    ->with('translations')
                    ->get()
                    ->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'type' => $section->section_type,
                            'title' => $section->title,
                            'translated_title' => $section->translated_title,
                            'sort_order' => $section->sort_order,
                            'is_active' => $section->is_active
                        ];
                    });
                $settingsData['homepage_sections'] = $sections;

                // if Token is passed of current user.
                if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
                    $loggedInUserId = Auth::guard('sanctum')->user()->id;
                    update_subscription($loggedInUserId);

                    $checkVerifiedStatus = VerifyCustomer::where('user_id', $loggedInUserId)->first();
                    if (!empty($checkVerifiedStatus)) {
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

                    // Check the subscription
                    if (collect($customerData)->isNotEmpty()) {
                        $settingsData['is_premium'] = $customerData->is_premium == 1 ? true : ($customerData->subscription == 1 ? true : false);
                        $settingsData['subscription'] = $customerData->subscription == 1 ? true : false;
                    } else {
                        $settingsData['is_premium'] = false;
                        $settingsData['subscription'] = false;
                    }
                }


                // Check the min_price and max_price
                $settingsData['min_price'] = DB::table('propertys')->selectRaw('MIN(price) as min_price')->value('min_price');
                $settingsData['max_price'] = DB::table('propertys')->selectRaw('MAX(price) as max_price')->value('max_price');

                // Check the features available
                $settingsData['features_available'] = array(
                    'premium_properties' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true)['feature_available'],
                    'premium_projects' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'), true)['feature_available'],
                );

                // Get Languages Data
                $specificSelect = ['id', 'code', 'name'];
                $language = HelperService::getActiveLanguages($specificSelect, true);
                $settingsData['languages'] = $language;

                $response['error'] = false;
                $response['message'] = trans("Data Fetched Successfully");
                $response['data'] = $settingsData;
            } else {
                $response['error'] = false;
                $response['message'] = trans("No Data Found");
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }


    public function getAppSettings(Request $request)
    {
        try {
            $types = array('company_name', 'currency_symbol', 'ios_version', 'default_language', 'force_update', 'android_version', 'number_with_suffix', 'maintenance_mode', 'company_tel1', 'company_tel2', 'company_email', 'company_address', 'place_api_key', 'playstore_id', 'sell_background', 'appstore_id', 'show_admob_ads', 'android_banner_ad_id', 'ios_banner_ad_id', 'android_interstitial_ad_id', 'ios_interstitial_ad_id', 'android_native_ad_id', 'ios_native_ad_id', 'demo_mode', 'min_price', 'max_price', 'number_with_otp_login', 'social_login', 'distance_option', 'otp_service_provider', 'app_home_screen', 'placeholder_logo', 'dark_mode_logo', 'light_tertiary', 'light_secondary', 'light_primary', 'dark_tertiary', 'dark_secondary', 'dark_primary', 'text_property_submission', 'auto_approve', 'verification_required_for_user', 'currency_code', 'bank_details', 'schema_for_deeplink', 'min_radius_range', 'max_radius_range', 'latitude', 'longitude', 'homepage_location_alert_status', 'email_password_login', 'app_login_background', 'gemini_ai_enabled', 'show_direct_video_upload', 'show_premium_toggle');

            // Query the Types to Settings Table to get its data
            $result =  Setting::whereIn('type', $types)->with('translations')->select('id', 'type', 'data')->get();

            // Check the result data is not empty
            if (collect($result)->isNotEmpty()) {
                $settingsData = array();

                // Loop on the result data
                foreach ($result as $row) {
                    if ($row->type == 'default_language') {
                        // Add Code in Data
                        $rowData = $row->data;
                        $languageCode = HelperService::getCustomerDefaultLanguage() ?? $rowData;
                        $settingsData[$row->type] = $languageCode;
                        if ($languageCode == 'en') {
                            $languageCode = 'en-new';
                        }

                        // Add Default language's name
                        $languageData = Language::where('code', $languageCode)->first();
                        if (collect($languageData)->isNotEmpty()) {
                            $settingsData['default_language_name'] = $languageData->name;
                            $settingsData['default_language_rtl'] = $languageData->rtl == 1 ? 1 : 0;
                        } else {
                            $settingsData['default_language_name'] = "";
                            $settingsData['default_language_rtl'] = 0;
                        }
                    } else if ($row->type == 'app_home_screen' || $row->type == "placeholder_logo" || $row->type == "dark_mode_logo" || $row->type == "app_login_background") {
                        $settingsData[$row->type] = url('/assets/images/logo/') . '/' . $row->data;
                    } else if ($row->type == 'verification_required_for_user') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
                    } else if ($row->type == 'currency_code') {
                        // Change Value to Bool
                        $settingsData['selected_currency_data'] = HelperService::getCurrencyData($row->data);
                    } else if ($row->type == 'bank_details') {
                        // Change Value to Bool
                        $bankDetails = json_decode($row->data, true);
                        $settingsData['bank_details'] = $this->processBankDetails($bankDetails);
                    } else if ($row->type == 'default_language') {
                        // Add Code in Data
                        $settingsData[$row->type] = HelperService::getCustomerDefaultLanguage() ?? $row->data;

                        // Add Default language's name
                        $languageData = Language::where('code', $row->data)->first();
                        if (collect($languageData)->isNotEmpty()) {
                            $settingsData['default_language_name'] = $languageData->name;
                            $settingsData['default_language_rtl'] = $languageData->rtl == 1 ? 1 : 0;
                        } else {
                            $settingsData['default_language_name'] = "";
                            $settingsData['default_language_rtl'] = 0;
                        }
                    } else if ($row->type == 'gemini_ai_enabled') {
                        // Change Value to Bool
                        $settingsData[$row->type] = $row->data == 1 ? true : false;
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
                    if (!empty($checkVerifiedStatus)) {
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

                    // Check the subscription
                    if (collect($customerData)->isNotEmpty()) {
                        $settingsData['is_premium'] = $customerData->is_premium == 1 ? true : ($customerData->subscription == 1 ? true : false);
                        $settingsData['subscription'] = $customerData->subscription == 1 ? true : false;
                    } else {
                        $settingsData['is_premium'] = false;
                        $settingsData['subscription'] = false;
                    }
                }

                // Check the min_price and max_price
                $settingsData['min_price'] = DB::table('propertys')->selectRaw('MIN(price) as min_price')->value('min_price');
                $settingsData['max_price'] = DB::table('propertys')->selectRaw('MAX(price) as max_price')->value('max_price');

                // Homepage Section Data
                $sections = HomepageSection::where('is_active', 1)
                    ->orderBy('sort_order')
                    ->with('translations')
                    ->get()
                    ->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'type' => $section->section_type,
                            'title' => $section->title,
                            'translated_title' => $section->translated_title,
                            'sort_order' => $section->sort_order,
                            'is_active' => $section->is_active
                        ];
                    });
                $settingsData['homepage_sections'] = $sections;

                // Check the features available
                $settingsData['features_available'] = array(
                    'premium_properties' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true)['feature_available'],
                    'premium_projects' => HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'), true)['feature_available'],
                );

                // Get Languages Data
                $specificSelect = ['id', 'code', 'name'];
                $language = HelperService::getActiveLanguages($specificSelect, true);
                $settingsData['languages'] = $language;

                $response['error'] = false;
                $response['message'] = trans("Data Fetched Successfully");
                $response['data'] = $settingsData;
            } else {
                $response['error'] = false;
                $response['message'] = trans("No Data Found");
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }

    public function getLanguagesData()
    {
        try {
            $languageData = Language::select('id', 'code', 'name')->get();
            if (collect($languageData)->isNotEmpty()) {
                $response['error'] = false;
                $response['message'] = trans("Data Fetched Successfully");
                $response['data'] = $languageData;
            } else {
                $response['error'] = false;
                $response['message'] = trans("No Data Found");
                $response['data'] = [];
            }
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }

    /**
     * Faq API
     * Params :- Limit and offset
     */
    public function getFaqData(Request $request)
    {
        try {
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            $faqsQuery = Faq::where('status', 1);
            $totalData = $faqsQuery->clone()->count();
            $faqsData = $faqsQuery->clone()->with('translations')->select('id', 'question', 'answer')->orderBy('id', 'DESC')->skip($offset)->take($limit)->get()->map(function ($faq) {
                $faq->translated_question = $faq->translated_question;
                $faq->translated_answer = $faq->translated_answer;
                return $faq;
            });
            $response = array(
                'error' => false,
                'total' => $totalData ?? 0,
                'data' => $faqsData,
                'message' => trans("Data Fetched Successfully")
            );
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
            return response()->json($response, 500);
        }
    }

    /**
     * beforeLogout API
     */
    public function beforeLogout(Request $request)
    {
        try {
            if ($request->has('fcm_id')) {
                Usertokens::where(['fcm_id' => $request->fcm_id, 'customer_id' => $request->user()->id])->delete();
            }
            $response = array(
                'error' => false,
                'message' => trans("Data Processed Successfully")
            );
            return response()->json($response);
        } catch (Exception $e) {
            $response = array(
                'error' => true,
                'message' => trans("Something Went Wrong")
            );
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
            if ($request->has('number') && !empty($request->number)) {
                $requestNumber = $request->number; // Get data from Request
                $toNumber = '+' . $request->country_code . $requestNumber;

                // Initialize empty array
                $dbData = array();

                // make an array of types for database query and get data from settings table
                $twilioCredentialsTypes = array('twilio_account_sid', 'twilio_auth_token', 'twilio_my_phone_number');
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
                        'message' => trans("Invalid Phone Number")
                    ]);
                }
                // Check if OTP already exists and is still valid
                $existingOtp = $otpRecordDB->clone()->where('number', $toNumber)->first();
            } else if ($request->has('email') && !empty($request->email)) {
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

                if ($request->has('number') && !empty($request->number)) {
                    $expireAt = now()->addMinutes(3); // Set OTP expiry time
                    // Update or create OTP entry in the database
                    NumberOtp::updateOrCreate(
                        ['number' => $toNumber],
                        ['otp' => $otp, 'expire_at' => $expireAt]
                    );
                } else if ($request->has('email') && !empty($request->email)) {
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


            if ($request->has('number') && !empty($request->number)) {
                // Use the Client to make requests to the Twilio REST API
                $client->messages->create(
                    // The number you'd like to send the message to
                    $toNumber,
                    [
                        // A Twilio phone number you purchased at https://console.twilio.com
                        'from' => $fromNumber,
                        // The body of the text message you'd like to send
                        'body' => "Here is the OTP: " . $otp . ". It expires in 3 minutes."
                    ]
                );
                /** Note :- While using Trial accounts cannot send messages to unverified numbers, or purchase a Twilio number to send messages to unverified numbers.*/
            } else if ($request->has('email') && !empty($request->email)) {
                try {
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes("verify_mail");

                    // Email Template
                    $verifyEmailTemplateData = system_setting("verify_mail_template");
                    $variables = array(
                        'app_name' => env("APP_NAME") ?? "eBroker",
                        'otp' => $otp
                    );
                    if (empty($verifyEmailTemplateData)) {
                        $verifyEmailTemplateData = "Your OTP is :- $otp";
                    }
                    $verifyEmailTemplate = HelperService::replaceEmailVariables($verifyEmailTemplateData, $variables);

                    $data = array(
                        'email_template' => $verifyEmailTemplate,
                        'email' => $toEmail,
                        'title' => $emailTypeData['title'],
                    );

                    HelperService::sendMail($data, false, true);
                } catch (Exception $e) {
                    if (Str::contains($e->getMessage(), [
                        'Failed',
                        'Mail',
                        'Mailer',
                        'MailManager',
                        "Connection could not be established"
                    ])) {
                        ApiResponseService::validationError("There is issue with mail configuration, kindly contact admin regarding this");
                    } else {
                        ApiResponseService::errorResponse();
                    }
                }
            }
            // Return success response
            return response()->json([
                'error' => false,
                'message' => trans("OTP Sent Successfully")
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
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
            if ($request->has('number') && !empty($request->number)) {
                $requestNumber = $request->number; // Get data from Request
                $toNumber = '+' . $request->country_code . $requestNumber;

                // Fetch the OTP record from the database
                $otpRecord = $otpRecordDB->clone()->where('number', $toNumber)->first();
            } else if ($request->has('email') && !empty($request->email)) {
                $toEmail = $request->email;
                // Fetch the OTP record from the database
                $otpRecord = $otpRecordDB->clone()->where('email', $toEmail)->first();
            } else {
                ApiResponseService::errorResponse();
            }
            $userOtp = $request->otp;

            if (!$otpRecord) {
                return response()->json([
                    'error' => true,
                    'message' => trans("OTP Not Found")
                ]);
            }

            // Check if the OTP is valid and not expired
            if ($otpRecord->otp == $userOtp && now()->isBefore($otpRecord->expire_at)) {

                if ($request->has('number') && !empty($request->number)) {
                    // Check the number and login type exists in user table
                    $user = Customer::where(['mobile' => $requestNumber, 'country_code' => $request->country_code, 'logintype' => 1])->first();
                } else if ($request->has('email') && !empty($request->email)) {
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
                if ($request->has('email') && !empty($request->email)) {
                    // Check the email and login type exists in user table
                    $user->is_email_verified = true;
                    $user->save();
                }

                return response()->json([
                    'error' => false,
                    'message' => trans("OTP Verified Successfully"),
                    'auth_id' => $authId
                ]);
            } else if ($otpRecord->otp != $userOtp) {
                ApiResponseService::validationError("Invalid OTP");
            } else if (now()->isAfter($otpRecord->expire_at)) {
                ApiResponseService::validationError("OTP Expired");
            } else {
                ApiResponseService::errorResponse();
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get property list with optional AI-powered search
     *
     * This method supports both traditional filtering and AI-powered natural language search.
     * When 'ai_search_prompt' is provided, the system uses Gemini AI to extract search parameters
     * from natural language queries like "3 bedroom furnished apartment in Mumbai under 2 crore".
     *
     * @param Request $request
     * @param int $request->offset Optional pagination offset (default: 0)
     * @param int $request->limit Optional pagination limit (default: 10)
     * @param string $request->get_all_premium_properties Optional flag to get only premium properties
     * @param string $request->filters Optional base64-encoded JSON filters
     * @param string $request->ai_search_prompt Optional natural language search query
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPropertyList(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset'                        => 'nullable|numeric',
                'limit'                         => 'nullable|numeric',
                'get_all_premium_properties'    => 'nullable|in:1',
                'filters'                       => 'nullable|string',
                'ai_search_prompt'              => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            // First decode filters (support base64, raw JSON string, or array)
            $filters = $request->filters;
            if (!empty($filters)) {
                if (is_array($filters)) {
                    // Already an array
                } else if (is_string($filters)) {
                    if (json_validate($filters)) {
                        $filters = json_decode($filters, true);
                    } else {
                        $decoded = base64_decode($filters, true);
                        if ($decoded !== false && json_validate($decoded)) {
                            $filters = json_decode($decoded, true);
                        } else {
                            $filters = array();
                        }
                    }
                } else {
                    $filters = array();
                }
            } else {
                $filters = array();
            }

            $isAiEnabled = 0;
            // // Check if AI search is enabled in settings and API key exists
            // $isAiEnabled = HelperService::getSettingData('gemini_ai_search') ? 1 : 0;
            // // Extract AI search prompt from filters (if present)
            // $aiExtractedFilters = [];
            $search = $filters['search'] ?? null;
            // if (!empty($search)) {
            //     $aiSearchPrompt = $search;
            //     $geminiApiKey = config('services.gemini.api_key');
            //     if($isAiEnabled == 1 && !empty($geminiApiKey)){
            //         try {
            //             // Get available categories, facilities, and nearby places for AI processing
            //             $categories = Category::select('id', 'category as name')->with('translations')->get()->map(function($category) {
            //                 return [
            //                     'id' => $category->id,
            //                     'name' => $category->name,
            //                     'translated_name' => $category->translated_name,
            //                     'translations' => $category->translations->map(function($translation){
            //                         return [
            //                             'language_id' => $translation->language_id,
            //                             'value' => $translation->value,
            //                         ];
            //                     }),
            //                 ];
            //             })->toArray();
            //             $facilities = parameter::select('id', 'name', 'type_of_parameter', 'type_values')->with('translations')->get()->map(function($facility) {
            //                 return [
            //                     'id' => $facility->id,
            //                     'name' => $facility->name,
            //                     'type_of_parameter' => $facility->type_of_parameter,
            //                     'values' => $facility->type_values,
            //                     'translated_option_value' => $facility->translated_option_value,
            //                     'translated_name' => $facility->translated_name,
            //                     'translations' => $facility->translations->map(function($translation){
            //                         return [
            //                             'language_id' => $translation->language_id,
            //                             'value' => $translation->value,
            //                         ];
            //                     }),
            //                 ];
            //             })->toArray();
            //             $nearbyPlaces = OutdoorFacilities::select('id', 'name')->with('translations')->get()->map(function($nearbyPlace) {
            //                 return [
            //                     'id' => $nearbyPlace->id,
            //                     'name' => $nearbyPlace->name,
            //                     'translated_name' => $nearbyPlace->translated_name,
            //                     'translations' => $nearbyPlace->translations->map(function($translation){
            //                         return [
            //                             'language_id' => $translation->language_id,
            //                             'value' => $translation->value,
            //                         ];
            //                     }),
            //                 ];
            //             })->toArray();

            //             // Use GeminiService to extract search parameters
            //             $geminiService = new GeminiService();
            //             $aiResult = $geminiService->extractSearchParameters($aiSearchPrompt, $categories, $nearbyPlaces, $facilities);
            //             if ($aiResult['success'] && !empty($aiResult['data'])) {
            //                 $aiExtractedFilters = $aiResult['data'];

            //                 // Convert AI extracted parameters to match existing filter structure
            //                 if (!empty($aiExtractedFilters['nearbyplace'])) {
            //                     $aiExtractedFilters['nearby_places'] = $aiExtractedFilters['nearbyplace'];
            //                     unset($aiExtractedFilters['nearbyplace']);
            //                 }

            //                 if (!empty($aiExtractedFilters['facilities'])) {
            //                     $aiExtractedFilters['parameters'] = $aiExtractedFilters['facilities'];
            //                     unset($aiExtractedFilters['facilities']);
            //                 }
            //             }
            //         } catch (\Exception $e) {
            //             Log::error('AI Search Error: ' . $e->getMessage());
            //         }
            //     }
            // }

            // // Merge AI-extracted filters with existing filters
            // // AI filters take precedence over existing filters for the same keys
            // if (!empty($aiExtractedFilters)) {
            //     $filters = array_merge($filters, $aiExtractedFilters);
            // }

            $filterValidator = Validator::make(
                collect($filters)->toArray(),
                [
                    'property_type'                             => 'nullable|in:0,1',
                    'category_id'                               => 'nullable|exists:categories,id',
                    'category_slug_id'                          => 'nullable|exists:categories,slug_id',
                    'location.country'                          => 'nullable',
                    'location.state'                            => 'nullable',
                    'location.city'                             => 'nullable',
                    'location.place_id'                         => 'nullable',
                    'location.latitude'                         => 'nullable',
                    'location.longitude'                        => 'nullable',
                    'location.range'                            => 'nullable',
                    'price.min_price'                           => 'nullable|numeric',
                    'price.max_price'                           => 'nullable|numeric',
                    'posted_since'                              => 'nullable|in:0,1,2,3,4',
                    'flags.promoted'                            => 'nullable',
                    'flags.most_viewed'                         => 'nullable',
                    'flags.most_liked'                          => 'nullable',
                    'flags.get_all_premium_properties'          => 'nullable',
                    'parameters'                                => 'nullable|array',
                    'parameters.*.id'                           => 'nullable|exists:parameters,id',
                    'parameters.*.value'                        => 'nullable',
                    'nearby_places'                             => 'nullable|array',
                    'nearby_places.*.id'                        => 'nullable|exists:outdoor_facilities,id',
                    'nearby_places.*.value'                     => 'nullable|integer',
                ],
                [
                    'property_type.in'                      => trans('Property type is not valid'),
                    'category_id.exists'                    => trans('Category id is not valid'),
                    'category_slug_id.exists'               => trans('Category slug id is not valid'),
                    'location.country.exists'               => trans('Country id is not valid'),
                    'location.state.exists'                 => trans('State id is not valid'),
                    'location.city.exists'                  => trans('City id is not valid'),
                    'location.place_id.string'              => trans('Place id is not valid'),
                    'price.min_price.numeric'               => trans('Min price is not valid'),
                    'price.max_price.numeric'               => trans('Max price is not valid'),
                    'posted_since.in'                       => trans('Posted since is not valid'),
                    'parameters.array'                      => trans('Parameters is not valid'),
                    'parameters.*.id.exists'                => trans('Parameter id is not valid'),
                    'nearby_places.array'                   => trans('Nearby place is not valid'),
                    'nearby_places.*.id.exists'             => trans('Nearby place id is not valid'),
                    'nearby_places.*.value.integer'         => trans('Nearby place value is not valid')
                ]
            );
            if ($filterValidator->fails()) {
                ApiResponseService::validationError($filterValidator->errors()->first());
            }

            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Get Filters Variables
            $propertyType = isset($filters['property_type']) ? $filters['property_type'] : null;
            $categoryId = isset($filters['category_id']) ? $filters['category_id'] : null;
            $categorySlugId = isset($filters['category_slug_id']) ? $filters['category_slug_id'] : null;
            $country = isset($filters['location']['country']) ? $filters['location']['country'] : (isset($filters['country']) ? $filters['country'] : (isset($request->country) ? $request->country : null));
            $state = isset($filters['location']['state']) ? $filters['location']['state'] : (isset($filters['state']) ? $filters['state'] : (isset($request->state) ? $request->state : null));
            $city = isset($filters['location']['city']) ? $filters['location']['city'] : (isset($filters['city']) ? $filters['city'] : (isset($filters['City']) ? $filters['City'] : (isset($request->city) ? $request->city : (isset($request->City) ? $request->City : null))));
            $placeId = isset($filters['location']['place_id']) ? $filters['location']['place_id'] : null;
            $latitude = isset($filters['location']['latitude']) ? $filters['location']['latitude'] : null;
            $longitude = isset($filters['location']['longitude']) ? $filters['location']['longitude'] : null;
            $minPrice = isset($filters['price']['min_price']) ? $filters['price']['min_price'] : null;
            $maxPrice = isset($filters['price']['max_price']) ? $filters['price']['max_price'] : null;
            $postedSince = isset($filters['posted_since']) ? $filters['posted_since'] : null;
            $range = isset($filters['location']['range']) ? $filters['location']['range'] : null;
            $promoted = isset($filters['flags']['promoted']) ? $filters['flags']['promoted'] : null;
            $getPremiumProperties = isset($filters['flags']['get_all_premium_properties']) ? $filters['flags']['get_all_premium_properties'] : null;
            $mostViewed = isset($filters['flags']['most_views']) ? $filters['flags']['most_views'] : null;
            $mostLiked = isset($filters['flags']['most_liked']) ? $filters['flags']['most_liked'] : null;
            $parameters = isset($filters['parameters']) ? $filters['parameters'] : null;
            $nearbyPlaces = isset($filters['nearby_places']) ? $filters['nearby_places'] : null;
            $title = isset($filters['title']) ? $filters['title'] : null;

            // Create a property query
            $propertyQuery = Property::whereIn('propery_type', [0, 1])->where(function ($query) {
                return $query->onlyActive();
            });

            // If Property Type Passed
            if (isset($propertyType) && (!empty($propertyType) || $propertyType == 0)) {
                $propertyQuery = $propertyQuery->where('propery_type', $propertyType);
            }

            // If Category Id is Passed
            if (isset($categoryId) && !empty($categoryId)) {
                $propertyQuery = $propertyQuery->where('category_id', $categoryId);
            }

            // If Status is passed (0/1), allow filtering on status
            if ($isAiEnabled == 0 && isset($filters['search']) && $filters['search'] !== '') {
                $propertyQuery = $propertyQuery->where(function ($whereCondition) use ($search) {
                    $whereCondition->where('title', 'like', '%' . $search . '%')
                        ->orWhere('address', 'like', '%' . $search . '%')
                        ->orWhereHas('category', function ($query) use ($search) {
                            $query->where('category', 'like', '%' . $search . '%');
                        })
                        // Use the global scope to search in translations with language filtering
                        ->orWhere(function ($query) use ($search) {
                            $query->searchInAnyTranslation($search);
                        });
                });
            }

            // If parameter id passed
            if (isset($parameters) && !empty($parameters)) {
                foreach ($parameters as $parameter) {
                    $parameterId = $parameter['id'];
                    $propertyQuery = $propertyQuery->whereHas('assignParameter', function ($query) use ($parameterId) {
                        $query->where('parameter_id', $parameterId)
                            ->where(function ($q) {
                                $q->whereNotNull('value')
                                    ->orWhere('value', '!=', '')
                                    ->orWhere('value', '!=', "null");
                            });
                    });

                    // if((isset($parameter['value']) && !empty($parameter['value']) || (isset($parameter['values']) && !empty($parameter['values'])))){
                    //     $parameterValue = explode(",",$parameter['value'] ?? $parameter['values']);
                    //     if(!empty($parameterValue)){
                    //         $propertyQuery = $propertyQuery->whereHas('assignParameter',function($query) use($parameterId,$parameterValue){
                    //             $query->where('parameter_id',$parameterId)->whereIn('value',$parameterValue);
                    //         });
                    //     }
                    // }
                }
            }

            if (isset($nearbyPlaces) && !empty($nearbyPlaces)) {
                foreach ($nearbyPlaces as $nearbyPlace) {
                    $nearbyPlaceId = $nearbyPlace['id'];
                    $nearbyPlaceValue = $nearbyPlace['value'];
                    if (isset($nearbyPlace['value']) && !empty($nearbyPlace['value'])) {
                        $propertyQuery = $propertyQuery->whereHas('assignfacilities', function ($query) use ($nearbyPlaceId, $nearbyPlaceValue) {
                            $query->where('facility_id', $nearbyPlaceId)->where('distance', '<=', $nearbyPlaceValue);
                        });
                    } else {
                        $propertyQuery = $propertyQuery->whereHas('assignfacilities', function ($query) use ($nearbyPlaceId) {
                            $query->where('facility_id', $nearbyPlaceId);
                        });
                    }
                }
            }

            // If Title is passed
            if (isset($title) && !empty($title)) {
                $propertyQuery = $propertyQuery->where('title', 'like', '%' . $title . '%');
            }

            // If Category Slug is Passed
            if (isset($categorySlugId) && !empty($categorySlugId)) {
                $propertyQuery = $propertyQuery->whereHas('category', function ($query) use ($categorySlugId) {
                    $query->where('slug_id', $categorySlugId);
                });
            }

            // If Country is passed
            if (isset($country) && !empty($country)) {
                $propertyQuery = $propertyQuery->where('country', 'like', '%' . $country . '%');
            }

            // If State is passed
            if (isset($state) && !empty($state)) {
                $propertyQuery = $propertyQuery->where('state', 'like', '%' . $state . '%');
            }

            // If City is passed
            if (isset($city) && !empty(trim($city))) {
                $cleanCity = trim($city);
                $propertyQuery = $propertyQuery->where(function ($q) use ($cleanCity) {
                    $q->where('city', 'like', '%' . $cleanCity . '%')
                      ->orWhere('address', 'like', '%' . $cleanCity . '%')
                      ->orWhere('client_address', 'like', '%' . $cleanCity . '%');
                });
            }

            // If place ID is passed, resolve it to city name
            if (isset($placeId) && !empty($placeId)) {
                $locationData = $this->resolvePlaceIdToLocation($placeId);
                if ($locationData) {
                    if ($locationData['city']) {
                        $propertyQuery = $propertyQuery->where('city', $locationData['city']);
                    }
                    if ($locationData['state']) {
                        $propertyQuery = $propertyQuery->where('state', $locationData['state']);
                    }
                    if ($locationData['country']) {
                        $propertyQuery = $propertyQuery->where('country', $locationData['country']);
                    }
                }
            }

            // If Max Price And Min Price passed
            if (isset($minPrice) && !empty($minPrice)) {
                $propertyQuery = $propertyQuery->where('price', '>=', $minPrice);
            }

            if (isset($maxPrice) && !empty($maxPrice)) {
                $propertyQuery = $propertyQuery->where('price', '<=', $maxPrice);
            }

            // If Posted Since 0 or 1 is passed
            if (isset($postedSince) && !empty($postedSince)) {
                // 0 - Last Week (from today back to the same day last week)
                if ($postedSince == 0) {
                    $oneWeekAgo = Carbon::now()->subWeek()->startOfDay();
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->whereBetween('created_at', [$oneWeekAgo, $today]);
                }
                // 1 - Yesterday
                if ($postedSince == 1) {
                    $yesterdayDate = Carbon::yesterday();
                    $propertyQuery =  $propertyQuery->whereDate('created_at', $yesterdayDate);
                }

                // 2 - Last Month
                if ($postedSince == 2) {
                    $lastMonthDate = Carbon::now()->subMonth();
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->whereBetween('created_at', [$lastMonthDate, $today]);
                }

                // 3 - Last 3 Months
                if ($postedSince == 3) {
                    $lastThreeMonthsDate = Carbon::now()->subMonths(3);
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->whereBetween('created_at', [$lastThreeMonthsDate, $today]);
                }

                // 4 - Last 6 Months
                if ($postedSince == 4) {
                    $lastSixMonthsDate = Carbon::now()->subMonths(6);
                    $today = Carbon::now()->endOfDay();
                    $propertyQuery = $propertyQuery->whereBetween('created_at', [$lastSixMonthsDate, $today]);
                }
            }

            // IF Promoted Passed then show the data according to
            if (isset($promoted) && !empty($promoted) && $promoted == 1) {
                $propertyQuery = $propertyQuery->whereHas('advertisement', function ($query) {
                    $query->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // If get_all_premium_properties is passed then show the data according to
            if (isset($getPremiumProperties) && !empty($getPremiumProperties) && $getPremiumProperties == 1) {
                $propertyQuery = $propertyQuery->where('is_premium', 1);
            }

            // Add promoted_count and favourite_count for ordering
            $propertyQuery = $propertyQuery->withCount([
                'advertisement as promoted_count' => function ($query) {
                    $query->where('status', 0)
                        ->where('is_enable', 1)
                        ->where('for', 'property')
                        ->groupBy('property_id');
                }
            ])
                ->withCount('favourite');

            // Always group promoted properties first
            $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END');

            // Randomize promoted properties, order non-promoted by id descending
            // Using a large number minus id for non-promoted to achieve DESC order in ASC context
            if (isset($mostViewed) && !empty($mostViewed) && $mostViewed == 1) {
                // For most viewed: randomize promoted, order non-promoted by total_click DESC
                $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - total_click) END');
            }
            // If Most Liked Passed then show the property data with promoted-first and Favourite Count Descending
            else if (isset($mostLiked) && !empty($mostLiked) && $mostLiked == 1) {
                // For most liked: randomize promoted, order non-promoted by favourite_count DESC
                $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - favourite_count) END');
            } else {
                // Default: randomize promoted, order non-promoted by id DESC
                $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - id) END');
            }

            // Latitude and Longitude
            if (isset($latitude) && !empty($latitude) && isset($longitude) && !empty($longitude) && $latitude != "null" && $longitude != "null") {
                if (isset($range) && !empty($range) && $range != "null") {
                    // Get the distance from the latitude and longitude
                    $propertyQuery = $propertyQuery->selectRaw("
                            (6371 * acos(cos(radians($latitude))
                            * cos(radians(latitude))
                            * cos(radians(longitude) - radians($longitude))
                            + sin(radians($latitude))
                            * sin(radians(latitude)))) AS distance")
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->having('distance', '<', $range);
                } else {
                    $propertyQuery = $propertyQuery->where('latitude', $latitude)->where('longitude', $longitude);
                }
            }

            // Get total properties
            $totalProperties = $propertyQuery->clone()->count();

            // Get properties list data
            $propertiesData = $propertyQuery
                ->with('category:id,category,image,slug_id', 'category.translations', 'translations')
                ->addSelect('id', 'slug_id', 'propery_type', 'title_image', 'category_id', 'title', 'price', 'city', 'state', 'country', 'rentduration', 'added_by', 'is_premium', 'latitude', 'longitude', 'total_click')
                ->withCount('favourite')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($property) {
                    $property->promoted = $property->is_promoted;
                    $property->is_premium = $property->is_premium == 1 ? true : false;
                    $property->property_type = $property->propery_type;
                    $property->assign_facilities = $property->assign_facilities;
                    $property->parameters = $property->parameters;
                    if ($property->category) {
                        $property->category->translated_name = $property->category->translated_name;
                    }
                    $property->translated_title = $property->translated_title;
                    $property->translated_description = $property->translated_description;
                    unset($property->propery_type);
                    return $property;
                });



            $response = array(
                'error' => false,
                'total' => $totalProperties,
                'data' => $propertiesData,
                'message' => trans("Data Fetched Successfully")
            );
            return response()->json($response);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAgentVerificationFormFields(Request $request)
    {
        $data = VerifyCustomerForm::where('status', 'active')->with(['form_fields_values' => function ($query) {
            $query->with('translations')->select('id', 'verify_customer_form_id', 'value');
        }, 'translations'])->select('id', 'name', 'field_type')->get()->map(function ($item) {
            if ($item->form_fields_values) {
                $item->form_fields_values->map(function ($formValue) {
                    $formValue->translated_value = $formValue->translated_value;
                });
            }
            $item->translated_name = $item->translated_name;
            return $item;
        });

        if (collect($data)->isNotEmpty()) {
            ApiResponseService::successResponse("Data Fetched Successfully", $data, array(), 200);
        } else {
            ApiResponseService::successResponse("No Data Found");
        }
    }

    public function getAgentVerificationFormValues(Request $request)
    {
        $data = VerifyCustomer::where('user_id', Auth::user()->id)->with(['user' => function ($query) {
            $query->select('id', 'name', 'profile')->withCount(['property', 'projects']);
        }])->with(['verify_customer_values' => function ($query) {
            $query->with('verify_form:id,name,field_type', 'verify_form.form_fields_values:id,verify_customer_form_id,value')->select('id', 'verify_customer_id', 'verify_customer_form_id', 'value');
        }])->first();

        if (collect($data)->isNotEmpty()) {
            ApiResponseService::successResponse("Data Fetched Successfully", $data, array(), 200);
        } else {
            ApiResponseService::successResponse("No Data Found");
        }
    }

    public function applyAgentVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'form_fields'           => 'required|array',
            'form_fields.*.id'      => 'required|exists:verify_customer_forms,id',
            'form_fields.*.value'   => 'required',
        ], [
            'form_fields.*.id'      => ':positionth Form Field id is not valid',
            'form_fields.*.value'   => ':positionth Form Field Value is not valid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            // If Payload is empty then show Payload is empty
            if (empty($request->form_fields)) {
                ApiResponseService::validationError("Payload is empty");
            }

            // Update the status of Customer (User) to pending
            $verifyCustomer = VerifyCustomer::updateOrCreate(['user_id' => Auth::user()->id], ['status' => 'pending']);
            $addCustomerValues = array();

            // Loop on request data of form_fields
            foreach ($request->form_fields as $key => $form_fields) {
                if (isset($form_fields['value']) && !empty($form_fields['value'])) {
                    // Check the Value is File upload or not
                    if ($request->hasFile('form_fields.' . $key . '.value')) {
                        $file = $request->file('form_fields.' . $key . '.value'); // Get Request File
                        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp']; // Allowed Images Extensions
                        $allowedDocumentExtensions = ['doc', 'docx', 'pdf', 'txt']; // Allowed Documentation Extensions
                        $extension = $file->getClientOriginalExtension(); // Get Extension
                        // Check the extension and verify with allowed images or documents extensions
                        if (in_array($extension, $allowedImageExtensions) || in_array($extension, $allowedDocumentExtensions)) {
                            // Get Old form value
                            $oldFormValue = VerifyCustomerValue::where(['verify_customer_id' => $verifyCustomer->id, 'verify_customer_form_id' => $form_fields['id']])->with('verify_form:id,field_type')->first();
                            $path = config('global.AGENT_VERIFICATION_DOC_PATH');
                            if (!empty($oldFormValue)) {
                                FileService::delete($path, $oldFormValue->getRawOriginal('value'));
                            }
                            $imageName = FileService::compressAndUpload($file, $path);
                            $value = $imageName;
                        } else {
                            // If File is not allowed then show Invalid File Type : Allowed types are: jpg, jpeg, png, doc, docx, pdf, txt
                            ApiResponseService::validationError("Invalid File Type");
                        }
                    } else {
                        // Check the value other than File Upload
                        $formFieldQueryData = VerifyCustomerForm::where('id', $form_fields['id'])->first();
                        if ($formFieldQueryData->field_type == 'radio' || $formFieldQueryData->field_type == 'dropdown') {
                            $id = $form_fields['id'];
                            $value = $form_fields['value'];
                            // IF Field Type is Radio or Dropdown, then check its value with database stored options
                            $checkValueExists = VerifyCustomerFormValue::where(['verify_customer_form_id' => $id, 'value' => $value])->first();
                            if (collect($checkValueExists)->isEmpty()) {
                                ApiResponseService::validationError("No Form Value Found");
                            }
                        } else if ($formFieldQueryData->field_type == 'checkbox') {
                            // IF Field Type is Checkbox
                            $submittedValue = explode(',', $form_fields['value']); // Explode the Comma Separated Values
                            $encodedValueArray = array();
                            // Loop on the values and check its value with database stored options
                            foreach ($submittedValue as $key => $value) {
                                $encodedValue = $value;
                                $checkValueExists = VerifyCustomerFormValue::where(['verify_customer_form_id' => $form_fields['id'], 'value' => $encodedValue])->first();
                                if (collect($checkValueExists)->isEmpty()) {
                                    ApiResponseService::validationError("No Form Value Found");
                                }
                                $encodedValueArray[] = $encodedValue;
                            }
                            // Convert the value into json encode
                            $value = implode(',', $encodedValueArray);
                        } else {
                            // Get Value as it is for other field types
                            $value = $form_fields['value'];
                        }
                    }
                    // Create an array to upsert data
                    $addCustomerValues[] = array(
                        'verify_customer_id'        => $verifyCustomer->id,
                        'verify_customer_form_id'   => (int)$form_fields['id'],
                        'value'                     => $value,
                        'created_at'                => now(),
                        'updated_at'                => now()
                    );
                }
            }

            // If array is not empty then update or create in bulk
            if (!empty($addCustomerValues)) {
                VerifyCustomerValue::upsert($addCustomerValues, ['verify_customer_id', 'verify_customer_form_id'], ['value']);
            }


            // Send Notification to Admin
            $fcm_id = array();
            $user_data = User::select('fcm_id', 'name')->get();
            foreach ($user_data as $user) {
                array_push($fcm_id, $user->fcm_id);
            }

            if (!empty($fcm_id)) {
                $registrationIDs = $fcm_id;
                $fcmMsg = array(
                    'title' => 'Agent Verification Form Submitted',
                    'message' => 'Agent Verification Form Submitted',
                    'type' => 'agent_verification',
                    'body' => 'Agent Verification Form Submitted',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default'
                );
                send_push_notification($registrationIDs, $fcmMsg);
            }

            // Commit the changes and return response
            DB::commit();
            ApiResponseService::successResponse("Data Submitted Successfully");
        } catch (Exception $e) {
            DB::rollback();
            ApiResponseService::logErrorResponse($e, $e->getMessage(), "", true);
        }
    }

    public function calculateMortgageCalculator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'down_payment' => 'nullable|lt:loan_amount',
            'show_all_details' => 'nullable|in:1'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $loanAmount = $request->loan_amount; // Loan amount
            $downPayment = $request->down_payment; // Down payment
            $interestRate = $request->interest_rate; // Annual interest rate in percentage
            $loanTermYear = $request->loan_term_years; // Loan term in years
            $showAllDetails = 0;
            if ($request->show_all_details == 1) {
                if (Auth::guard('sanctum')->check()) {
                    $packageLimit = HelperService::checkPackageLimit(config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.TYPE'));
                    if ($packageLimit == true) {
                        $showAllDetails = 1;
                    }
                }
            }

            $schedule = $this->mortgageCalculation($loanAmount, $downPayment, $interestRate, $loanTermYear, $showAllDetails);
            ApiResponseService::successResponse("Data Fetched Successfully", $schedule, [], 200);
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, $e->getMessage());
        }
    }
    public function getProjectDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required_without:slug_id|exists:projects,id',
            'slug_id' => 'required_without:id|exists:projects,slug_id',
            'get_similar' => 'nullable|in:1'
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $isPremium = 0;
            if ($request->id) {
                $isPremium = Projects::where('id', $request->id)->value('is_premium');
            } else if ($request->slug_id) {
                $isPremium = Projects::where('slug_id', $request->slug_id)->value('is_premium');
            }

            if ($isPremium) {
                if (Auth::guard('sanctum')->check()) {
                    HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'));
                } else {
                    ApiResponseService::errorResponse('Please Login to see premium project details', null, config('constants.RESPONSE_CODE.UNAUTHORIZED'));
                }
            }

            $getSimilarProjects = array();
            $project = Projects::with(['customer' => function ($query) {
                $query->select('id', 'name', 'profile', 'email', 'mobile', 'address', 'slug_id')->withCount([
                    'projects' => function ($subQuery) {
                        $subQuery->onlyActive();
                    },
                    'property' => function ($subQuery) {
                        $subQuery->onlyActive();
                    }
                ]);
            }])
                ->with('gallary_images')
                ->with('documents')
                ->with('plans')
                ->with('category:id,category,image')
                ->with('category.translations', 'translations')
                ->where(function ($query) {
                    $query->where(['request_status' => 'approved', 'status' => 1]);
                });

            if ($request->get_similar == 1) {
                $similarProjectMapper = function ($item) {
                    if ($item->category) {
                        $item->category->translated_name = $item->category->translated_name;
                    }
                    $item->translated_title = $item->translated_title;
                    $item->translated_description = $item->translated_description;
                    return $item;
                };
                $similarProjectQuery = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location', 'category_id', 'added_by', 'request_status')->where(function ($query) {
                    $query->where(['request_status' => 'approved', 'status' => 1]);
                })->with('category:id,category,image')->with('category.translations', 'translations');
                if ($request->has('id') && !empty($request->id)) {
                    $getSimilarProjects = $similarProjectQuery->clone()->where('id', '!=', $request->id)->get()->map($similarProjectMapper);
                } else if ($request->has('slug_id') && !empty($request->slug_id)) {
                    $getSimilarProjects = $similarProjectQuery->clone()->where('slug_id', '!=', $request->slug_id)->get()->map($similarProjectMapper);
                }
            }

            if ($request->id) {
                $project = $project->clone()->where('id', $request->id);
                HelperService::incrementTotalClick('project', $request->id);
            }

            if ($request->slug_id) {
                $project = $project->clone()->where('slug_id', $request->slug_id);
                HelperService::incrementTotalClick('project', null, $request->slug_id);
            }

            $total = $project->clone()->count();
            $data = $project->first();

            if (!empty($data)) {
                if ($data->category) {
                    $data->category->translated_name = $data->category->translated_name;
                }
                $data->translated_title = $data->translated_title;
                $data->translated_description = $data->translated_description;


                if ($data->is_admin_listing == 1) {
                    $adminCompanyTel1 = system_setting('company_tel1');
                    $adminEmail = system_setting('company_email');
                    $adminAddress = system_setting('company_address');
                    $adminData = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id')->first();
                    $totalPropertiesOfAdmin = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); })->count();
                    $totalProjectsOfAdmin = Projects::where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); })->count();

                    // Create modified customer data
                    $customCustomer = [
                        'id' => $adminData->id,
                        'name' => $adminData->name,
                        'slug_id' => $adminData->slug_id,
                        'profile' => !empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg'),
                        'mobile' => !empty($adminCompanyTel1) ? $adminCompanyTel1 : "",
                        'email' => !empty($adminEmail) ? $adminEmail : "",
                        'address' => !empty($adminAddress) ? $adminAddress : "",
                        'total_properties' => $totalPropertiesOfAdmin,
                        'total_projects' => $totalProjectsOfAdmin,
                        'total_properties_count' => $totalPropertiesOfAdmin,
                        'total_projects_count' => $totalProjectsOfAdmin
                    ];

                    // Force Laravel to include the modified customer data
                    $data->setRelation('customer', (object) $customCustomer);
                    $data->customer = (object) $customCustomer;
                } else {
                    $data->total_properties = $data->customer->properties_count;
                    $data->total_projects = $data->customer->projects_count;
                    $data->customer->is_user_verified = $data->customer->is_user_verified;
                }
            }



            ApiResponseService::successResponse(
                "Data Fetched Successfully",
                $data,
                array(
                    'total' => $total,
                    'similar_projects' => $getSimilarProjects
                )
            );
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAddedProjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'nullable',
                'in:under_construction,upcoming',
                'request_status' => 'nullable|in:pending,approved,rejected,expired',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $user = Auth::user();


            // Base query for selected columns
            $projectsQuery = Projects::where('added_by', $user->id)->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile', 'category.translations', 'translations', 'plans', 'documents');

            // Check if either id or slug_id is provided
            if ($request->filled('id') || $request->filled('slug_id')) {
                $specificProjectsQuery = $projectsQuery->clone()
                    ->where(function ($query) use ($request) {
                        $query->when($request->filled('id'), function ($query) use ($request) {
                            return $query->where('id', $request->id);
                        })
                            ->when($request->filled('slug_id'), function ($query) use ($request) {
                                return $query->orWhere('slug_id', $request->slug_id);
                            });
                    });
                $data = $specificProjectsQuery->clone()->first();
                if (collect($data)->isNotEmpty()) {
                    $data->posted_since = $data->created_at->diffForHumans();
                    if ($data->category) {
                        $data->category->translated_name = $data->category->translated_name;
                    }
                    $data->translated_title = $data->translated_title;
                    $data->translated_description = $data->translated_description;
                    if ($data->customer) {
                        $data->customer->is_user_verified = $data->customer->is_user_verified;
                    }
                }
                // Get Similar Projects
                if ($request->has('id')) {
                    $getSimilarProjects = $projectsQuery->clone()->where('id', '!=', $request->id)->get()->map(function ($project) {
                        $project->posted_since = $project->created_at->diffForHumans();
                        if ($project->category) {
                            $project->category->translated_name = $project->category->translated_name;
                        }
                        $project->translated_title = $project->translated_title;
                        $project->translated_description = $project->translated_description;
                        if ($project->customer) {
                            $project->customer->is_user_verified = $project->customer->is_user_verified;
                        }
                        return $project;
                    });
                } else if ($request->has('slug_id')) {
                    $getSimilarProjects = $projectsQuery->clone()->where('slug_id', '!=', $request->slug_id)->get()->map(function ($project) {
                        $project->posted_since = $project->created_at->diffForHumans();
                        if ($project->category) {
                            $project->category->translated_name = $project->category->translated_name;
                        }
                        $project->translated_title = $project->translated_title;
                        $project->translated_description = $project->translated_description;
                        if ($project->customer) {
                            $project->customer->is_user_verified = $project->customer->is_user_verified;
                        }
                        return $project;
                    });
                }

                ApiResponseService::successResponse("Data Fetched Successfully", $data, array('similar_projects' => $getSimilarProjects));
            } else {

                $offset = isset($request->offset) ? $request->offset : 0;
                $limit = isset($request->limit) ? $request->limit : 10;

                // If neither id nor slug_id is provided, use the base query for selected columns
                $projectsQuery = $projectsQuery->clone()
                    ->when($request->filled('type'), function ($query) use ($request) {
                        // IF Request Status is passed and status has approved or rejected or pending or all
                        return $query->where('type', $request->type);
                    })->when($request->filled('request_status'), function ($query) use ($request) {
                        // IF Request Status is passed and status has approved or rejected or pending or expired
                        if ($request->request_status == 'expired') {
                            return $query->whereNotNull('expiry_date')->where('expiry_date', '<', now());
                        }
                        return $query->where('request_status', $request->request_status);
                    })->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location', 'status', 'category_id', 'added_by', 'created_at', 'request_status', 'edit_reason', 'is_premium', 'expiry_date');
                // Get Total
                $total = $projectsQuery->clone()->count();

                // Get Data
                $data = $projectsQuery->clone()->take($limit)->skip($offset)->latest()->get()->map(function ($project) {
                    $project->reject_reason = (object)array();
                    if ($project->request_status == 'rejected') {
                        $project->reject_reason = $project->reject_reason()->select('id', 'project_id', 'reason', 'created_at')->latest()->first();
                    }
                    $project->posted_since = $project->created_at->diffForHumans();
                    if ($project->category) {
                        $project->category->translated_name = $project->category->translated_name;
                    }
                    $project->translated_title = $project->translated_title;
                    $project->translated_description = $project->translated_description;
                    if ($project->customer) {
                        $project->customer->is_user_verified = $project->customer->is_user_verified;
                    }
                    return $project;
                });
                ApiResponseService::successResponse("Data Fetched Successfully", $data, array('total' => $total));
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getProjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset'    => 'nullable|numeric',
                'limit'     => 'nullable|numeric',
                'filters'   => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            // Decode filters from base64 (same as getPropertyList)
            $filters = $request->filters;
            if (!empty($filters)) {
                $filters = base64_decode($filters);
                if (json_validate($filters)) {
                    $filters = json_decode($filters, true);
                } else {
                    $filters = array();
                }
            } else {
                $filters = array();
            }

            $filterValidator = Validator::make(
                collect($filters)->toArray(),
                [
                    'category_id'                       => 'nullable|exists:categories,id',
                    'location.country'                  => 'nullable',
                    'location.state'                    => 'nullable',
                    'location.city'                     => 'nullable',
                    'location.latitude'                 => 'nullable',
                    'location.longitude'                => 'nullable',
                    'location.range'                    => 'nullable',
                    'posted_since'                      => 'nullable|in:0,1,2,3,4',
                    'flags.promoted'                    => 'nullable',
                    'flags.most_views'                  => 'nullable',
                    'flags.most_liked'                  => 'nullable',
                    'flags.get_all_premium_properties'  => 'nullable',
                    'project_type'                      => 'nullable|in:0,1',
                ]
            );
            if ($filterValidator->fails()) {
                ApiResponseService::validationError($filterValidator->errors()->first());
            }

            // Get Offset and Limit
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit  = isset($request->limit)  ? $request->limit  : 10;

            // Get filter variables (same structure as getPropertyList)
            $categoryId         = isset($filters['category_id'])                        ? $filters['category_id']                       : null;
            $country            = isset($filters['location']['country'])                ? $filters['location']['country']               : null;
            $state              = isset($filters['location']['state'])                  ? $filters['location']['state']                 : null;
            $city               = isset($filters['location']['city'])                   ? $filters['location']['city']                  : null;
            $latitude           = isset($filters['location']['latitude'])               ? $filters['location']['latitude']              : null;
            $longitude          = isset($filters['location']['longitude'])              ? $filters['location']['longitude']             : null;
            $range              = isset($filters['location']['range'])                  ? $filters['location']['range']                 : null;
            $postedSince        = isset($filters['posted_since'])                       ? $filters['posted_since']                      : null;
            $promoted           = isset($filters['flags']['promoted'])                  ? $filters['flags']['promoted']                 : null;
            $getPremiumProjects = isset($filters['flags']['get_all_premium_properties']) ? $filters['flags']['get_all_premium_properties'] : null;
            $mostViewed         = isset($filters['flags']['most_views'])                ? $filters['flags']['most_views']               : null;
            $mostLiked          = isset($filters['flags']['most_liked'])                ? $filters['flags']['most_liked']               : null;
            $projectType        = isset($filters['project_type'])                      ? $filters['project_type']                      : null;

            // Also support legacy top-level slug_id / id params
            $projectSlugId = $request->has('slug_id') ? $request->slug_id : null;
            $projectId     = $request->has('id')      ? $request->id      : null;

            // Base query
            $projectsQuery = Projects::where(['request_status' => 'approved', 'status' => 1])
                ->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                })
                ->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile,slug_id', 'category.translations', 'translations')
                ->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'status', 'location', 'category_id', 'added_by', 'is_admin_listing', 'is_premium', 'request_status', 'meta_title', 'meta_description', 'meta_keywords', 'meta_image', 'total_click', 'expiry_date');

            // If Project Type is passed (0 = upcoming, 1 = under_construction)
            if (isset($projectType) && $projectType !== null) {
                $typeValue = $projectType == 0 ? 'upcoming' : 'under_construction';
                $projectsQuery = $projectsQuery->where('type', $typeValue);
            }

            // If Category ID is passed
            if (isset($categoryId) && !empty($categoryId)) {
                $projectsQuery = $projectsQuery->where('category_id', $categoryId);
            }

            // If Country is passed
            if (isset($country) && !empty($country)) {
                $projectsQuery = $projectsQuery->where('country', 'like', '%' . $country . '%');
            }

            // If State is passed
            if (isset($state) && !empty($state)) {
                $projectsQuery = $projectsQuery->where('state', 'like', '%' . $state . '%');
            }

            // If City is passed
            if (isset($city) && !empty($city)) {
                $projectsQuery = $projectsQuery->where('city', 'like', '%' . $city . '%');
            }

            // Latitude and Longitude
            if (isset($latitude) && !empty($latitude) && isset($longitude) && !empty($longitude) && $latitude != "null" && $longitude != "null") {
                if (isset($range) && !empty($range) && $range != "null") {
                    $projectsQuery = $projectsQuery->selectRaw("
                            (6371 * acos(cos(radians($latitude))
                            * cos(radians(latitude))
                            * cos(radians(longitude) - radians($longitude))
                            + sin(radians($latitude))
                            * sin(radians(latitude)))) AS distance")
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->having('distance', '<', $range);
                } else {
                    $projectsQuery = $projectsQuery->where('latitude', $latitude)->where('longitude', $longitude);
                }
            }

            // If Posted Since is passed
            if (isset($postedSince)) {
                if ($postedSince == 0) {
                    $projectsQuery = $projectsQuery->whereBetween(
                        'created_at',
                        [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                    );
                }
                if ($postedSince == 1) {
                    $projectsQuery = $projectsQuery->whereDate('created_at', Carbon::yesterday());
                }
                if ($postedSince == 2) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonth());
                }
                if ($postedSince == 3) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonths(3));
                }
                if ($postedSince == 4) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonths(6));
                }
            }

            // Legacy support for direct slug_id / id params
            if ($projectSlugId) {
                $projectsQuery = $projectsQuery->where('slug_id', $projectSlugId);
            }
            if ($projectId) {
                $projectsQuery = $projectsQuery->where('id', $projectId);
            }

            // Existing get_featured support
            if ($request->filled('get_featured') && $request->get_featured == 1) {
                $projectsQuery = $projectsQuery->whereHas('advertisement', function ($query) {
                    $query->where('for', 'project')->where('status', 0)->where('is_enable', 1);
                });
            }

            // If promoted is passed then show only projects that have active advertisements
            if (isset($promoted) && !empty($promoted) && $promoted == 1) {
                $projectsQuery = $projectsQuery->whereHas('advertisement', function ($query) {
                    $query->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // If get_all_premium_properties is passed then show only premium projects
            if (isset($getPremiumProjects) && !empty($getPremiumProjects) && $getPremiumProjects == 1) {
                $projectsQuery = $projectsQuery->where('is_premium', 1);
            }

            // Add promoted_count for ordering
            $projectsQuery = $projectsQuery->withCount([
                'advertisement as promoted_count' => function ($query) {
                    $query->where('status', 0)
                        ->where('is_enable', 1)
                        ->where('for', 'project')
                        ->groupBy('project_id');
                }
            ]);

            // Always group promoted projects first
            $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END');

            // Randomize promoted, order non-promoted by chosen sort key
            if (isset($mostViewed) && !empty($mostViewed) && $mostViewed == 1) {
                // For most viewed: order non-promoted by total_click DESC
                $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - total_click) END');
            } elseif (isset($mostLiked) && !empty($mostLiked) && $mostLiked == 1) {
                // For most liked: order non-promoted by id DESC (projects have no favourites table)
                $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - id) END');
            } else {
                // Default: order non-promoted by id DESC
                $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - id) END');
            }

            // Get Total
            $total = $projectsQuery->clone()->count();

            // Get Admin Company Details
            $adminCompanyTel1 = system_setting('company_tel1');
            $adminEmail = system_setting('company_email');
            $adminUser = User::where('id', 1)->select('id', 'slug_id')->first();

            // Get Data
            $data = $projectsQuery->clone()
                ->take($limit)
                ->skip($offset)
                ->get()
                ->map(function ($project) use ($adminCompanyTel1, $adminEmail, $adminUser) {
                    // Check if listing is by admin then add admin details in customer
                    if ($project->is_admin_listing == true) {
                        unset($project->customer);
                        $project->customer = array(
                            'name'     => "Admin",
                            'email'    => $adminEmail,
                            'mobile'   => $adminCompanyTel1,
                            'slug_id'  => $adminUser->slug_id
                        );
                    }
                    if ($project->category) {
                        $project->category->translated_name = $project->category->translated_name;
                    }
                    $project->translated_title = $project->translated_title;
                    $project->promoted  = $project->is_promoted;
                    $project->is_premium = $project->is_premium == 1 ? true : false;
                    return $project;
                });
            ApiResponseService::successResponse("Data Fetched Successfully", $data, array('total' => $total));
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function blockChatUser(Request $request)
    {
        $userId = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'to_user_id' => [
                'required_without:to_admin',
                'exists:customers,id',
                function ($attribute, $value, $fail) use ($userId) {
                    if ($value == $userId) {
                        $fail('You cannot block yourself.');
                    }
                }
            ],
            'to_admin' => 'required_without:to_user_id|in:1',
        ]);

        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $blockUserData = array(
                'by_user_id' => $userId,
                'reason' => $request->reason ?? null
            );
            if ($request->has('to_user_id') && !empty($request->to_user_id)) {
                $ifExtryExists = BlockedChatUser::where(['by_user_id' => $userId, 'user_id' => $request->to_user_id])->count();
                if ($ifExtryExists) {
                    ApiResponseService::validationError("User Already Blocked");
                }
                $blockUserData['user_id'] = $request->to_user_id;
            } else if ($request->has('to_admin') && $request->to_admin == 1) {
                $ifExtryExists = BlockedChatUser::where(['by_user_id' => $userId, 'admin' => 1])->count();
                if ($ifExtryExists) {
                    ApiResponseService::validationError("Admin Already Blocked");
                }
                $blockUserData['admin'] = 1;
            } else {
                ApiResponseService::errorResponse();
            }

            BlockedChatUser::create($blockUserData);
            ApiResponseService::successResponse("User Blocked Successfully");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function unBlockChatUser(Request $request)
    {
        $userId = Auth::user()->id;
        $validator = Validator::make($request->all(), [
            'to_user_id' => [
                'required_without:to_admin',
                'exists:customers,id',
                function ($attribute, $value, $fail) use ($userId) {
                    if ($value == $userId) {
                        $fail('You cannot unblock yourself.');
                    }
                }
            ],
            'to_admin' => 'required_without:to_user_id|in:1',
        ]);

        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            if ($request->has('to_user_id') && !empty($request->to_user_id)) {
                $blockedUserQuery = BlockedChatUser::where(['by_user_id' => $userId, 'user_id' => $request->to_user_id]);
                $ifExtryExists = $blockedUserQuery->clone()->count();
                if (!$ifExtryExists) {
                    ApiResponseService::validationError("No Blocked User Found");
                }
                $blockedUserQuery->delete();
            } else if ($request->has('to_admin') && $request->to_admin == 1) {
                $blockedUserQuery = BlockedChatUser::where(['by_user_id' => $userId, 'user_id' => $request->to_user_id]);
                $ifExtryExists = $blockedUserQuery->count();
                if (!$ifExtryExists) {
                    ApiResponseService::validationError("No Blocked User Found");
                }
                $blockedUserQuery->delete();
            } else {
                ApiResponseService::errorResponse();
            }
            ApiResponseService::successResponse("User Unblocked Successfully");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getPrivacyPolicy()
    {
        try {
            $privacyPolicyData = Setting::where('type', 'privacy_policy')->with('translations')->first();
            $arrayData = null;
            if (!empty($privacyPolicyData)) {
                $translatedData = $privacyPolicyData->translated_data;
                $arrayData = $privacyPolicyData->toArray();
                $arrayData['data'] = $translatedData;
                unset($arrayData['translations']);
                unset($arrayData['created_at']);
            }
            ApiResponseService::successResponse("Data Fetched Successfully", !empty($arrayData) ? $arrayData : "");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getTermsAndConditions()
    {
        try {
            $termsAndConditionsData = Setting::where('type', 'terms_conditions')->with('translations')->first();
            $arrayData = null;
            if (!empty($termsAndConditionsData)) {
                $translatedData = $termsAndConditionsData->translated_data;
                $arrayData = $termsAndConditionsData->toArray();
                $arrayData['data'] = $translatedData;
                unset($arrayData['translations']);
                unset($arrayData['created_at']);
            }
            ApiResponseService::successResponse("Data Fetched Successfully", !empty($arrayData) ? $arrayData : "");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAboutUs()
    {
        try {
            $aboutUsData = Setting::where('type', 'about_us')->with('translations')->first();
            $arrayData = null;
            if (!empty($aboutUsData)) {
                $translatedData = $aboutUsData->translated_data;
                $arrayData = $aboutUsData->toArray();
                $arrayData['data'] = $translatedData;
                unset($arrayData['translations']);
                unset($arrayData['created_at']);
            }
            ApiResponseService::successResponse("Data Fetched Successfully", !empty($arrayData) ? $arrayData : "");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function userRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'          => 'required|in:1,3',
            'firebase_id'   => 'required_if:type,1',
            'name'          => 'nullable',
            'mobile'        => 'required_if:type,1',
            'country_code'  => 'required_if:type,1',
            'email'         => 'required_if:type,3|email',
            'password'      => 'required|min:6',
            're_password'   => 'required|same:password',
        ], [
            'firebase_id.required_if'   => trans("Firebase ID is required if login type is number"),
            'name.required'             => trans("Name is required"),
            'email.required'            => trans("Email is required"),
            'email.email'               => trans("Email is invalid"),
            'password.required'         => trans("Password is required"),
            'password.min'              => trans("Password must be at least 6 characters long"),
            're_password.required'      => trans("Re-password is required"),
            're_password.same'          => trans("Re-password and password must match"),
            'type.required'             => trans("Type is required"),
            'type.in'                   => trans("Type is invalid"),
            'mobile.required_if'        => trans("Mobile is required"),
            'country_code.required_if'  => trans("Country code is required")
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            if ($request->type == 3) {
                $customerExists = Customer::where(['email' => $request->email, 'logintype' => 3])->count();
                if ($customerExists) {
                    ApiResponseService::validationError("User Already Exists");
                }
            } else {
                $customerExists = Customer::where(['mobile' => $request->mobile, 'country_code' => $request->country_code, 'logintype' => 1])->count();
                if ($customerExists) {
                    ApiResponseService::validationError("User Already Exists");
                }
            }
            $lastID = Customer::orderBy('id', 'desc')->first()?->id ?? 0;
            $authId = $request->type == 1 ? $request->firebase_id : Str::uuid()->toString();
            if (Customer::where('auth_id', $authId)->count()) {
                ApiResponseService::validationError("Auth ID / Firebase ID already exists");
            }
            $name = $request->has('name') && !empty($request->name) ? $request->name : 'User ' . $lastID + 1;
            $customerData = array(
                'name'          => $name,
                'email'         => $request->has('email') && !empty($request->email) ? $request->email : "",
                'mobile'        => $request->has('mobile') && !empty($request->mobile) ? str_replace(' ', '', $request->mobile) : null,
                'country_code'  => $request->has('country_code') && !empty($request->country_code) ? $request->country_code : null,
                'password'      => Hash::make($request->password),
                'auth_id'       => $authId,
                'slug_id'       => generateUniqueSlug($request->name, 5),
                'notification'  => 1,
                'isActive'      => 1,
                'logintype'     => $request->type == 1 ? 1 : 3,
                'mobile'        => $request->has('mobile') && !empty($request->mobile) ? $request->mobile : null,
                'country_code'  => $request->has('country_code') && !empty($request->country_code) ? $request->country_code : null,
            );
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
                $emailTypeData = HelperService::getEmailTemplatesTypes("welcome_mail");

                // Email Template
                $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
                $appName = env("APP_NAME") ?? "eBroker";
                $variables = array(
                    'app_name' => $appName,
                    'user_name' => !empty($request->name) ? $request->name : "$appName User",
                    'email' => $request->email,
                );
                if (empty($welcomeEmailTemplateData)) {
                    $welcomeEmailTemplateData = "Welcome to $appName";
                }
                $welcomeEmailTemplate = HelperService::replaceEmailVariables($welcomeEmailTemplateData, $variables);

                $data = array(
                    'email_template' => $welcomeEmailTemplate,
                    'email' => $request->email,
                    'title' => $emailTypeData['title'],
                );
                HelperService::sendMail($data, false, true);

                /** Send OTP mail for verification */
                // Get Data of email type
                $emailTypeData = HelperService::getEmailTemplatesTypes("verify_mail");

                // Email Template
                $propertyFeatureStatusTemplateData = system_setting($emailTypeData['type']);
                $appName = env("APP_NAME") ?? "eBroker";
                $variables = array(
                    'app_name' => $appName,
                    'otp' => $otp
                );
                if (empty($propertyFeatureStatusTemplateData)) {
                    $propertyFeatureStatusTemplateData = "Your OTP :- " . $otp;
                }
                $propertyFeatureStatusTemplate = HelperService::replaceEmailVariables($propertyFeatureStatusTemplateData, $variables);

                $data = array(
                    'email_template' => $propertyFeatureStatusTemplate,
                    'email' => $request->email,
                    'title' => $emailTypeData['title'],
                );
                HelperService::sendMail($data, false, true);
            } else {
                // IF login type is number, then send welcome mail
                if ($request->has('email') && !empty($request->email)) {
                    /** Register Mail */
                    // Get Data of email type
                    $emailTypeData = HelperService::getEmailTemplatesTypes("welcome_mail");
                    // Email Template
                    $welcomeEmailTemplateData = system_setting($emailTypeData['type']);
                    $appName = env("APP_NAME") ?? "eBroker";
                    $variables = array(
                        'app_name' => $appName,
                        'user_name' => !empty($request->name) ? $request->name : "$appName User",
                        'email' => $request->email,
                    );
                    if (empty($welcomeEmailTemplateData)) {
                        $welcomeEmailTemplateData = "Welcome to $appName";
                    }
                    $welcomeEmailTemplate = HelperService::replaceEmailVariables($welcomeEmailTemplateData, $variables);
                    $data = array(
                        'email_template' => $welcomeEmailTemplate,
                        'email' => $request->email,
                        'title' => $emailTypeData['title'],
                    );
                    HelperService::sendMail($data);
                }
            }
            DB::commit();
            ApiResponseService::successResponse("User Registered Successfully");
        } catch (Exception $e) {
            DB::rollback();
            if (Str::contains($e->getMessage(), [
                'Failed',
                'Mail',
                'Mailer',
                'MailManager',
                "Connection could not be established"
            ])) {
                ApiResponseService::validationError("There is issue with mail configuration, kindly contact admin regarding this");
            } else {
                ApiResponseService::errorResponse();
            }
        }
    }

    public function checkNumberPasswordExists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_code'  => 'required',
            'mobile'        => 'required',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Customer::where(['mobile' => $request->mobile, 'country_code' => $request->country_code, 'logintype' => 1])->first();
            if ($user) {
                if ($user->isActive == 0) {
                    ApiResponseService::validationError(trans("User is not active"));
                }
                if ($user->password) {
                    $data = array(
                        'user_exists' => true,
                        'password_exists' => true,
                    );
                    ApiResponseService::successResponse(trans("Password Exists"), $data);
                } else {
                    $data = array(
                        'user_exists' => true,
                        'password_exists' => false,
                    );
                    ApiResponseService::validationError(trans("Password Does Not Exist"), $data);
                }
            } else {
                $data = array(
                    'user_exists' => false,
                    'password_exists' => false,
                );
                ApiResponseService::validationError(trans("User Does Not Exist"), $data);
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function updateNumberPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile'        => 'required',
            'country_code'  => 'required',
            'password'      => 'required|min:6',
            're_password'   => 'required|same:password',
        ], [
            'mobile.required'        => trans("Mobile is required"),
            'country_code.required'  => trans("Country code is required"),
            'password.required'      => trans("Password is required"),
            'password.min'           => trans("Password must be at least 6 characters long"),
            're_password.required'   => trans("Re-password is required"),
            're_password.same'       => trans("Re-password and password must match"),
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Customer::where(['mobile' => $request->mobile, 'country_code' => $request->country_code, 'logintype' => 1])->first();
            if ($user) {
                $user->password = Hash::make($request->password);
                $user->save();
                ApiResponseService::successResponse("Password Updated Successfully");
            } else {
                ApiResponseService::validationError("User Not Found");
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function updateEmailPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'        => 'required',
            'password'      => 'required|min:6',
            're_password'   => 'required|same:password',
        ], [
            'email.required'         => trans("Email is required"),
            'password.required'      => trans("Password is required"),
            'password.min'           => trans("Password must be at least 6 characters long"),
            're_password.required'   => trans("Re-password is required"),
            're_password.same'       => trans("Re-password and password must match"),
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Customer::where(['email' => $request->email])->first();
            if ($user) {
                $user->password = Hash::make($request->password);
                $user->save();
                ApiResponseService::successResponse("Password Updated Successfully");
            } else {
                ApiResponseService::validationError("User Not Found");
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function changePropertyStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:propertys,id',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            // Get Query Data of property based on property id
            $propertyQueryData = Property::find($request->property_id);
            if ($propertyQueryData->request_status != 'approved') {
                ApiResponseService::validationError("Property is not approved");
            }
            // update user status
            $propertyQueryData->status = $request->status == 1 ? 1 : 0;
            $propertyQueryData->save();
            ApiResponseService::successResponse("Data Updated Successfully");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required'
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $isUserExists = Customer::where(['email' => $request->email, 'logintype' => 3])->count();
            if ($isUserExists) {
                $token = HelperService::generateToken();
                HelperService::storeToken($request->email, $token);

                $rootAdminUrl = env("APP_URL") ?? FacadesRequest::root();
                $trimmedEmail = ltrim($rootAdminUrl, '/'); // remove / from starting if exists
                $link = $trimmedEmail . "/reset-password?token=" . $token;
                $data = array(
                    'email' => $request->email,
                    'link' => $link
                );

                // Get Data of email type
                $emailTypeData = HelperService::getEmailTemplatesTypes("reset_password");

                // Email Template
                $verifyEmailTemplateData = system_setting("password_reset_mail_template");
                $variables = array(
                    'app_name' => env("APP_NAME") ?? "eBroker",
                    'email' => $request->email,
                    'link' => $link
                );
                if (empty($verifyEmailTemplateData)) {
                    $verifyEmailTemplateData = "Your reset password link is :- $link";
                }
                $verifyEmailTemplate = HelperService::replaceEmailVariables($verifyEmailTemplateData, $variables);

                $data = array(
                    'email_template' => $verifyEmailTemplate,
                    'email' => $request->email,
                    'title' => $emailTypeData['title'],
                );
                HelperService::sendMail($data, false, true);
                ApiResponseService::successResponse("Reset Link Sent Successfully");
            } else {
                ApiResponseService::validationError("No User Found");
            }
        } catch (Exception $e) {
            if (Str::contains($e->getMessage(), [
                'Failed',
                'Mail',
                'Mailer',
                "Connection could not be established"
            ])) {
                ApiResponseService::validationError("There is issue with mail configuration, kindly contact admin regarding this");
            } else {
                ApiResponseService::errorResponse();
            }
        }
    }

    public function changeProjectStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $loggedInUserID = Auth::user()->id;
            // Get Query Data of project based on project id
            $projectQuery = Projects::where('id', $request->project_id);
            $projectQueryData = $projectQuery->firstOrFail();
            if ($projectQueryData->added_by != $loggedInUserID) {
                ApiResponseService::validationError("Cannot change the status of project owned by others");
            }
            if ($projectQueryData->request_status != 'approved') {
                ApiResponseService::validationError("Project is not approved");
            }
            // update user status
            $projectQuery->update(['status' => $request->status == 1 ? 1 : 0]);
            ApiResponseService::successResponse("Data Updated Successfully");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getFeatures(Request $request)
    {
        try {
            $features = Feature::where('status', 1)->with('translations')->get()->map(function ($feature) {
                $feature->translated_name = $feature->translated_name;
                return $feature;
            });
            ApiResponseService::successResponse("Data Fetched Successfully", $features);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
    public function getPackages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform_type' => 'nullable|in:ios',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $packageQuery = Package::query();
            $filteredPackageQuery = $packageQuery->clone()->when($request->has('platform_type') && $request->platform_type == 'ios', function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('ios_product_id')->orWhere('package_type', 'free');
                });
            });

            $auth = Auth::guard('sanctum');
            $getActivePackages = array();
            $getAllActivePackageIds = array();

            if ($auth->check()) {
                $userId = $auth->user()->id;
                $getAllActivePackageIds = HelperService::getAllActivePackageIds($userId);

                if (!empty($getAllActivePackageIds)) {
                    $getActivePackages = UserPackage::whereIn('package_id', $getAllActivePackageIds)->where('user_id', $userId)
                        ->with('user_package_limits.package_feature.feature.translations')
                        ->with(['package' => function ($query) use ($userId) {
                            $query->with(['package_features' => function ($query) use ($userId) {
                                $query->with(['feature.translations', 'user_package_limits' => function ($subQuery) use ($userId) {
                                    $subQuery->whereHas('user_package', function ($userQuery) use ($userId) {
                                        $userQuery->where('user_id', $userId)->orderBy('id', 'desc');
                                    });
                                }]);
                            }, 'translations']);
                        }])
                        ->groupBy('package_id')
                        ->get()
                        ->map(function ($userPackage) {
                            return [
                                'id'                        => $userPackage->package->id,
                                'name'                      => $userPackage->package->name,
                                'package_type'              => $userPackage->package->package_type,
                                'ios_product_id'            => $userPackage->package->ios_product_id,
                                'price'                     => $userPackage->package->price,
                                'duration'                  => $userPackage->package->duration,
                                'start_date'                => $userPackage->start_date,
                                'end_date'                  => $userPackage->end_date,
                                'is_renewed'                => $userPackage->is_renewed,
                                'is_renew_allowed'          => $userPackage->is_renew_allowed,
                                'created_at'                => $userPackage->package->created_at,
                                'package_status'            => $userPackage->package->package_payment_status,
                                'payment_transaction_id'    => $userPackage->package->payment_transaction_id,
                                'translated_name'           => $userPackage->package->translated_name,
                                'features'                  => $userPackage->package->package_features->map(function ($pacakgeFeatures) {
                                    return [
                                        'id'                => $pacakgeFeatures->feature->id,
                                        'name'              => $pacakgeFeatures->feature->name,
                                        'translated_name'   => $pacakgeFeatures->feature->translated_name,
                                        'limit_type'        => $pacakgeFeatures->limit_type,
                                        'limit'             => $pacakgeFeatures->limit,
                                        'used_limit'        => $pacakgeFeatures->limit_type == 'unlimited' ? null : $pacakgeFeatures->user_package_limits->where('package_feature_id', $pacakgeFeatures->id)->first()->used_limit ?? 0,
                                        'total_limit'       => $pacakgeFeatures->limit_type == 'unlimited' ? null : $pacakgeFeatures->user_package_limits->where('package_feature_id', $pacakgeFeatures->id)->first()->total_limit ?? $pacakgeFeatures->limit
                                    ];
                                }),
                                'is_active' => 1
                            ];
                        });
                }
            }

            $getOtherPackagesQuery = $filteredPackageQuery->clone()->where('status', 1)->has('package_features');

            if (!empty($getAllActivePackageIds)) {
                $getOtherPackagesQuery = $getOtherPackagesQuery->whereNotIn('id', $getAllActivePackageIds);
            }

            $getOtherPackageData = $getOtherPackagesQuery->whereHas('package_features.feature', function ($query) {
                $query->where('status', 1);
            })->with(['package_features' => function ($query) {
                $query->with(['feature' => function ($query) {
                    $query->where('status', 1)->with('translations');
                }]);
            }, 'translations'])
                ->get()
                ->map(function ($package) {
                    if ($package->package_features) {
                        return [
                            'id'                        => $package->id,
                            'name'                      => $package->name,
                            'package_type'              => $package->package_type,
                            'price'                     => $package->price,
                            'ios_product_id'            => $package->ios_product_id,
                            'duration'                  => $package->duration,
                            'created_at'                => $package->created_at,
                            'package_status'            => $package->package_payment_status,
                            'payment_transaction_id'    => $package->payment_transaction_id,
                            'translated_name'           => $package->translated_name,
                            'features'                  => $package->package_features->map(function ($package_feature) {
                                return [
                                    'id'                => $package_feature->feature->id,
                                    'name'              => $package_feature->feature->name,
                                    'translated_name'   => $package_feature->feature->translated_name,
                                    'limit_type'        => $package_feature->limit_type,
                                    'limit'             => $package_feature->limit,
                                ];
                            }),
                        ];
                    }
                });
            $features = Feature::with('translations')->get()->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'translated_name' => $feature->translated_name,
                ];
            });

            $payAsYouGoPackages = \App\Models\PayAsYouGo::where('status', 1)->get();

            ApiResponseService::successResponse("Data Fetched Successfully", $getOtherPackageData, [
                'active_packages' => $getActivePackages,
                'all_features'    => $features,
                'pay_as_you_go'   => $payAsYouGoPackages
            ]);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id'        => 'required_without:pay_as_you_go_id',
            'pay_as_you_go_id'  => 'required_without:package_id',
            'payment_method'    => 'required|in:razorpay,paystack,stripe,flutterwave,paypal,cashfree,phonepe,midtrans',
            'platform_type'     => 'required|in:app,web'
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $paymentSettings = HelperService::getPaymentDetails($request->payment_method);
            if (empty($paymentSettings)) {
                ApiResponseService::validationError("None of payment method is activated");
            }

            $amount = 0;
            $itemName = "";
            $paymentTransactionData = null;

            if ($request->has('pay_as_you_go_id') && !empty($request->pay_as_you_go_id)) {
                $payAsYouGo = \App\Models\PayAsYouGo::where(['id' => $request->pay_as_you_go_id, 'status' => 1])->first();
                if (empty($payAsYouGo)) {
                    ApiResponseService::validationError("No active Pay As You Go package found");
                }
                
                $amount = $payAsYouGo->price;
                $itemName = $payAsYouGo->name;

                //Add Payment Data to Payment Transactions Table
                $paymentTransactionData = PaymentTransaction::create([
                    'user_id'         => $loggedInUserId,
                    'pay_as_you_go_id'=> $payAsYouGo->id,
                    'amount'          => $amount,
                    'payment_gateway' => Str::ucfirst($paymentSettings['payment_method']),
                    'payment_status'  => 'pending',
                    'order_id'        => null,
                    'payment_type'    => 'online payment'
                ]);

            } else {
                $package = Package::where(['id' => $request->package_id, 'package_type' => 'paid'])->first();
                if (empty($package)) {
                    ApiResponseService::validationError("No paid package found");
                }

                // Check if package is one_time and user already purchased it
                if ($package->purchase_type == 'one_time' && $this->checkUserPurchasedPackage($loggedInUserId, $package->id)) {
                    ApiResponseService::validationError("This package can only be purchased once");
                }

                // Check if user already has an active package
                $isAllFeatureLimitExits = $this->checkPackageLimitExists($loggedInUserId, $package->id);
                if ($isAllFeatureLimitExits == true) {
                    ApiResponseService::validationError("same package purchase in past have all features limits available");
                }

                $amount = $package->price;
                $itemName = $package->name;

                //Add Payment Data to Payment Transactions Table
                $paymentTransactionData = PaymentTransaction::create([
                    'user_id'         => $loggedInUserId,
                    'package_id'      => $package->id,
                    'amount'          => $amount,
                    'payment_gateway' => Str::ucfirst($paymentSettings['payment_method']),
                    'payment_status'  => 'pending',
                    'order_id'        => null,
                    'payment_type'    => 'online payment'
                ]);
            }


            $user = Auth::user();
            $phoneNumber = !empty($user->country_code) ? '+' . $user->country_code . $user->mobile : $user->mobile;
            $paymentIntent = PaymentService::create($paymentSettings)->createAndFormatPaymentIntent(round($amount, 2), [
                'payment_transaction_id' => $paymentTransactionData->id,
                'package_id'             => (string)($request->package_id ?? ''),
                'pay_as_you_go_id'       => (string)($request->pay_as_you_go_id ?? ''),
                'user_id'                => (string)$loggedInUserId,
                'email'                  => $user->email,
                'platform_type'          => $request->platform_type,
                'description'            => $request->description ?? $itemName,
                'user_name'              => $user->name ?? "",
                'address_line1'          => $user->address ?? "",
                'address_city'           => $user->city ?? "",
                'phone'                  => $phoneNumber,
            ]);
            $paymentTransactionData->update(['order_id' => $paymentIntent['id'] ?? null]);

            $paymentTransactionData = PaymentTransaction::findOrFail($paymentTransactionData->id);
            // Custom Array to Show as response
            $paymentGatewayDetails = array(
                ...$paymentIntent,
                'payment_transaction_id' => $paymentTransactionData->id,
            );

            DB::commit();
            ApiResponseService::successResponse("", ["payment_intent" => $paymentGatewayDetails, "payment_transaction" => $paymentTransactionData]);
        } catch (Throwable $e) {
            DB::rollBack();
            ApiResponseService::logErrorResponse($e);
            ApiResponseService::errorResponse();
        }
    }

    public function makePaymentTransactionFail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_transaction_id' => 'required|exists:payment_transactions,id',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            PaymentTransaction::where('id', $request->payment_transaction_id)->update(['payment_status' => 'failed']);
            ApiResponseService::successResponse("Data Updated Successfully");
        } catch (Throwable $e) {
            DB::rollBack();
            ApiResponseService::logErrorResponse($e);
            ApiResponseService::errorResponse();
        }
    }

    public function checkPackageLimit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:' . implode(',', array_column(config('constants.FEATURES'), 'TYPE')),
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $data = HelperService::checkPackageLimit($request->type, true, true);
            return ApiResponseService::successResponse('Data Fetched Successfully', $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse();
        }
    }

    /** Get Property And Project Featured */
    public function getFeaturedData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            $loggedInUserID = Auth::user()->id;
            $advertisementQuery = Advertisement::select('id', 'status', 'start_date', 'end_date', 'property_id', 'project_id');
            if ($request->type == 'property') {
                $advertisementQuery->whereHas('property', function ($query) use ($loggedInUserID) {
                    $query->where(['post_type' => 1, 'added_by' => $loggedInUserID]);
                })->with('property:id,category_id,slug_id,title,propery_type,city,state,country,price,title_image,is_premium,rentduration', 'property.category:id,category,image', 'property.translations');
            } else {
                $advertisementQuery->whereHas('project', function ($query) use ($loggedInUserID) {
                    $query->where(['added_by' => $loggedInUserID]);
                })->with('project:id,category_id,slug_id,title,type,city,state,country,image', 'project.category:id,category,image', 'project.translations');
            }

            $total = $advertisementQuery->count();
            $data = $advertisementQuery->take($limit)->skip($offset)->orderBy('id', 'DESC')->get()->map(function ($item) {
                if ($item->property) {
                    if ($item->property->category) {
                        $item->property->category->translated_name = $item->property->category->translated_name;
                    }
                    $item->property->translated_title = $item->property->translated_title;
                    $item->property->translated_description = $item->property->translated_description;
                    $item->property->is_premium = $item->property->is_premium == 1 ? true : false;
                    $item->property->property_type = $item->property->propery_type;
                }
                if ($item->project) {
                    $item->project->translated_title = $item->project->translated_title;
                    $item->project->translated_description = $item->project->translated_description;
                    if ($item->project->category) {
                        $item->project->category->translated_name = $item->project->category->translated_name;
                    }
                }
                return $item;
            });

            ApiResponseService::successResponse("Data Fetched Successfully", $data, array('total' => $total));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse();
        }
    }

    public function initiateBankTransaction(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_id' => 'required_if:pay_as_you_go_id,null|exists:packages,id',
                'pay_as_you_go_id' => 'required_without:package_id|exists:pay_as_you_gos,id',
                'file' => 'required|file|mimes:jpeg,png,jpg,pdf,doc,docx,webp|max:3072',
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;

            if ($request->has('pay_as_you_go_id') && !empty($request->pay_as_you_go_id)) {
                $payAsYouGo = PayAsYouGo::where(['id' => $request->pay_as_you_go_id, 'status' => 1])->first();
                if (empty($payAsYouGo)) {
                    ApiResponseService::validationError("No active Pay As You Go package found");
                }
                
                $amount = $payAsYouGo->price;
                $itemName = $payAsYouGo->name;

                //Add Payment Data to Payment Transactions Table
                $paymentTransactionData = PaymentTransaction::create([
                    'user_id'         => $loggedInUserId,
                    'pay_as_you_go_id'=> $payAsYouGo->id,
                    'amount'          => $amount,
                    'payment_gateway' => null,
                    'payment_status'  => 'review',
                    'order_id'        => Str::uuid(),
                    'payment_type'    => 'bank transfer'
                ]);
            }else{
                $packageData = Package::findOrFail($request->package_id);

                // Check for free packages to not allowed
                if ($packageData->package_type == 'free') {
                    ApiResponseService::validationError("No paid package found");
                }

                // Check if user has already paid for this package
                $paymentTransaction = PaymentTransaction::where(['user_id' => $loggedInUserId, 'package_id' => $packageData->id])->latest()->first();
                if (!empty($paymentTransaction) && ($paymentTransaction->payment_status == 'pending' || $paymentTransaction->payment_status == 'rejected' || $paymentTransaction->payment_status == 'review')) {
                    ApiResponseService::validationError("Last Transaction is not completed");
                }

                // Check if package is one_time and user already purchased it
                if ($packageData->purchase_type == 'one_time' && $this->checkUserPurchasedPackage($loggedInUserId, $packageData->id)) {
                    ApiResponseService::validationError("This package can only be purchased once");
                }

                $paymentTransactionData = PaymentTransaction::create([
                    'user_id'         => $loggedInUserId,
                    'package_id'      => $packageData->id,
                    'amount'          => $packageData->price,
                    'payment_gateway' => null,
                    'payment_status'  => 'review',
                    'order_id'        => Str::uuid(),
                    'payment_type'    => 'bank transfer'
                ]);

            }

            // Upload File
            $file = $request->file('file');
            $file = FileService::compressAndUpload($file, config('global.BANK_RECEIPT_FILE_PATH'));
            if (empty($file)) {
                ApiResponseService::validationError("File Upload Failed");
            }

            // Create Bank Receipt File
            $bankReceiptFile = BankReceiptFile::create([
                'payment_transaction_id' => $paymentTransactionData->id,
                'file' => $file,
            ]);
            $paymentTransactionData['bank_receipt_file'] = $bankReceiptFile->file;

            // Get Bank Details
            $bankDetailsFieldsQuery = system_setting('bank_details');
            if (isset($bankDetailsFieldsQuery) && !empty($bankDetailsFieldsQuery)) {
                $bankDetailsFields = json_decode($bankDetailsFieldsQuery, true);
            } else {
                $bankDetailsFields = [];
            }
            DB::commit();

            ResponseService::successResponse("Transaction Initiated Successfully", $paymentTransactionData, array('bank_details' => $bankDetailsFields));
        } catch (Exception $e) {
            DB::rollback();
            ApiResponseService::errorResponse();
        }
    }

    public function uploadBankReceiptFile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_transaction_id'    => 'required',
                'file'                      => 'required|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:3072',
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            // Check Payment Transaction
            $paymentTransaction = PaymentTransaction::findOrFail($request->payment_transaction_id);
            if (empty($paymentTransaction)) {
                ApiResponseService::validationError("Payment Transaction Not Found");
            }

            if ($paymentTransaction->payment_type != 'bank transfer') {
                ApiResponseService::validationError("Payment Transaction Type is not Bank Transfer");
            }

            // Check Payment Transaction Status
            if ($paymentTransaction->payment_status == 'review') {
                ApiResponseService::validationError("Your transaction is already in review");
            }

            PaymentTransaction::where('id', $request->payment_transaction_id)->update(['payment_status' => 'review']);

            // Upload File
            $file = $request->file('file');
            $file = FileService::compressAndUpload($file, config('global.BANK_RECEIPT_FILE_PATH'));

            // Create Bank Receipt File
            $bankReceiptFile = BankReceiptFile::create([
                'payment_transaction_id' => $request->payment_transaction_id,
                'file' => $file,
            ]);

            ApiResponseService::successResponse("File Uploaded Successfully", $bankReceiptFile);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getPaymentReceipt(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_transaction_id' => 'required|exists:payment_transactions,id',
            ]);

            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUserId = Auth::user()->id;
            $payment = PaymentTransaction::with(
                'package:id,name,duration,package_type',
                'customer:id,name,email,mobile'
            )->without('customer.tokens')->findOrFail($request->payment_transaction_id);
            if ($payment->user_id != $loggedInUserId) {
                ApiResponseService::validationError("You are not authorized to view this receipt");
            }

            // Only allow viewing receipts for successful payments
            if ($payment->payment_status !== 'success') {
                ApiResponseService::validationError("Receipt is only available for successful payments");
            }
            $receiptService = new PaymentReceiptService();
            return $receiptService->generateHTML($payment);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }


    public function compareProperties(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'source_property_id' => 'required|exists:propertys,id',
                'target_property_id' => 'required|exists:propertys,id',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $sourcePropertyId = $request->source_property_id;
            $targetPropertyId = $request->target_property_id;


            $propertyBaseQuery = Property::where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); })->select('id', 'category_id', 'title', 'city', 'state', 'country', 'address', 'price', 'propery_type', 'total_click', 'rentduration', 'is_premium', 'title_image')->with('category:id,slug_id,image,category', 'category.translations', 'translations');
            $sourceProperty = $propertyBaseQuery->clone()->where('id', $sourcePropertyId)->first();
            $targetProperty = $propertyBaseQuery->clone()->where('id', $targetPropertyId)->first();
            if (empty($sourceProperty)) {
                return ApiResponseService::errorResponse("Source property not found");
            }
            if (empty($targetProperty)) {
                return ApiResponseService::errorResponse("Target property not found");
            }


            if ($sourceProperty->category_id != $targetProperty->category_id) {
                return ApiResponseService::errorResponse("Properties are not in the same category");
            }
            if ($sourceProperty->id == $targetProperty->id) {
                return ApiResponseService::errorResponse("Source and target property cannot be the same");
            }
            if ($sourceProperty->is_premium == 1) {
                if (collect(Auth::guard('sanctum')->user())->isEmpty()) {
                    return ApiResponseService::errorResponse("Source property is a premium property");
                } else {
                    $data = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true);
                    if (($data['package_available'] == false || $data['feature_available'] == false) && $data['limit_available'] == false) {
                        ApiResponseService::validationError("Source property is a premium property", $data);
                    }
                }
            }
            if ($targetProperty->is_premium == 1) {
                if (collect(Auth::guard('sanctum')->user())->isEmpty()) {
                    return ApiResponseService::errorResponse('Target property is a premium property');
                } else {
                    $data = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true);
                    if (($data['package_available'] == false || $data['feature_available'] == false) && $data['limit_available'] == false) {
                        ApiResponseService::validationError("Target property is a premium property", $data);
                    }
                }
            }

            if (!$sourceProperty || !$targetProperty) {
                return ApiResponseService::errorResponse('One or both properties not found');
            }

            $sourcePropertyData = $this->getPropertyData($sourceProperty);
            $targetPropertyData = $this->getPropertyData($targetProperty);
            $data = array(
                'source_property' => $sourcePropertyData,
                'target_property' => $targetPropertyData
            );
            return ApiResponseService::successResponse("Properties compared successfully", $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }
    public function getAllSimilarProperties(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'property_id' => 'required|exists:propertys,id',
                'search' => 'nullable|string',
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 20;
            $getRequestProperty = Property::findOrFail($request->property_id);

            $getAllSimilarProperties = Property::where('id', '!=', $request->property_id)
                ->whereIn('propery_type', [0, 1])
                ->onlyActive()
                ->where('category_id', $getRequestProperty->category_id)
                ->select(
                    'id',
                    'slug_id',
                    'category_id',
                    'city',
                    'state',
                    'country',
                    'price',
                    'propery_type',
                    'title',
                    'title_image',
                    'is_premium',
                    'address',
                    'rentduration',
                    'latitude',
                    'longitude'
                )
                ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                ->when($request->has('search'), function ($query) use ($request) {
                    $query->where('title', 'like', '%' . $request->search . '%');
                })
                ->when($request->has('offset'), function ($query) use ($offset) {
                    $query->offset($offset);
                })
                ->when($request->has('limit'), function ($query) use ($limit) {
                    $query->limit($limit);
                })
                ->get()
                ->map(function ($propertyData) {
                    if ($propertyData->category) {
                        $propertyData->category->translated_name = $propertyData->category->translated_name;
                    }
                    $propertyData->translated_title = $propertyData->translated_title;
                    $propertyData->translated_description = $propertyData->translated_description;
                    $propertyData->promoted = $propertyData->is_promoted;
                    $propertyData->property_type = $propertyData->propery_type;
                    $propertyData->parameters = $propertyData->parameters;
                    $propertyData->is_premium = $propertyData->is_premium == 1;
                    return $propertyData;
                });
            return ApiResponseService::successResponse("Similar properties fetched successfully", $getAllSimilarProperties);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
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

    // Google Places: Autocomplete List Data
    public function getMapPlacesListData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'input' => 'required|string'
            ], [
                'input.required' => trans('Input is required')
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            $input = (string) $request->query('input', '');
            $service = new GooglePlacesService();
            $responseData = $service->autocomplete($input);
            return ApiResponseService::successResponse("Data Fetched Successfully", $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getPlacesForApp error: ' . $e->getMessage());
            return ApiResponseService::errorResponse();
        }
    }

    // Google Geocode/Place Details Data
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

            $service = new GooglePlacesService();
            $responseData = $service->detailsOrGeocode($placeId, $latitude, $longitude);
            ApiResponseService::successResponse("Data Fetched Successfully", $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getPlaceDetailsData error: ' . $e->getMessage());
            ApiResponseService::errorResponse();
        }
    }

    // Get property advance filter data
    public function propertyAdvanceFilterData()
    {
        try {
            $cacheKey = 'propertyAdvanceFilterData';
            $cachedData = Cache::get($cacheKey);
            if (!is_null($cachedData)) {
                return ApiResponseService::successResponse("Data Fetched Successfully", $cachedData);
            }

            // Get property ids
            $propertyQuery = Property::where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); })->whereIn('propery_type', [0, 1]);
            $propertyIds = $propertyQuery->pluck('id');

            // Get nearby facilities ids
            $nearbyFacilitiesId = AssignedOutdoorFacilities::whereIn('property_id', $propertyIds)->pluck('facility_id');

            // Get nearby facilities
            $nearbyFacilities = OutdoorFacilities::whereIn('id', $nearbyFacilitiesId)->with('translations')->get()->map(function ($nearbyFacility) {
                $nearbyFacility->translated_name = $nearbyFacility->translated_name;
                return $nearbyFacility;
            });

            // Get parameter ids of all categories
            $categoryIds = $propertyQuery->pluck('category_id');
            $facilititesIds = array();
            $facilitiesOfCategory = Category::whereIn('id', $categoryIds)->where('status', 1)->get()->pluck('parameter_types');
            foreach ($facilitiesOfCategory as $facility) {
                $facility = explode(',', $facility);
                $facility = array_filter($facility);
                $facility = array_unique($facility);
                $facility = array_values($facility);
                $facilititesIds = array_merge($facilititesIds, $facility);
            }

            // Get parameters
            $parameters = parameter::whereIn('id', $facilititesIds)->with('translations')->get()->map(function ($parameter) {
                $parameter->translated_name = $parameter->translated_name;
                $parameter->translated_option_value = $parameter->translated_option_value;
                return $parameter;
            });

            // Array of parameters and nearby facilities
            $propertyAdvanceFilterData = array(
                'parameters' => $parameters,
                'nearby_facilities' => $nearbyFacilities
            );

            // Cache the data
            Cache::put($cacheKey, $propertyAdvanceFilterData, now()->addMinute(10));
            ApiResponseService::successResponse("Data Fetched Successfully", $propertyAdvanceFilterData);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function storeBookingPreferences(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'meeting_duration_minutes'          => 'required|integer',
                'lead_time_minutes'                 => 'required|integer',
                'buffer_time_minutes'               => 'required|integer',
                'auto_confirm'                      => 'nullable|in:0,1',
                'cancel_reschedule_buffer_minutes'  => 'nullable|integer',
                'auto_cancel_after_minutes'         => 'required|integer',
                'auto_cancel_message'               => 'nullable|string',
                'daily_booking_limit'               => 'nullable|integer',
                'availability_types'                => 'nullable|string',
                'anti_spam_enabled'                 => 'nullable|boolean',
                'timezone'                          => 'nullable|string',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();   

            // Check if the user is an agent
            if (!$isAgent) {
                return ApiResponseService::validationError("You are not authorized to store booking preferences");
            }

            // Check if the provided timezone is valid
            if ($request->timezone && !empty($request->timezone)) {
                $timezone = $request->timezone;
                if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
                    return ApiResponseService::validationError(trans("Invalid timezone provided."));
                }
            }

            // Check if the availability types are valid
            if ($request->has('availability_types') && !empty($request->availability_types)) {
                $availabilityTypes = explode(',', $request->availability_types);
                $availabilityTypes = array_map('trim', $availabilityTypes);
                $availabilityValidator = Validator::make($availabilityTypes, [
                    '*' => 'in:phone,virtual,in_person',
                ], [
                    '*.in' => trans('Invalid availability type provided.'),
                ]);
                if ($availabilityValidator->fails()) {
                    return ApiResponseService::validationError($availabilityValidator->errors()->first());
                }
            }

            // Create booking preferences
            $bookingPreferencesData = array(
                'agent_id'                          => $loggedInUser->id,
                'meeting_duration_minutes'          => $request->meeting_duration_minutes,
                'lead_time_minutes'                 => $request->lead_time_minutes,
                'buffer_time_minutes'               => $request->buffer_time_minutes,
                'auto_confirm'                      => $request->auto_confirm,
                'cancel_reschedule_buffer_minutes'  => $request->cancel_reschedule_buffer_minutes,
                'auto_cancel_after_minutes'         => $request->auto_cancel_after_minutes,
                'auto_cancel_message'               => $request->auto_cancel_message,
                'daily_booking_limit'               => $request->daily_booking_limit,
                'availability_types'                => $request->availability_types,
                'anti_spam_enabled'                 => $request->anti_spam_enabled,
                'timezone'                          => $request->timezone,
            );
            AgentBookingPreference::upsert($bookingPreferencesData, ['agent_id'], ['agent_id', 'meeting_duration_minutes', 'lead_time_minutes', 'buffer_time_minutes', 'auto_confirm', 'cancel_reschedule_buffer_minutes', 'auto_cancel_after_minutes', 'auto_cancel_message', 'daily_booking_limit', 'availability_types', 'anti_spam_enabled', 'timezone']);
            $bookingPreferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();

            return ApiResponseService::successResponse("Booking preferences stored successfully", $bookingPreferences);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function setAgentTimeSchedule(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'schedule' => 'required_without:deleted_ids|nullable|array',
                'schedule.*.id' => 'nullable|integer|exists:agent_availabilities,id',
                'schedule.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'schedule.*.start_time' => 'required|date_format:H:i',
                'schedule.*.end_time' => 'required|date_format:H:i',
                'deleted_ids' => 'nullable|array',
                'deleted_ids.*' => 'nullable|integer|exists:agent_availabilities,id'
            ], [
                'schedule.*.id.integer' => trans('ID must be a number'),
                'schedule.*.id.exists' => trans('ID is not valid'),
                'schedule.*.day.required' => trans('Day is required'),
                'schedule.*.day.string' => trans('Day must be a string'),
                'schedule.*.day.in' => trans('Invalid day provided'),
                'schedule.*.start_time.required' => trans('Start time is required'),
                'schedule.*.start_time.date_format' => trans('Invalid start time format'),
                'schedule.*.end_time.required' => trans('End time is required'),
                'schedule.*.end_time.date_format' => trans('Invalid end time format'),
                'deleted_ids.array' => trans('Deleted slots IDs must be an array'),
                'deleted_ids.*.integer' => trans('Deleted slots IDs must be a number'),
                'deleted_ids.*.exists' => trans('Deleted slots IDs is not valid'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();

            $loggedInUser = Auth::user();

            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();   

            $agentTimeZone = $loggedInUser->getTimezone(true);

            // Check if the user is an agent
            if (!$isAgent) {
                return ApiResponseService::validationError("You are not authorized to set agent time schedule");
            }

            if ($request->has('schedule') && !empty($request->schedule)) {
                // Check if the schedule is valid
                $schedule = $request->schedule;

                // Validate for overlapping time slots within the same request
                $this->validateTimeSlotOverlaps($schedule);

                // Validate against existing time slots in database
                $this->validateExistingTimeSlotOverlaps($schedule, $loggedInUser, $request->deleted_ids ?? []);

                $schedule = array_map(function ($item) use ($loggedInUser, $agentTimeZone) {
                    return [
                        'agent_id' => $loggedInUser->id,
                        'id' => $item['id'] ?? null,
                        'day_of_week' => $item['day'],
                        'start_time' => Carbon::parse($item['start_time'], $agentTimeZone)->setTimezone('UTC')->toDateTimeString(),
                        'end_time' => Carbon::parse($item['end_time'], $agentTimeZone)->setTimezone('UTC')->toDateTimeString(),
                        'is_active' => 1,
                    ];
                }, $schedule);

                // Update or create schedule
                AgentAvailability::upsert($schedule, ['id'], ['agent_id', 'day_of_week', 'start_time', 'end_time', 'is_active']);
            }


            // Remove availabilities of agents by ids
            if ($request->has('deleted_ids') && !empty($request->deleted_ids)) {
                AgentAvailability::whereIn('id', $request->deleted_ids)->delete();
            }

            // Get Updated Agent schedule
            $agentSchedule = AgentAvailability::where('agent_id', $loggedInUser->id)
                ->get()
                ->groupBy('day_of_week')
                ->map(function ($items) use ($agentTimeZone) {
                    return $items->map(function ($item) use ($agentTimeZone) {
                        return [
                            'id' => $item->id,
                            'start_time' => Carbon::parse($item->start_time, 'UTC')->setTimezone($agentTimeZone)->format('H:i'),
                            'end_time'   => Carbon::parse($item->end_time, 'UTC')->setTimezone($agentTimeZone)->format('H:i'),
                        ];
                    });
                });

            DB::commit();
            return ApiResponseService::successResponse("Agent time schedule set successfully", $agentSchedule);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getMonthlyTimeSlots(Request $request)
    {
        try {
            $currentYear = date('Y');
            $validator = Validator::make($request->all(), [
                'month' => 'required|integer|min:1|max:12',
                'year'  => 'required|integer|min:' . $currentYear,
                'agent_id' => 'nullable|integer',
            ], [
                'month.integer' => trans('Month must be a number'),
                'month.min' => trans('Month must be between 1 and 12'),
                'month.max' => trans('Month must be between 1 and 12'),
                'year.integer' => trans('Year must be a number'),
                'year.min' => trans('Year must be greater than or equal to ' . $currentYear),
                'agent_id.integer' => trans('Agent ID must be a number'),
                'agent_id.exists' => trans('Agent ID is not valid'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            if ($request->has('month') && $request->has('year')) {
                $requestMonth = (int) $request->month;
                $requestYear = (int) $request->year;
                $currentMonth = (int) date('m');
                $currentYear = (int) date('Y');

                if ($requestYear >= $currentYear && $requestMonth < $currentMonth) {
                    return ApiResponseService::validationError("Month cannot be greater than current month for the current year.");
                }
            }

            $agentId = $request->agent_id;
            if ($agentId == 0) {
                $agentData = User::where('type', 0)->first();
                if (Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); })->whereIn('propery_type', [0, 1])->count() == 0) {
                    return ApiResponseService::validationError("Agent has no properties");
                }
            } else {
                $agentData = Customer::where('id', $agentId)->first();
                if ($agentData->isActive == 0) {
                    return ApiResponseService::validationError("Agent is not active");
                }
                if ($agentData->property()->whereIn('propery_type', [0, 1])->onlyActive()->count() == 0) {
                    return ApiResponseService::validationError("Agent has no properties");
                }
            }
            if (!$agentData) {
                return ApiResponseService::validationError("Agent not found");
            }
            if (isset($agentData->is_agent) && $agentData->is_agent == false) {
                return ApiResponseService::validationError("Agent not found");
            }

            $month = (int) $request->month;
            $year = (int) $request->year;

            // Preferences (buffer + timezone)
            if ($agentId == 0) {
                $bookingPref = AgentBookingPreference::where(['admin_id' => $agentData->id, 'is_admin_data' => 1])->first();
            } else {
                $bookingPref = AgentBookingPreference::where('agent_id', $agentId)->first();
            }
            $meetingDurationMinutes = (int) ($bookingPref->meeting_duration_minutes ?? 0);
            $availableMeetingTypes = $bookingPref->availability_types;
            $bufferMinutes = (int) ($bookingPref->buffer_time_minutes ?? 0);
            $agentTimezone = $bookingPref && $bookingPref->timezone ? $bookingPref->timezone : (config('app.timezone') ?? 'UTC');
            $leadTimeMinutes = max(0, (int) ($bookingPref->lead_time_minutes ?? 0));

            // Validate meeting duration and buffer values
            if ($meetingDurationMinutes <= 0) {
                return ApiResponseService::validationError("Meeting duration must be greater than 0 minutes.");
            }
            if ($bufferMinutes < 0) {
                $bufferMinutes = 0;
            }

            // Admin timezone for display
            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC';

            // Month bounds in agent timezone
            $monthStartTz = Carbon::create($year, $month, 1, 0, 0, 0, $agentTimezone)->startOfDay();
            $monthEndTz = (clone $monthStartTz)->endOfMonth()->endOfDay();

            // Admin timezone month bounds for keys
            $adminMonthStart = Carbon::create($year, $month, 1, 0, 0, 0, $adminTimezone)->startOfDay();
            $adminMonthEnd = (clone $adminMonthStart)->endOfMonth()->endOfDay();

            if ($agentId == 0) {
                $weeklyAvailability = AgentAvailability::where('admin_id', $agentData->id)
                    ->where('is_active', 1)
                    ->where('is_admin_data', 1)
                    ->get()
                    ->groupBy('day_of_week');
            } else {
                // Load weekly availability (grouped by day_of_week)
                $weeklyAvailability = AgentAvailability::where('agent_id', $agentId)
                    ->where('is_active', 1)
                    ->get()
                    ->groupBy('day_of_week');
            }

            // Load extra time slots for the month (grouped by date)
            $monthStartDate = $monthStartTz->toDateString();
            $monthEndDate = $monthEndTz->toDateString();
            if ($agentId == 0) {
                $extraSlotsByDate = AgentExtraTimeSlot::where('admin_id', $agentData->id)
                    ->where('is_admin_data', 1)
                    ->whereBetween('date', [$monthStartDate, $monthEndDate])
                    ->get()
                    ->groupBy('date');
            } else {
                $extraSlotsByDate = AgentExtraTimeSlot::where('agent_id', $agentId)
                    ->whereBetween('date', [$monthStartDate, $monthEndDate])
                    ->get()
                    ->groupBy('date');
            }

            $daysSlots = [];
            // Pre-initialize all admin dates in the month to ensure presence of empty arrays
            $adminCursor = (clone $adminMonthStart);
            while ($adminCursor <= $adminMonthEnd) {
                $daysSlots[$adminCursor->toDateString()] = [];
                $adminCursor->addDay();
            }
            // Do not include past dates: start from today or monthStartTz, whichever is later
            if ($agentId == 0) {
                $todayAgentTz = Carbon::now($adminTimezone)->startOfDay();
            } else {
                $todayAgentTz = Carbon::now($agentTimezone)->startOfDay();
            }
            $cursor = $monthStartTz->greaterThan($todayAgentTz) ? (clone $monthStartTz) : $todayAgentTz;
            // Get the current time in agent timezone for comparison
            if ($agentId == 0) {
                $nowAgentTz = Carbon::now($adminTimezone);
            } else {
                $nowAgentTz = Carbon::now($agentTimezone);
            }
            $minStartAgent = (clone $nowAgentTz)->addMinutes($leadTimeMinutes);

            while ($cursor <= $monthEndTz) {
                $dateKey = $cursor->toDateString();
                $dayName = strtolower($cursor->englishDayOfWeek); // monday..sunday

                $windows = $weeklyAvailability->get($dayName, collect());

                // Build slots from weekly availability windows (if any)
                foreach ($windows as $w) {
                    if ($agentId == 0) {
                        $winStart = Carbon::parse($dateKey . ' ' . $w->start_time, 'UTC')->setTimezone($adminTimezone);
                        $winEnd = Carbon::parse($dateKey . ' ' . $w->end_time, 'UTC')->setTimezone($adminTimezone);
                    } else {
                        $winStart = Carbon::parse($dateKey . ' ' . $w->start_time, 'UTC')->setTimezone($agentTimezone);
                        $winEnd = Carbon::parse($dateKey . ' ' . $w->end_time, 'UTC')->setTimezone($agentTimezone);
                    }
                    if ($winEnd <= $winStart) {
                        continue;
                    }

                    $slotStart = (clone $winStart);
                    while (true) {
                        $slotEnd = (clone $slotStart)->addMinutes($meetingDurationMinutes);
                        if ($slotEnd > $winEnd) {
                            break;
                        }
                        // Skip slots that are in the past (end time must be after now)
                        if ($slotEnd <= $nowAgentTz) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                            continue;
                        }
                        // Enforce lead time: slot must start after now + lead time
                        if ($slotStart < $minStartAgent) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                            continue;
                        }
                        $slotStartAdmin = (clone $slotStart)->setTimezone($adminTimezone);
                        $slotEndAdmin = (clone $slotEnd)->setTimezone($adminTimezone);
                        $targetKey = $slotStartAdmin->toDateString();
                        if (!isset($daysSlots[$targetKey])) {
                            $daysSlots[$targetKey] = [];
                        }
                        $daysSlots[$targetKey][] = [
                            'start_time' => $slotStartAdmin->format('H:i'),
                            'end_time'   => $slotEndAdmin->format('H:i'),
                            'start_at'   => $slotStartAdmin->format('Y-m-d H:i:s'),
                            'end_at'     => $slotEndAdmin->format('Y-m-d H:i:s'),
                        ];
                        $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                    }
                }

                // Build slots from extra time windows (date-specific)
                $extraWindows = $extraSlotsByDate->get($dateKey, collect());
                foreach ($extraWindows as $ew) {
                    if ($agentId == 0) {
                        $ewStart = Carbon::parse($dateKey . ' ' . $ew->start_time, 'UTC')->setTimezone($adminTimezone);
                        $ewEnd = Carbon::parse($dateKey . ' ' . $ew->end_time, 'UTC')->setTimezone($adminTimezone);
                    } else {
                        $ewStart = Carbon::parse($dateKey . ' ' . $ew->start_time, 'UTC')->setTimezone($agentTimezone);
                        $ewEnd = Carbon::parse($dateKey . ' ' . $ew->end_time, 'UTC')->setTimezone($agentTimezone);
                    }
                    if ($ewEnd <= $ewStart) {
                        continue;
                    }

                    $slotStart = (clone $ewStart);
                    while (true) {
                        $slotEnd = (clone $slotStart)->addMinutes($meetingDurationMinutes);
                        if ($slotEnd > $ewEnd) {
                            break;
                        }
                        // Skip slots that are in the past (end time must be after now)
                        if ($slotEnd <= $nowAgentTz) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                            continue;
                        }
                        // Enforce lead time: slot must start after now + lead time
                        if ($slotStart < $minStartAgent) {
                            $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                            continue;
                        }
                        $slotStartAdmin = (clone $slotStart)->setTimezone($adminTimezone);
                        $slotEndAdmin = (clone $slotEnd)->setTimezone($adminTimezone);
                        $targetKey = $slotStartAdmin->toDateString();
                        if (!isset($daysSlots[$targetKey])) {
                            $daysSlots[$targetKey] = [];
                        }
                        $daysSlots[$targetKey][] = [
                            'start_time' => $slotStartAdmin->format('H:i'),
                            'end_time'   => $slotEndAdmin->format('H:i'),
                            'start_at'   => $slotStartAdmin->format('Y-m-d H:i:s'),
                            'end_at'     => $slotEndAdmin->format('Y-m-d H:i:s'),
                        ];
                        $slotStart = (clone $slotEnd)->addMinutes($bufferMinutes);
                    }
                }
                $cursor->addDay();
            }

            // Remove slots that overlap existing appointments (with buffer applied)
            $monthStartUtc = (clone $monthStartTz)->setTimezone('UTC')->toDateTimeString();
            $monthEndUtc = (clone $monthEndTz)->setTimezone('UTC')->toDateTimeString();
            if ($agentId == 0) {
                $appointments = Appointment::where(['is_admin_appointment' => 1, 'admin_id' => $agentData->id])
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $monthEndUtc)
                    ->where('end_at', '>', $monthStartUtc)
                    ->get();
            } else {
                $appointments = Appointment::where('agent_id', $agentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $monthEndUtc)
                    ->where('end_at', '>', $monthStartUtc)
                    ->get();
            }

            foreach ($daysSlots as $k => $list) {
                $filtered = [];
                foreach ($list as $s) {
                    // Convert slot (admin tz) to UTC and apply buffer window
                    $slotStartUtcWithBuffer = Carbon::parse($s['start_at'], $adminTimezone)->setTimezone('UTC')->subMinutes($bufferMinutes)->toDateTimeString();
                    $slotEndUtcWithBuffer = Carbon::parse($s['end_at'], $adminTimezone)->setTimezone('UTC')->addMinutes($bufferMinutes)->toDateTimeString();
                    $overlaps = false;
                    foreach ($appointments as $a) {
                        if ($a->start_at < $slotEndUtcWithBuffer && $a->end_at > $slotStartUtcWithBuffer) {
                            $overlaps = true;
                            break;
                        }
                    }
                    if (!$overlaps) {
                        $filtered[] = $s;
                    }
                }
                // Sort each day's slots
                usort($filtered, function ($a, $b) {
                    return strcmp($a['start_time'], $b['start_time']);
                });
                $daysSlots[$k] = $filtered;
            }

            $response = [
                'agent_id' => $agentId,
                'month' => $month,
                'year' => $year,
                'availability_types' => $availableMeetingTypes,
                'timezone' => $adminTimezone,
                'agent_timezone' => $agentTimezone,
                'days' => $daysSlots,
            ];

            return ApiResponseService::successResponse("Time slots fetched successfully", $response);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function checkAgentTimeAvailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_id'   => 'nullable|integer',
                'date'       => 'required|date_format:Y-m-d',
                'start_time' => 'required|date_format:H:i',
                'end_time'   => 'required|date_format:H:i',
            ], [
                'date.required' => trans('Date is required'),
                'date.date_format' => trans('Invalid date format, expected Y-m-d'),
                'start_time.required' => trans('Start time is required'),
                'start_time.date_format' => trans('Invalid start time format, expected H:i'),
                'end_time.required' => trans('End time is required'),
                'end_time.date_format' => trans('Invalid end time format, expected H:i'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC'; // Admin/site timezone for input interpretation if needed
            $loggedInUser = Auth::user();
            $requestedAgentId = $request->agent_id;
            if ($requestedAgentId == 0) {
                $agentId = User::where('type', 0)->first()->id ?? 0;
                $isAdminAppointment = true;
            } else {
                $isAdminAppointment = false;
                $agentId = $requestedAgentId;
            }

            if (!$isAdminAppointment) {
                $agentData = Customer::where('id', $agentId)->first();
                if (isset($agentData->is_agent) && $agentData->is_agent) {
                    $agentId = $agentData->id;
                } else {
                    return ApiResponseService::validationError(trans("Agent id passed is not valid"));
                }
            }

            // Booking preferences (meeting duration is required to compute slots)
            if (!$isAdminAppointment) {
                $bookingPref = AgentBookingPreference::where('agent_id', $agentId)->first();
                $agentTimezone = $bookingPref && $bookingPref->timezone ? $bookingPref->timezone : (config('app.timezone') ?? 'UTC');
            } else {
                $bookingPref = AgentBookingPreference::where(['is_admin_data' => 1, 'admin_id' => $agentId])->first();
                $agentTimezone = $adminTimezone;
            }
            $meetingDurationMinutes = (int) ($bookingPref->meeting_duration_minutes ?? 0);
            if ($meetingDurationMinutes <= 0) {
                return ApiResponseService::validationError(trans("Meeting duration must be configured for the agent."));
            }
            $bufferMinutes = max(0, (int) ($bookingPref->buffer_time_minutes ?? 0));
            $leadTimeMinutes = max(0, (int) ($bookingPref->lead_time_minutes ?? 0));

            $dateKey = $request->date; // Y-m-d
            $startTimeStr = $request->start_time; // H:i
            $endTimeStr = $request->end_time; // H:i

            // Build requested interval in agent TZ
            $slotStartAgent = Carbon::parse($dateKey . ' ' . $startTimeStr, $adminTimezone)->setTimezone($agentTimezone);
            $slotEndAgent = Carbon::parse($dateKey . ' ' . $endTimeStr, $adminTimezone)->setTimezone($agentTimezone);

            if ($slotEndAgent <= $slotStartAgent) {
                return ApiResponseService::validationError(trans("End time must be greater than start time"));
            }
            // Ensure requested interval equals exactly one meeting duration
            if ($slotStartAgent->diffInMinutes($slotEndAgent) !== $meetingDurationMinutes) {
                return ApiResponseService::validationError(trans("Invalid time duration"));
            }

            $dayName = strtolower($slotStartAgent->englishDayOfWeek);
            // Lead time barrier at query time
            $nowAgentTz = Carbon::now($agentTimezone);
            $minStartAgent = (clone $nowAgentTz)->addMinutes($leadTimeMinutes);

            // Load weekly availability windows for the day
            if (!$isAdminAppointment) {
                $windows = AgentAvailability::where('agent_id', $agentId)
                    ->where('is_active', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            } else {
                $windows = AgentAvailability::where(['is_admin_data' => 1, 'admin_id' => $agentId])
                    ->where('is_active', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            }

            // Build slots for the date from availability windows
            $availbleSlots = [];
            foreach ($windows as $w) {
                $winStart = Carbon::parse($dateKey . ' ' . $w->start_time, 'UTC')->setTimezone($agentTimezone);
                $winEnd = Carbon::parse($dateKey . ' ' . $w->end_time, 'UTC')->setTimezone($agentTimezone);
                if ($winEnd <= $winStart) {
                    continue;
                }

                $cursor = (clone $winStart);
                while (true) {
                    $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                    if ($candidateEnd > $winEnd) {
                        break;
                    }

                    $slotStartAdmin = (clone $cursor)->setTimezone($adminTimezone);
                    $slotEndAdmin = (clone $candidateEnd)->setTimezone($adminTimezone);

                    $availbleSlots[] = [
                        'start_agent' => (clone $cursor),
                        'end_agent'   => (clone $candidateEnd),
                        'start_time'  => $slotStartAdmin->format('H:i'),
                        'end_time'    => $slotEndAdmin->format('H:i'),
                        'start_at'    => $slotStartAdmin->format('Y-m-d H:i:s'),
                        'end_at'      => $slotEndAdmin->format('Y-m-d H:i:s'),
                    ];

                    $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                }
            }

            // Also include date-specific extra time windows for the agent.
            // Pull from requested date plus adjacent days to handle timezone spillover.
            $prevDate = Carbon::parse($dateKey, $adminTimezone)->subDay()->toDateString();
            $nextDate = Carbon::parse($dateKey, $adminTimezone)->addDay()->toDateString();
            $extraSlots = [];

            if ($requestedAgentId != 0) {
                $extraWindows = AgentExtraTimeSlot::where('agent_id', $agentId)
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            } else {
                $extraWindows = AgentExtraTimeSlot::where(['is_admin_data' => 1, 'admin_id' => $agentId])
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            }
            if (!empty($extraWindows)) {
                foreach ($extraWindows as $ew) {
                    // Build using the window's own stored date in agent TZ
                    $ewDate = $ew->date;
                    $ewStart = Carbon::parse($ewDate . ' ' . $ew->start_time, 'UTC')->setTimezone($agentTimezone);
                    $ewEnd = Carbon::parse($ewDate . ' ' . $ew->end_time, 'UTC')->setTimezone($agentTimezone);
                    if ($ewEnd <= $ewStart) {
                        continue;
                    }

                    $cursor = (clone $ewStart);
                    while (true) {
                        $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                        if ($candidateEnd > $ewEnd) {
                            break;
                        }

                        $slotStartAdmin = (clone $cursor);
                        $slotEndAdmin = (clone $candidateEnd);
                        // Constrain to requested admin date to avoid cross-day leakage
                        if ($slotStartAdmin->toDateString() !== $dateKey) {
                            $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                            continue;
                        }

                        $extraSlots[] = [
                            'start_agent' => (clone $cursor),
                            'end_agent'   => (clone $candidateEnd),
                            'start_time'  => $slotStartAdmin->format('H:i'),
                            'end_time'    => $slotEndAdmin->format('H:i'),
                            'start_at'    => $slotStartAdmin->format('Y-m-d H:i:s'),
                            'end_at'      => $slotEndAdmin->format('Y-m-d H:i:s'),
                        ];

                        $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                    }
                }
            }
            $slots = array_merge($availbleSlots, $extraSlots);
            // Filter slots according to start time in ascending order
            usort($slots, function ($a, $b) {
                return strcmp($a['start_time'], $b['start_time']);
            });
            // If no slots produced at all, outside schedule
            if (empty($slots)) {
                return ApiResponseService::successResponse("Unavailable", [
                    'available' => false,
                    'reason' => trans('No Slots available for selected date'),
                    'available_slots' => [],
                ]);
            }

            // Fetch unavailability and appointments for filtering
            // $unavailabilities = \App\Models\AgentUnavailability::where('agent_id', $requestedAgentId)
            //     ->where('date', $dateKey)
            //     ->get();

            $dayStartAgent = Carbon::parse($dateKey . ' 00:00:00', $agentTimezone);
            $dayEndAgent = Carbon::parse($dateKey . ' 23:59:59', $agentTimezone);
            $dayStartUtc = (clone $dayStartAgent)->setTimezone('UTC')->toDateTimeString();
            $dayEndUtc = (clone $dayEndAgent)->setTimezone('UTC')->toDateTimeString();

            if ($requestedAgentId != 0) {
                $appointments = Appointment::where('agent_id', $requestedAgentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            } else {
                $appointments = Appointment::where(['is_admin_appointment' => 1, 'admin_id' => $agentId])
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            }

            // Filter slots by unavailability and appointments (with buffer margins)
            $availableSlots = [];
            foreach ($slots as $s) {
                $sStartAgent = $s['start_agent'];
                $sEndAgent = $s['end_agent'];

                // Enforce lead time: slot must start after now + lead time
                if ($sStartAgent < $minStartAgent) {
                    continue;
                }

                // Unavailability filter
                $blockedByUnavailability = false;
                // foreach ($unavailabilities as $u){
                //     if ($u->unavailability_type === 'full_day') { $blockedByUnavailability = true; break; }
                //     if ($u->start_time && $u->end_time){
                //         $uStart = Carbon::parse($dateKey.' '.$u->start_time, $agentTimezone);
                //         $uEnd = Carbon::parse($dateKey.' '.$u->end_time, $agentTimezone);
                //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) { $blockedByUnavailability = true; break; }
                //     }
                // }
                if ($blockedByUnavailability) {
                    continue;
                }

                // Appointment overlap filter with buffer on both ends
                $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $blockedByAppointment = false;
                foreach ($appointments as $a) {
                    if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                        $blockedByAppointment = true;
                        break;
                    }
                }
                if ($blockedByAppointment) {
                    continue;
                }

                $availableSlots[] = $s;
            }

            // Determine if requested interval equals one of the available slots (compare admin-time HH:MM)
            $requestedStartAdmin = Carbon::parse($dateKey . ' ' . $startTimeStr, $adminTimezone);
            $requestedEndAdmin = Carbon::parse($dateKey . ' ' . $endTimeStr, $adminTimezone);
            $matched = false;
            foreach ($availableSlots as $s) {
                if ($s['start_time'] === $requestedStartAdmin->format('H:i') && $s['end_time'] === $requestedEndAdmin->format('H:i')) {
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                ApiResponseService::successResponse("Available", [
                    'available' => true,
                    'is_admin_agent' => $requestedAgentId == 0 ? true : false,
                    'agent_id' => $agentId,
                    'date' => $dateKey,
                    'start_time' => $startTimeStr,
                    'end_time' => $endTimeStr,
                    'duration_minutes' => $meetingDurationMinutes,
                    'buffer_minutes' => $bufferMinutes,
                    'agent_timezone' => $agentTimezone,
                    'admin_timezone' => $adminTimezone,
                    'slot_start_at_agent' => $slotStartAgent->format('Y-m-d H:i:s'),
                    'slot_end_at_agent' => $slotEndAgent->format('Y-m-d H:i:s'),
                ]);
            }
            // Not matched
            ApiResponseService::successResponse("Unavailable", [
                'available' => false,
                'reason' => trans('Please select a different time slot'),
                'available_slots' => array_map(function ($s) {
                    return ['start_time' => $s['start_time'], 'end_time' => $s['end_time']];
                }, $availableSlots),
            ]);

            // (old direct overlap logic replaced by slot-based check above)
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function addAgentUnavailability(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date'                      => 'required|date_format:Y-m-d',
                'type_of_unavailability'    => 'required|in:full_day,partial_day',
                'start_time'                => 'nullable|required_if:type_of_unavailability,partial_day|date_format:H:i',
                'end_time'                  => 'nullable|required_if:type_of_unavailability,partial_day|date_format:H:i|after:start_time',
                'reason'                    => 'nullable|string',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();   

            // Check if the user is an agent
            if (!$isAgent) {
                return ApiResponseService::validationError(trans("You are not authorized to add unavailability"));
            }
            // Resolve agent timezone from preferences (default UTC)
            $preferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();
            $agentTimezone = $preferences?->timezone ?: 'UTC';

            $date = $request->input('date');
            $type = $request->input('type_of_unavailability');
            $reason = $request->input('reason');

            if ($type === 'partial_day') {
                $startTimeStr = $request->input('start_time');
                $endTimeStr = $request->input('end_time');
                $startAgent = Carbon::parse($date . ' ' . $startTimeStr, $agentTimezone);
                $endAgent = Carbon::parse($date . ' ' . $endTimeStr, $agentTimezone);
                if ($endAgent <= $startAgent) {
                    return ApiResponseService::validationError(trans("End time must be greater than start time"));
                }
                $unavailabilityType = 'specific_time';
            } else {
                // full_day
                $startAgent = Carbon::parse($date . ' 00:00:00', $agentTimezone);
                $endAgent = Carbon::parse($date . ' 23:59:59', $agentTimezone);
                $unavailabilityType = 'full_day';
            }

            // Unavailability must be within the agent's active schedule for that weekday
            $weekday = strtolower(Carbon::parse($date)->format('l'));
            $hasSchedule = AgentAvailability::where('agent_id', $loggedInUser->id)
                ->where('day_of_week', $weekday)
                ->where('is_active', true)
                ->exists();
            if (!$hasSchedule) {
                return ApiResponseService::validationError(trans('No active schedule defined for this day'));
            }
            if ($unavailabilityType === 'full_day') {
                return ApiResponseService::validationError(trans('Unavailability must be within your scheduled hours'));
            }
            // For specific time, ensure it fits entirely inside a single schedule window
            $startHms = $startAgent->format('H:i:s');
            $endHms = $endAgent->format('H:i:s');
            $fitsInside = AgentAvailability::where('agent_id', $loggedInUser->id)
                ->where('day_of_week', $weekday)
                ->where('is_active', true)
                ->where('start_time', '<=', $startHms)
                ->where('end_time', '>=', $endHms)
                ->exists();
            if (!$fitsInside) {
                return ApiResponseService::validationError(trans('Unavailability must be within your schedule window'));
            }

            // Prevent duplicate/overlapping unavailability on the same date
            if ($unavailabilityType === 'full_day') {
                $exists = AgentUnavailability::where('agent_id', $loggedInUser->id)
                    ->where('date', $date)
                    ->exists();
                if ($exists) {
                    return ApiResponseService::validationError(trans('Unavailability already exists for this date'));
                }
            } else {
                $startHms = $startAgent->format('H:i:s');
                $endHms = $endAgent->format('H:i:s');
                $overlapExists = AgentUnavailability::where('agent_id', $loggedInUser->id)
                    ->where('date', $date)
                    ->where(function ($q) use ($startHms, $endHms) {
                        $q->where('unavailability_type', 'full_day')
                            ->orWhere(function ($qq) use ($startHms, $endHms) {
                                $qq->where('unavailability_type', 'specific_time')
                                    ->where('start_time', '<', $endHms)
                                    ->where('end_time', '>', $startHms);
                            });
                    })
                    ->exists();
                if ($overlapExists) {
                    return ApiResponseService::validationError(trans('Unavailability overlaps with an existing entry'));
                }
            }

            // Store unavailability
            $createdUnavailability = AgentUnavailability::create([
                'agent_id' => $loggedInUser->id,
                'date' => $date,
                'unavailability_type' => $unavailabilityType,
                'start_time' => ($unavailabilityType === 'specific_time') ? $startAgent->format('H:i:s') : null,
                'end_time' => ($unavailabilityType === 'specific_time') ? $endAgent->format('H:i:s') : null,
                'reason' => $reason,
            ]);

            // Build UTC window for overlap query
            $windowStartUtc = (clone $startAgent)->setTimezone('UTC')->toDateTimeString();
            $windowEndUtc = (clone $endAgent)->setTimezone('UTC')->toDateTimeString();

            // Determine default cancel reason from preferences
            $defaultCancelReason = $preferences?->auto_cancel_message ?: ($reason ?: trans('Agent unavailable'));

            // Find overlapping appointments to cancel
            $appointments = Appointment::where('agent_id', $loggedInUser->id)
                ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                ->where('start_at', '<', $windowEndUtc)
                ->where('end_at', '>', $windowStartUtc)
                ->get();

            $cancelledCount = 0;
            DB::beginTransaction();

            $cancelData = [];
            $appointmentIds = [];

            foreach ($appointments as $appt) {
                $cancelData[] = [
                    'appointment_id' => $appt->id,
                    'cancelled_by'   => 'agent',
                    'reason'         => $defaultCancelReason,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];

                $appointmentIds[] = $appt->id;
            }

            if (!empty($cancelData)) {
                // Bulk insert cancellations
                AppointmentCancellation::insert($cancelData);

                // Bulk update appointments to cancelled
                Appointment::whereIn('id', $appointmentIds)->update(['status' => 'cancelled']);
            }

            $cancelledCount = count($appointmentIds);

            DB::commit();

            $createdUnavailability->cancelled_appointments = $cancelledCount;

            ApiResponseService::successResponse(
                trans('Unavailability added successfully'),
                $createdUnavailability
            );
        } catch (Exception $e) {
            DB::rollBack();
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Deelte Unavailability Data
    public function deleteUnavailabilityData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'unavailability_id' => 'required|exists:agent_unavailabilities,id',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $unavailabilityId = $request->unavailability_id;
            $unavailability = AgentUnavailability::where('id', $unavailabilityId)->first();
            if (!$unavailability) {
                return ApiResponseService::validationError(trans('Unavailability not found'));
            }
            $unavailability->delete();
            return ApiResponseService::successResponse(trans('Unavailability deleted successfully'));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Agent Booking Preferences
    public function getAgentBookingPreferences()
    {
        try {
            $loggedInUser = Auth::user();
            // Check if the logged-in user is a verified user (submitted verification request & admin approved it)
            if (!VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists()) {
                return ApiResponseService::validationError(trans("You are not authorized to get agent booking preferences"));
            }
            $agentBookingPreferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();
            return ApiResponseService::successResponse(trans('Data fetched successfully'), $agentBookingPreferences);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Agent Time Schedules
    public function getAgentTimeSchedules()
    {
        try {
            $loggedInUser = Auth::user();
            // Check if the logged-in user is a verified user (submitted verification request & admin approved it)
            if (!VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists()) {
                return ApiResponseService::validationError(trans("You are not authorized to get agent time schedules"));
            }

            $agentId = $loggedInUser->id;
            $agentTimezone = $loggedInUser->getTimezone(true);

            // Get time schedules with appointment counts
            $agentTimeSchedules = AgentAvailability::where('agent_id', $agentId)->get()->map(function ($schedule) use ($agentId, $agentTimezone) {
                // Get appointments for this day of week and time slot
                $appointmentCount = $this->getAppointmentCountForTimeSlot($agentId, $schedule->day_of_week, $schedule->start_time, $schedule->end_time, $agentTimezone);
                $schedule->start_time = Carbon::parse($schedule->start_time, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $schedule->end_time = Carbon::parse($schedule->end_time, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $schedule->appointment_count = $appointmentCount;
                return $schedule;
            });

            // Get extra time slots with appointment counts (only future and today's slots)
            $today = now($agentTimezone)->format('Y-m-d');
            $extraTimeSlots = AgentExtraTimeSlot::where('agent_id', $agentId)
                ->where('date', '>=', $today)
                ->get()
                ->map(function ($slot) use ($agentId, $agentTimezone) {
                    // Get appointments for this specific date and time slot
                    $appointmentCount = $this->getAppointmentCountForExtraTimeSlot($agentId, $slot->date, $slot->start_time, $slot->end_time, $agentTimezone);
                    $slot->appointment_count = $appointmentCount;
                    return $slot;
                });

            $data = [
                'time_schedules' => $agentTimeSchedules,
                'extra_slots' => $extraTimeSlots,
            ];
            return ApiResponseService::successResponse(trans('Data fetched successfully'), $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Unavailability Data
    public function getUnavailabilityData()
    {
        try {
            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();
            if (!$isAgent) {
                return ApiResponseService::validationError(trans("You are not authorized to get unavailability data"));
            }
            $unavailabilityData = AgentUnavailability::where('agent_id', $loggedInUser->id)->get();
            return ApiResponseService::successResponse(trans('Data fetched successfully'), $unavailabilityData);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Add/Update Agent Extra Time Slots (Bulk Operations)
    public function manageAgentExtraTimeSlots(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            if (isset($loggedInUser->is_agent) && $loggedInUser->is_agent == false) {
                return ApiResponseService::validationError(trans("You are not authorized to manage extra time slots"));
            }

            $validator = Validator::make($request->all(), [
                'extra_time_slots' => 'required|array',
                'extra_time_slots.*.id' => 'nullable|integer|exists:agent_extra_time_slots,id',
                'extra_time_slots.*.date' => 'required|date_format:Y-m-d|after_or_equal:today',
                'extra_time_slots.*.start_time' => 'required|date_format:H:i',
                'extra_time_slots.*.end_time' => 'required|date_format:H:i|after:extra_time_slots.*.start_time',
                'extra_time_slots.*.reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();

            $extraTimeSlots = $request->extra_time_slots;
            $data = [];
            $errors = [];

            // First, validate all slots for conflicts within the batch
            for ($i = 0; $i < count($extraTimeSlots); $i++) {
                for ($j = $i + 1; $j < count($extraTimeSlots); $j++) {
                    $slot1 = $extraTimeSlots[$i];
                    $slot2 = $extraTimeSlots[$j];

                    if ($slot1['date'] === $slot2['date']) {
                        // Check if slots overlap (excluding the same slot if updating)
                        if (($slot1['start_time'] < $slot2['end_time']) && ($slot1['end_time'] > $slot2['start_time'])) {
                            // If both slots have IDs and they're the same, skip overlap check
                            if (!(isset($slot1['id']) && isset($slot2['id']) && $slot1['id'] == $slot2['id'])) {
                                $errors[] = "Slots " . ($i + 1) . " and " . ($j + 1) . " overlap on " . $slot1['date'];
                            }
                        }
                    }
                }
            }

            if (!empty($errors)) {
                return ApiResponseService::validationError("Batch validation failed: " . implode(', ', $errors));
            }

            // Process each slot
            foreach ($extraTimeSlots as $index => $slot) {
                try {
                    $slotId = $slot['id'] ?? null;
                    $date = $slot['date'];
                    $startTime = $slot['start_time'];
                    $endTime = $slot['end_time'];
                    $reason = $slot['reason'] ?? null;

                    // Validate time is not in the past
                    $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $startTime);
                    if ($startDateTime->lt(Carbon::now())) {
                        $errors[] = "Slot " . ($index + 1) . ": " . trans('You cannot add/update a time slot to a past time');
                        continue;
                    }

                    if ($slotId) {
                        // UPDATE LOGIC
                        $existingSlot = AgentExtraTimeSlot::where('id', $slotId)
                            ->where('agent_id', $loggedInUser->id)
                            ->first();

                        if (!$existingSlot) {
                            $errors[] = "Slot " . ($index + 1) . ": " . trans('Extra time slot not found or not authorized');
                            continue;
                        }

                        // Check for overlapping with existing extra time slots (excluding current slot)
                        $overlaps = AgentExtraTimeSlot::where('agent_id', $loggedInUser->id)
                            ->where('date', $date)
                            ->where('id', '!=', $slotId)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($overlaps) {
                            $errors[] = "Slot " . ($index + 1) . ": " . trans('Time slot overlaps with an existing slot');
                            continue;
                        }

                        // Check for overlapping with agent's base schedule
                        $weekday = strtolower(Carbon::parse($date)->format('l'));
                        $scheduleOverlap = AgentAvailability::where('agent_id', $loggedInUser->id)
                            ->where('day_of_week', $weekday)
                            ->where('is_active', true)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($scheduleOverlap) {
                            $errors[] = "Slot " . ($index + 1) . ": " . trans('Time slot overlaps with your schedule');
                            continue;
                        }

                        // Update the slot
                        $existingSlot->update([
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'reason' => $reason,
                        ]);
                        $data[] = $existingSlot;
                    } else {
                        // CREATE LOGIC
                        // Check for overlapping with existing extra time slots
                        $overlaps = AgentExtraTimeSlot::where('agent_id', $loggedInUser->id)
                            ->where('date', $date)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($overlaps) {
                            $errors[] = "Slot " . ($index + 1) . ": " . trans('Time slot overlaps with an existing slot');
                            continue;
                        }

                        // Check for overlapping with agent's base schedule
                        $weekday = strtolower(Carbon::parse($date)->format('l'));
                        $scheduleOverlap = AgentAvailability::where('agent_id', $loggedInUser->id)
                            ->where('day_of_week', $weekday)
                            ->where('is_active', true)
                            ->where(function ($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->exists();
                        if ($scheduleOverlap) {
                            $errors[] = "Slot " . ($index + 1) . ": " . trans('Time slot overlaps with your schedule');
                            continue;
                        }

                        // Create the slot
                        $agentExtraTimeSlot = AgentExtraTimeSlot::create([
                            'agent_id' => $loggedInUser->id,
                            'date' => $date,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'reason' => $reason,
                        ]);
                        $data[] = $agentExtraTimeSlot;
                    }
                } catch (Exception $e) {
                    $errors[] = "Slot " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            DB::commit();
            $totalProcessed = count($data);
            if ($totalProcessed == 0) {
                return ApiResponseService::validationError("No slots were processed. Issues: " . implode(', ', $errors));
            } elseif (!empty($errors)) {
                $message = trans('Some slots were processed successfully. Issues: ') . implode(', ', $errors);
                return ApiResponseService::successResponse($message, $data);
            } else {
                return ApiResponseService::successResponse('All extra time slots processed successfully', $data);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }


    // Delete Multiple Agent Extra Time Slots (Bulk Operation)
    public function deleteMultipleAgentExtraTimeSlots(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();   
            if (!$isAgent) {
                return ApiResponseService::validationError(trans("You are not authorized to delete extra time slots"));
            }

            $validator = Validator::make($request->all(), [
                'slot_ids' => 'required|array',
                'slot_ids.*' => 'required|integer|exists:agent_extra_time_slots,id',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            // Agent timezone and default cancel reason
            $preferences = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first();
            $agentTimezone = $preferences?->timezone ?: 'UTC';
            $defaultCancelReason = $preferences?->auto_cancel_message ?: trans('Extra time slot removed by agent');

            DB::beginTransaction();

            foreach ($request->slot_ids as $slotId) {
                try {
                    $slot = AgentExtraTimeSlot::where('id', $slotId)
                        ->where('agent_id', $loggedInUser->id)
                        ->first();

                    // Build slot window in UTC
                    $slotStartAgent = Carbon::parse($slot->date . ' ' . $slot->start_time, $agentTimezone);
                    $slotEndAgent = Carbon::parse($slot->date . ' ' . $slot->end_time, $agentTimezone);
                    $windowStartUtc = (clone $slotStartAgent)->setTimezone('UTC')->toDateTimeString();
                    $windowEndUtc = (clone $slotEndAgent)->setTimezone('UTC')->toDateTimeString();

                    // Find overlapping appointments to cancel
                    $appointments = Appointment::where('agent_id', $loggedInUser->id)
                        ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                        ->where('start_at', '<', $windowEndUtc)
                        ->where('end_at', '>', $windowStartUtc)
                        ->get();

                    $appointmentIds = [];
                    foreach ($appointments as $appointment) {
                        $appointmentIds[] = $appointment->id;

                        // Cancel the appointment
                        $appointment->update([
                            'status' => 'cancelled',
                            'last_status_updated_by' => 'agent'
                        ]);

                        // Record cancellation
                        AppointmentCancellation::create([
                            'appointment_id' => $appointment->id,
                            'cancelled_by' => 'agent',
                            'reason' => $defaultCancelReason
                        ]);
                    }

                    // Delete the slot
                    $slot->delete();
                } catch (Exception $e) {
                    $failedDeletions[] = "Slot ID {$slotId}: " . $e->getMessage();
                }
            }

            DB::commit();
            ResponseService::successResponse(trans('Extra time slots deleted successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Agent Extra Time Slots
    public function getAgentExtraTimeSlots()
    {
        try {
            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();      
            if (!$isAgent) {
                return ApiResponseService::validationError(trans("You are not authorized to get extra time slots"));
            }
            $extraTimeSlots = AgentExtraTimeSlot::where('agent_id', $loggedInUser->id)->get();
            return ApiResponseService::successResponse(trans('Data fetched successfully'), $extraTimeSlots);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Create Appointment Request
    public function createAppointment(Request $request)
    {
        try {
            // Admin/site timezone for inputs
            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC';
            $currentDate = Carbon::now()->setTimezone($adminTimezone)->toDateString();
            $currentTime = Carbon::now()->setTimezone($adminTimezone)->format('H:i');
            $validator = Validator::make($request->all(), [
                'property_id'   => 'required|integer|exists:propertys,id',
                'meeting_type'  => 'required|in:phone,virtual,in_person',
                'date'          => 'required|date_format:Y-m-d|after_or_equal:' . $currentDate,
                'start_time'    => 'required|date_format:H:i',
                'end_time'      => 'required|date_format:H:i|after:start_time',
                'notes'         => 'nullable|string',
            ], [
                'property_id.required' => trans('Property is required'),
                'property_id.integer' => trans('Invalid property id'),
                'property_id.exists' => trans('Property not found'),
                'meeting_type.required' => trans('Meeting type is required'),
                'meeting_type.in' => trans('Invalid meeting type'),
                'date.required' => trans('Date is required'),
                'date.date_format' => trans('Invalid date format, expected Y-m-d'),
                'date.after_or_equal' => trans('Date must be greater than or equal to current date'),
                'start_time.required' => trans('Start time is required'),
                'start_time.date_format' => trans('Invalid start time format, expected H:i'),
                'start_time.after_or_equal' => trans('Start time must be greater than or equal to current time'),
                'end_time.required' => trans('End time is required'),
                'end_time.date_format' => trans('Invalid end time format, expected H:i'),
                'end_time.after' => trans('End time must be greater than start time'),
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();

            if ($request->date == $currentDate) {
                $validator->after(function ($validator) use ($request, $adminTimezone, $currentDate, $currentTime) {
                    $inputDate = $request->input('date');
                    $startTime = $request->input('start_time');
                    if (!$inputDate || !$startTime) {
                        return;
                    }
                    $start = Carbon::createFromFormat('H:i', $startTime, $adminTimezone);
                    $nowTime = Carbon::createFromFormat('H:i', $currentTime, $adminTimezone);
                    if ($start->lt($nowTime)) {
                        $validator->errors()->add('start_time', trans('Start time must be greater than or equal to current time'));
                    }
                });
            }

            $loggedInUser = Auth::user();
            $userId = $loggedInUser->id;
            $dateKey = $request->date;
            $startTimeStr = $request->start_time;
            $endTimeStr = $request->end_time;

            // Resolve property and agent
            $property = Property::where('id', $request->property_id)->first();
            if (!$property) {
                return ApiResponseService::validationError(trans('Property not found'));
            }
            $isAdminAgent = $property->added_by == 0 ? true : false;
            if ($isAdminAgent) {
                $adminId = User::where('type', 0)->first()->id ?? 0;
                $agentId = $adminId;
            } else {
                $agentId = $property->added_by;
            }

            // Booking preferences (timezone)
            if ($isAdminAgent) {
                $bookingPref = AgentBookingPreference::where(['is_admin_data' => 1, 'admin_id' => $agentId])->first();
                $agentTimezone = $adminTimezone;
            } else {
                $bookingPref = AgentBookingPreference::where('agent_id', $agentId)->first();
                $agentTimezone = $bookingPref && $bookingPref->timezone ? $bookingPref->timezone : (config('app.timezone') ?? 'UTC');
            }
            $dailyLimit = $bookingPref && $bookingPref->daily_booking_limit ? $bookingPref->daily_booking_limit : 0;

            // Build requested interval in agent TZ
            $slotStartAgent = Carbon::parse($dateKey . ' ' . $startTimeStr, $adminTimezone)->setTimezone($agentTimezone);
            $slotEndAgent = Carbon::parse($dateKey . ' ' . $endTimeStr, $adminTimezone)->setTimezone($agentTimezone);

            // Create appointment in UTC
            $startUtc = (clone $slotStartAgent)->setTimezone('UTC')->toDateTimeString();
            $endUtc = (clone $slotEndAgent)->setTimezone('UTC')->toDateTimeString();

            // Check if agent is trying to create appointment for himself
            if (!$isAdminAgent && ($agentId == $loggedInUser->id)) {
                return ApiResponseService::validationError(trans('You are not allowed to create appointment for yourself'));
            }

            // Resolve agent
            if ($isAdminAgent) {
                $agent = User::where('id', $agentId)->first();
            } else {
                $agent = Customer::where('id', $agentId)->first();
            }
            if (!$agent) {
                return ApiResponseService::validationError(trans('Agent not found'));
            }

            // Check if user is blocked for appointments
            if ($isAdminAgent) {
                if (BlockedUserForAppointment::isUserBlocked($userId, null)) {
                    return ApiResponseService::validationError(trans('You are blocked from making appointments with this agent'));
                }
            } else {
                if (BlockedUserForAppointment::isUserBlocked($userId, $agentId)) {
                    return ApiResponseService::validationError(trans('You are blocked from making appointments with this agent'));
                }
            }

            // Check if user has reached daily limit
            if ($dailyLimit > 0) {
                $startOfDay = Carbon::parse($dateKey . ' ' . '00:00:00', $agentTimezone)->setTimezone('UTC')->toDateTimeString();
                $endOfDay = Carbon::parse($dateKey . ' ' . '23:59:59', $agentTimezone)->setTimezone('UTC')->toDateTimeString();
                DB::enableQueryLog();
                $dailyAppointmentCount = Appointment::whereBetween('start_at', [$startOfDay, $endOfDay])
                    ->when($isAdminAgent, function ($query) use ($agentId, $isAdminAgent) {
                        $query->where('admin_id', $agentId)
                            ->where('is_admin_appointment', $isAdminAgent);
                    }, function ($query) use ($agentId) {
                        $query->where('agent_id', $agentId);
                    })
                    ->whereNotIn('status', ['cancelled', 'auto_cancelled', 'pending'])
                    ->count();

                // If daily appointment count is greater than or equal to daily limit, return error
                if ($dailyAppointmentCount >= $dailyLimit) {
                    return ApiResponseService::validationError(
                        trans('Agent cannot accept more appointments for today')
                    );
                }
            }


            // Check for existing appointments
            if ($isAdminAgent) {
                // AppointmentQuery
                $appointmentQuery = Appointment::where([
                    'user_id' => $userId,
                    'is_admin_appointment' => 1,
                    'start_at' => $startUtc,
                    'end_at' => $endUtc,
                ])->whereNot('status', 'cancelled', 'auto_cancelled');

                // Check if user has appointment with same admin at same time
                $existingAppointmentWithSameAdmin = $appointmentQuery->clone()->where('admin_id', $agentId)->first();
                if ($existingAppointmentWithSameAdmin) {
                    return ApiResponseService::validationError(trans('Appointment already booked with this admin'));
                }

                // Check if user has appointment with any other admin at same time
                $existingAppointmentWithOtherAdmin = $appointmentQuery->clone()->where('admin_id', '!=', $agentId)->first();
                if ($existingAppointmentWithOtherAdmin) {
                    return ApiResponseService::validationError(trans('Appointment cannot be created as you already have an appointment with another admin at this time'));
                }
            } else {
                // Appointment Query
                $appointmentQuery = Appointment::where([
                    'is_admin_appointment' => 0,
                    'start_at' => $startUtc,
                    'end_at' => $endUtc,
                    'user_id' => $userId,
                ])->whereNot('status', 'cancelled');

                // Check if user has appointment with same agent at same time
                $existingAppointmentWithSameAgent = $appointmentQuery->clone()->where('agent_id', $agentId)->first();
                if ($existingAppointmentWithSameAgent) {
                    return ApiResponseService::validationError(trans('Appointment already booked with this agent'));
                }

                // Check if user has appointment with any other agent at same time
                $existingAppointmentWithOtherAgent = Appointment::where([
                    'is_admin_appointment' => 0,
                    'start_at' => $startUtc,
                    'end_at' => $endUtc
                ])->whereNot('status', 'cancelled')
                    ->where(function ($query) use ($userId, $agentId) {
                        $query->where('user_id', $userId)
                            ->orWhere('agent_id', $userId);
                    })
                    ->where('agent_id', '!=', $agentId)
                    ->where('user_id', '!=', $agentId)
                    ->first();

                if ($existingAppointmentWithOtherAgent) {
                    return ApiResponseService::validationError(trans('Appointment cannot be created as you already have an appointment with another agent at this time'));
                }
            }

            // Booking preferences (meeting duration/auto_confirm/timezone)
            $meetingDurationMinutes = (int) ($bookingPref->meeting_duration_minutes ?? 0);
            if ($meetingDurationMinutes <= 0) {
                return ApiResponseService::validationError(trans('Agent has not configured meeting duration'));
            }
            $bufferMinutes = max(0, (int) ($bookingPref->buffer_time_minutes ?? 0));
            $leadTimeMinutes = max(0, (int) ($bookingPref->lead_time_minutes ?? 0));
            $autoConfirm = (bool) ($bookingPref->auto_confirm ?? false);

            // Validate duration
            if ($slotEndAgent <= $slotStartAgent) {
                return ApiResponseService::validationError(trans('End time must be greater than start time'));
            }
            if ($slotStartAgent->diffInMinutes($slotEndAgent) !== $meetingDurationMinutes) {
                return ApiResponseService::validationError(trans('Invalid time duration'));
            }

            // Enforce lead time at booking time
            $nowAgentTz = Carbon::now($agentTimezone);
            $minStartAgent = (clone $nowAgentTz)->addMinutes($leadTimeMinutes);
            if ($slotStartAgent < $minStartAgent) {
                return ApiResponseService::validationError(trans('Selected time is too soon. Please choose a later time.'));
            }

            // Reuse availability algorithm from checkAgentTimeAvailability
            $availabilityRequest = new Request([
                'agent_id' => $agentId,
                'date' => $dateKey,
                'start_time' => $startTimeStr,
                'end_time' => $endTimeStr,
            ]);

            // Manually invoke availability logic
            $availabilityValidator = Validator::make($availabilityRequest->all(), [
                'agent_id'   => 'nullable|integer|exists:' . ($isAdminAgent ? 'users' : 'customers') . ',id',
                'date'       => 'required|date_format:Y-m-d',
                'start_time' => 'required|date_format:H:i',
                'end_time'   => 'required|date_format:H:i',
            ]);
            if ($availabilityValidator->fails()) {
                return ApiResponseService::validationError($availabilityValidator->errors()->first());
            }

            // Build all candidate slots similar to checkAgentTimeAvailability
            $dayName = strtolower($slotStartAgent->englishDayOfWeek);

            if ($isAdminAgent) {
                $windows = AgentAvailability::where('admin_id', $agentId)
                    ->where('is_active', 1)
                    ->where('is_admin_data', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            } else {
                $windows = AgentAvailability::where('agent_id', $agentId)
                    ->where('is_active', 1)
                    ->where('day_of_week', $dayName)
                    ->get();
            }

            $availbleAgentSlots = [];
            foreach ($windows as $w) {
                $winStart = Carbon::parse($dateKey . ' ' . $w->start_time, 'UTC')->setTimezone($agentTimezone);
                $winEnd = Carbon::parse($dateKey . ' ' . $w->end_time, 'UTC')->setTimezone($agentTimezone);
                if ($winEnd <= $winStart) {
                    continue;
                }

                $cursor = (clone $winStart);
                while (true) {
                    $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                    if ($candidateEnd > $winEnd) {
                        break;
                    }

                    $slotStartAdmin = (clone $cursor)->setTimezone($adminTimezone);
                    $slotEndAdmin = (clone $candidateEnd)->setTimezone($adminTimezone);
                    $availbleAgentSlots[] = [
                        'start_agent' => (clone $cursor),
                        'end_agent'   => (clone $candidateEnd),
                        'start_time'  => $slotStartAdmin->format('H:i'),
                        'end_time'    => $slotEndAdmin->format('H:i'),
                    ];
                    $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                }
            }

            // Include extra time windows limited to the specific admin date
            $prevDate = Carbon::parse($dateKey, $adminTimezone)->setTimezone('UTC')->subDay()->toDateString();
            $nextDate = Carbon::parse($dateKey, $adminTimezone)->setTimezone('UTC')->addDay()->toDateString();
            if ($isAdminAgent) {
                $extraWindows = AgentExtraTimeSlot::where('admin_id', $agentId)
                    ->where('is_admin_data', 1)
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            } else {
                $extraWindows = AgentExtraTimeSlot::where('agent_id', $agentId)
                    ->whereIn('date', [$prevDate, $dateKey, $nextDate])
                    ->get();
            }
            $extraSlots = [];

            foreach ($extraWindows as $ew) {
                $ewDate = $ew->date;
                $ewStart = Carbon::parse($ewDate . ' ' . $ew->start_time, 'UTC')->setTimezone($agentTimezone);
                $ewEnd = Carbon::parse($ewDate . ' ' . $ew->end_time, 'UTC')->setTimezone($agentTimezone);
                if ($ewEnd <= $ewStart) {
                    continue;
                }
                $cursor = (clone $ewStart);
                while (true) {
                    $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                    if ($candidateEnd > $ewEnd) {
                        break;
                    }
                    $slotStartAdmin = (clone $cursor)->setTimezone($adminTimezone);
                    if ($slotStartAdmin->toDateString() !== $dateKey) {
                        $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                        continue;
                    }
                    $slotEndAdmin = (clone $candidateEnd)->setTimezone($adminTimezone);
                    $extraSlots[] = [
                        'start_agent' => (clone $cursor),
                        'end_agent'   => (clone $candidateEnd),
                        'start_time'  => $slotStartAdmin->format('H:i'),
                        'end_time'    => $slotEndAdmin->format('H:i'),
                    ];
                    $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                }
            }
            $slots = array_merge($availbleAgentSlots, $extraSlots);
            // Filter slots according to start time in ascending order
            usort($slots, function ($a, $b) {
                return strcmp($a['start_time'], $b['start_time']);
            });

            if (empty($slots)) {
                return ApiResponseService::validationError(trans('Selected time is outside agent availability'), $slots);
            }

            // // Filter by unavailability and existing appointments
            // $unavailabilities = AgentUnavailability::where('agent_id', $agentId)
            //     ->where('date', $dateKey)
            //     ->get();

            $dayStartAgent = Carbon::parse($dateKey . ' 00:00:00', $agentTimezone);
            $dayEndAgent = Carbon::parse($dateKey . ' 23:59:59', $agentTimezone);
            $dayStartUtc = (clone $dayStartAgent)->setTimezone('UTC')->toDateTimeString();
            $dayEndUtc = (clone $dayEndAgent)->setTimezone('UTC')->toDateTimeString();
            if ($isAdminAgent) {
                $appointments = Appointment::where('admin_id', $agentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            } else {
                $appointments = Appointment::where('agent_id', $agentId)
                    ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                    ->where('start_at', '<', $dayEndUtc)
                    ->where('end_at', '>', $dayStartUtc)
                    ->get();
            }

            // Build list of available slots after applying unavailability and existing appointments
            $availableSlots = [];
            foreach ($slots as $s) {
                $sStartAgent = $s['start_agent'];
                $sEndAgent = $s['end_agent'];

                // Unavailability filter
                // $blockedByUnavailability = false;
                // foreach ($unavailabilities as $u){
                //     if ($u->unavailability_type === 'full_day') { $blockedByUnavailability = true; break; }
                //     if ($u->start_time && $u->end_time){
                //         $uStart = Carbon::parse($dateKey.' '.$u->start_time, $agentTimezone);
                //         $uEnd = Carbon::parse($dateKey.' '.$u->end_time, $agentTimezone);
                //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) { $blockedByUnavailability = true; break; }
                //     }
                // }
                // if ($blockedByUnavailability) { continue; }

                // Appointment overlap filter with buffer on both ends
                $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $blockedByAppointment = false;
                foreach ($appointments as $a) {
                    if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                        $blockedByAppointment = true;
                        break;
                    }
                }
                if ($blockedByAppointment) {
                    continue;
                }

                $availableSlots[] = [
                    'start_agent' => $sStartAgent,
                    'end_agent'   => $sEndAgent,
                    'start_time'  => $s['start_time'],
                    'end_time'    => $s['end_time'],
                ];
            }

            $available = false;
            $requestedStartAdmin = Carbon::parse($dateKey . ' ' . $startTimeStr, $adminTimezone);
            $requestedEndAdmin = Carbon::parse($dateKey . ' ' . $endTimeStr, $adminTimezone);

            foreach ($slots as $s) {
                if ($s['start_time'] !== $requestedStartAdmin->format('H:i') || $s['end_time'] !== $requestedEndAdmin->format('H:i')) {
                    continue;
                }

                $sStartAgent = $s['start_agent'];
                $sEndAgent = $s['end_agent'];

                // // Unavailability filter
                // $blockedByUnavailability = false;
                // foreach ($unavailabilities as $u){
                //     if ($u->unavailability_type === 'full_day') { $blockedByUnavailability = true; break; }
                //     if ($u->start_time && $u->end_time){
                //         $uStart = Carbon::parse($dateKey.' '.$u->start_time, $agentTimezone);
                //         $uEnd = Carbon::parse($dateKey.' '.$u->end_time, $agentTimezone);
                //         if ($sStartAgent < $uEnd && $sEndAgent > $uStart) { $blockedByUnavailability = true; break; }
                //     }
                // }
                // if ($blockedByUnavailability) { continue; }

                // Appointment overlap filter with buffer on both ends
                $sStartWithBufferUtc = (clone $sStartAgent)->subMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $sEndWithBufferUtc = (clone $sEndAgent)->addMinutes($bufferMinutes)->setTimezone('UTC')->toDateTimeString();
                $blockedByAppointment = false;
                foreach ($appointments as $a) {
                    if ($a->start_at < $sEndWithBufferUtc && $a->end_at > $sStartWithBufferUtc) {
                        $blockedByAppointment = true;
                        break;
                    }
                }
                if ($blockedByAppointment) {
                    continue;
                }

                $available = true;
                break;
            }

            if (!$available) {
                return ApiResponseService::validationError(trans('Selected time is not available'), $availableSlots);
            }

            $appointment = Appointment::create([
                'is_admin_appointment' => $isAdminAgent ? 1 : 0,
                'admin_id' => $isAdminAgent ? $agentId : null,
                'agent_id' => $isAdminAgent ? null : $agentId,
                'user_id' => $userId,
                'property_id' => $property->id,
                'meeting_type' => $request->meeting_type,
                'start_at' => $startUtc,
                'end_at' => $endUtc,
                'status' => $autoConfirm ? 'confirmed' : 'pending',
                'is_auto_confirmed' => $autoConfirm,
                'last_status_updated_by' => $autoConfirm ? 'system' : 'user',
                'notes' => $request->notes,
            ]);
            $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($adminTimezone)->format('Y-m-d H:i:s');
            $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($adminTimezone)->format('Y-m-d H:i:s');

            // Send notifications using the appointment notification service
            AppointmentNotificationService::sendNewAppointmentRequestNotification(
                $appointment,
                $isAdminAgent ? null : $agent,
                $loggedInUser,
                $property,
                $autoConfirm,
                $isAdminAgent ? $agent : null
            );

            DB::commit();
            return ApiResponseService::successResponse(trans('Appointment created successfully'), $appointment);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Update Appointment Status (Agent and User)
    public function updateAppointmentStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id'    => 'required|exists:appointments,id',
                'status'            => 'required|in:confirmed,cancelled,rescheduled',
                'reason'            => 'nullable|required_if:status,cancelled,rescheduled|string',
                'date'              => 'nullable|required_if:status,rescheduled|date_format:Y-m-d',
                'start_time'        => 'nullable|required_if:status,rescheduled|date_format:H:i',
                'end_time'          => 'nullable|required_if:status,rescheduled|date_format:H:i|after:start_time',
                'meeting_type'      => 'nullable|in:phone,virtual,in_person',
            ], [
                'date.required_if' => trans('Date is required for rescheduling'),
                'start_time.required_if' => trans('Start time is required for rescheduling'),
                'end_time.required_if' => trans('End time is required for rescheduling'),
                'end_time.after' => trans('End time must be greater than start time'),
                'meeting_type.in' => trans('Invalid meeting type'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $appointmentId = $request->appointment_id;
            $requestedStatus = $request->status;
            $reason = $request->input('reason');
            $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC'; // Admin Timezone
            $appointment = Appointment::where('id', $appointmentId)->first(); // Appointment Data
            $isAdminAppointment = false;

            // Appointment Data Validation
            if (!$appointment) {
                return ApiResponseService::validationError(trans('Appointment not found'));
            }

            // Admin Appointment Data Validation
            if ($appointment->is_admin_appointment) {
                $isAdminAppointment = true;
            }

            // Booking Preference Data Validation
            if ($isAdminAppointment) {
                $preferences = AgentBookingPreference::where(['is_admin_data' => 1, 'admin_id' => $appointment->admin_id])->first(); // Agent Booking Preference
                $agentTimezone = $adminTimezone;
            } else {
                $preferences = AgentBookingPreference::where('agent_id', $appointment->agent_id)->first(); // Admin Booking Preference
                $agentTimezone = $preferences?->timezone ?: (config('app.timezone') ?? 'UTC'); // Agent Timezone
            }
            $dailyLimit = $preferences?->daily_booking_limit ?: 0;
            $isAgent = false;
            if (!$isAdminAppointment) {
                $isAgent = (bool)($loggedInUser->is_agent ?? false);
                $isAgent = $appointment->agent_id === $loggedInUser->id;
            }


            // Authorization: agent must be the appointment's agent; user must be the appointment's user
            if ($isAdminAppointment) {
                $authorized = false;
                if ($appointment->user_id == $loggedInUser->id) {
                    $authorized = true;
                }
            } else {
                if ($isAgent) {
                    $property = Property::select('id', 'added_by')->where('id', $appointment->property_id)->first();
                    $authorized = $property && $property->added_by == $loggedInUser->id;
                } else {
                    $authorized = $appointment->user_id === $loggedInUser->id;
                }
            }
            if (!$authorized) {
                return ApiResponseService::validationError(trans('You are not authorized to update this appointment'));
            }

            // Allowed actions by role
            if ($isAgent) {
                $allowedForRole = in_array($requestedStatus, ['confirmed', 'cancelled', 'rescheduled']);
            } else {
                $allowedForRole = in_array($requestedStatus, ['cancelled', 'rescheduled']);
            }
            if (!$allowedForRole) {
                return ApiResponseService::validationError(trans('You are not allowed to set this status'));
            }

            // Prevent invalid state transitions
            if (in_array($appointment->status, ['completed'])) {
                return ApiResponseService::validationError(trans('This appointment can no longer be updated'));
            }

            // If update status is reschedule or cancel, check agent preference cancel_reschedule_buffer_minutes
            if (in_array($requestedStatus, ['rescheduled', 'cancelled'])) {
                $cancelRescheduleBuffer = (int) ($preferences->cancel_reschedule_buffer_minutes ?? 0);
                if ($cancelRescheduleBuffer > 0) {
                    // Appointment start time in agent timezone
                    $appointmentStart = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone);
                    $nowAgent = Carbon::now($agentTimezone);
                    $appointmentDate = Carbon::parse($appointment->start_at, 'UTC');
                    if ($nowAgent >= $appointmentDate) {
                        $diffInMinutes = $appointmentStart->diffInMinutes($nowAgent, false);
                    } else {
                        $diffInMinutes = $nowAgent->diffInMinutes($appointmentStart, false);
                    }
                    if ($diffInMinutes <= $cancelRescheduleBuffer) {
                        return ApiResponseService::validationError(
                            trans('You cannot ' . ($requestedStatus === 'rescheduled' ? 'reschedule' : 'cancel') . ' this appointment within :minutes minutes of its start time.', ['minutes' => $cancelRescheduleBuffer])
                        );
                    }
                }
            }

            // Idempotency: if same status (and for reschedule same time), return success without changes
            if ($requestedStatus !== 'rescheduled' && $appointment->status === $requestedStatus) {
                if ($isAgent) {
                    $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone);
                    $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($agentTimezone);
                } else {
                    $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($adminTimezone);
                    $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($adminTimezone);
                }
                return ApiResponseService::successResponse(trans('No changes'), $appointment);
            }

            $meetingDurationMinutes = (int) ($preferences->meeting_duration_minutes ?? 0);
            $bufferMinutes = max(0, (int) ($preferences->buffer_time_minutes ?? 0));

            DB::beginTransaction();

            $changedBy = null;
            // Handle reschedule (validate availability and update times)
            if ($requestedStatus === 'rescheduled') {
                $dateKey = $request->input('date');
                $startTimeStr = $request->input('start_time');
                $endTimeStr = $request->input('end_time');

                // Check if agent has reached daily limit
                if ($dailyLimit > 0) {
                    if ($isAdminAppointment) {
                        $startOfDay = Carbon::parse($dateKey . ' ' . '00:00:00', $adminTimezone)->setTimezone('UTC');
                        $endOfDay = Carbon::parse($dateKey . ' ' . '23:59:59', $adminTimezone)->setTimezone('UTC');
                    } else {
                        $startOfDay = Carbon::parse($dateKey . ' ' . '00:00:00', $agentTimezone)->setTimezone('UTC');
                        $endOfDay = Carbon::parse($dateKey . ' ' . '23:59:59', $agentTimezone)->setTimezone('UTC');
                    }
                    $dailyAppointmentCount = Appointment::whereBetween('start_at', [$startOfDay, $endOfDay])
                        ->whereNotIn('status', ['cancelled', 'auto_cancelled', 'pending'])
                        ->when($isAdminAppointment, function ($query) use ($appointment) {
                            $query->where('admin_id', $appointment->admin_id)
                                ->where('is_admin_appointment', 1);
                        }, function ($query) use ($appointment) {
                            $query->where('agent_id', $appointment->agent_id);
                        })
                        ->count();
                    // If daily appointment count is greater than or equal to daily limit, return error
                    if ($dailyAppointmentCount >= $dailyLimit) {
                        return ApiResponseService::validationError(
                            trans('Agent cannot accept more appointments on provided date.')
                        );
                    }
                }

                // Idempotent check: same as existing
                $newStartAgent = Carbon::parse($dateKey . ' ' . $startTimeStr, $adminTimezone)->setTimezone($agentTimezone);
                $newEndAgent = Carbon::parse($dateKey . ' ' . $endTimeStr, $adminTimezone)->setTimezone($agentTimezone);
                $newStartUtc = (clone $newStartAgent)->setTimezone('UTC')->toDateTimeString();
                $newEndUtc = (clone $newEndAgent)->setTimezone('UTC')->toDateTimeString();
                if ($appointment->start_at === $newStartUtc && $appointment->end_at === $newEndUtc) {
                    DB::rollBack();
                    if ($isAgent) {
                        $startAt = $appointment->start_at;
                        unset($appointment->start_at);
                        $endAt = $appointment->end_at;
                        unset($appointment->end_at);
                        $appointment->start_at = Carbon::parse($startAt, 'UTC')->setTimezone($agentTimezone);
                        $appointment->end_at = Carbon::parse($endAt, 'UTC')->setTimezone($agentTimezone);
                    } else {
                        $startAt = $appointment->start_at;
                        unset($appointment->start_at);
                        $endAt = $appointment->end_at;
                        unset($appointment->end_at);
                        $appointment->start_at = Carbon::parse($startAt, 'UTC')->setTimezone($adminTimezone);
                        $appointment->end_at = Carbon::parse($endAt, 'UTC')->setTimezone($adminTimezone);
                    }
                    return ApiResponseService::successResponse(trans('No changes'), $appointment);
                }

                // Past date/time checks per role
                if ($isAgent) {
                    $nowAgent = Carbon::now($agentTimezone);
                    if ($newStartAgent->lt($nowAgent)) {
                        DB::rollBack();
                        return ApiResponseService::validationError(trans('You cannot select a past date/time'));
                    }
                } else {
                    $newStartAdmin = Carbon::parse($dateKey . ' ' . $startTimeStr, $adminTimezone);
                    $nowAdmin = Carbon::now($adminTimezone);
                    if ($newStartAdmin->lt($nowAdmin)) {
                        DB::rollBack();
                        return ApiResponseService::validationError(trans('You cannot select a past date/time'));
                    }
                }

                if ($newEndAgent <= $newStartAgent) {
                    DB::rollBack();
                    return ApiResponseService::validationError(trans('End time must be greater than start time'));
                }
                if ($meetingDurationMinutes > 0 && $newStartAgent->diffInMinutes($newEndAgent) !== $meetingDurationMinutes) {
                    DB::rollBack();
                    return ApiResponseService::validationError(trans('Invalid time duration'));
                }

                // Check availability similar to createAppointment
                $dayName = strtolower($newStartAgent->englishDayOfWeek);
                if ($isAdminAppointment) {
                    $windows = AgentAvailability::where('admin_id', $appointment->admin_id)
                        ->where('is_active', 1)
                        ->where('is_admin_data', 1)
                        ->where('day_of_week', $dayName)
                        ->get();
                    $extraWindows = AgentExtraTimeSlot::where(['admin_id' => $appointment->admin_id, 'is_admin_data' => 1])
                        ->where('date', $dateKey)
                        ->get();
                } else {
                    $windows = AgentAvailability::where('agent_id', $appointment->agent_id)
                        ->where('is_active', 1)
                        ->where('day_of_week', $dayName)
                        ->get();
                    $extraWindows = AgentExtraTimeSlot::where(['agent_id' => $appointment->agent_id])
                        ->where('date', $dateKey)
                        ->get();
                }
                $windows = $windows->merge($extraWindows);

                // Build slots
                $slots = [];
                foreach ($windows as $w) {
                    $winStart = Carbon::parse($dateKey . ' ' . $w->start_time, 'UTC')->setTimezone($agentTimezone);
                    $winEnd = Carbon::parse($dateKey . ' ' . $w->end_time, 'UTC')->setTimezone($agentTimezone);
                    if ($winEnd <= $winStart) {
                        continue;
                    }
                    $cursor = (clone $winStart);
                    while (true) {
                        $candidateEnd = (clone $cursor)->addMinutes($meetingDurationMinutes);
                        if ($candidateEnd > $winEnd) {
                            break;
                        }
                        $slots[] = ['start' => (clone $cursor), 'end' => (clone $candidateEnd)];
                        $cursor = (clone $candidateEnd)->addMinutes($bufferMinutes);
                    }
                }
                if (empty($slots)) {
                    DB::rollBack();
                    return ApiResponseService::validationError(trans('Selected time is outside agent availability'));
                }

                // Find exact requested slot in slots and ensure it's not blocked
                $slotValid = false;
                foreach ($slots as $s) {
                    $sStartAgent = $s['start'];
                    $sEndAgent = $s['end'];
                    if ($sStartAgent->format('H:i') !== $newStartAgent->format('H:i') || $sEndAgent->format('H:i') !== $newEndAgent->format('H:i')) {
                        continue;
                    }
                    $slotValid = true;
                    break;
                }
                if (!$slotValid) {
                    DB::rollBack();
                    return ApiResponseService::validationError(trans('Selected time is not available'));
                }

                // Record reschedule and update appointment
                AppointmentReschedule::create([
                    'appointment_id' => $appointment->id,
                    'old_start_at' => $appointment->start_at,
                    'old_end_at' => $appointment->end_at,
                    'new_start_at' => $newStartUtc,
                    'new_end_at' => $newEndUtc,
                    'reason' => $reason,
                    'rescheduled_by' => $isAgent ? 'agent' : 'user',
                ]);

                $appointment->start_at = $newStartUtc;
                $appointment->end_at = $newEndUtc;
                $appointment->status = 'rescheduled';
                $appointment->last_status_updated_by = $isAgent ? 'agent' : 'user';
                $appointment->meeting_type = $request->meeting_type;
                $appointment->save();
            }

            // Handle confirm
            if ($requestedStatus === 'confirmed') {
                if (!$isAgent) {
                    DB::rollBack();
                    return ApiResponseService::validationError(trans('Only agents can confirm appointments'));
                }
                if (in_array($appointment->status, ['cancelled'])) {
                    DB::rollBack();
                    return ApiResponseService::validationError(trans('Cancelled appointment cannot be confirmed'));
                }
                $appointment->status = 'confirmed';
                $appointment->last_status_updated_by = 'agent';
                $appointment->save();
            }

            // Handle cancel
            if ($requestedStatus === 'cancelled') {
                if ($appointment->status === 'cancelled') {
                    DB::rollBack();
                    return ApiResponseService::successResponse(trans('No changes'), $appointment);
                }
                $appointment->status = 'cancelled';
                $appointment->last_status_updated_by = $isAgent ? 'agent' : 'user';
                $appointment->save();
                AppointmentCancellation::create([
                    'appointment_id' => $appointment->id,
                    'reason' => $reason,
                    'cancelled_by' => $isAgent ? 'agent' : 'user',
                ]);
            }

            $changedBy = $isAgent ? 'agent' : 'user';

            AppointmentNotificationService::sendStatusNotification(
                $appointment,
                $appointment->status,
                $reason,
                $changedBy
            );

            $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($adminTimezone);
            $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($adminTimezone);

            DB::commit();
            return ApiResponseService::successResponse(trans('Appointment status updated successfully'), $appointment);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function updateAppointmentMeetingType(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|exists:appointments,id',
                'meeting_type' => 'required|in:phone,virtual,in_person',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }
            $loggedInUser = Auth::user();
            $appointment = Appointment::where('id', $request->appointment_id)->where(function ($query) use ($loggedInUser) {
                $query->where('agent_id', $loggedInUser->id)->orWhere('user_id', $loggedInUser->id);
            })->first();
            if (!$appointment) {
                ApiResponseService::validationError(trans('You are not authorized to update this appointment'));
            }
            if ($appointment->status === 'cancelled') {
                ApiResponseService::validationError(trans('Appointment is cancelled'));
            }
            // Store old meeting type for notification
            $oldMeetingType = $appointment->meeting_type;
            $newMeetingType = $request->meeting_type;

            // Check if meeting type actually changed
            if ($oldMeetingType === $newMeetingType) {
                ApiResponseService::successResponse(trans('No changes made'), $appointment);
            }

            // Update meeting type
            Appointment::where('id', $request->appointment_id)->update(['meeting_type' => $newMeetingType]);

            // Determine who made the change for notification targeting
            $updatedBy = null;
            if ($appointment->agent_id == $loggedInUser->id) {
                $updatedBy = 'agent';
            } elseif ($appointment->user_id == $loggedInUser->id) {
                $updatedBy = 'user';
            }

            // Send notifications using the appointment notification service
            AppointmentNotificationService::sendMeetingTypeChangeNotification(
                $appointment,
                $oldMeetingType,
                $newMeetingType,
                $updatedBy
            );

            ApiResponseService::successResponse(trans('Appointment meeting type updated successfully'), $appointment);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAgentAppointments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
                'meeting_type' => 'nullable|string|in:phone,virtual,in_person',
                'status' => 'nullable|in:pending,confirmed,completed,cancelled,rescheduled,auto_cancelled',
                'date_filter' => 'nullable|string|in:upcoming,previous',
            ], [
                'offset.integer' => trans('Offset must be an integer'),
                'limit.integer' => trans('Limit must be an integer'),
                'meeting_type.in' => trans('Meeting type must be phone, virtual, or in_person'),
                'status.in' => trans('Status must be pending, confirmed, completed, cancelled, rescheduled, or auto_cancelled'),
                'date_filter.in' => trans('Date filter must be upcoming, or previous'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;
            $loggedInUser = Auth::user();
            // Check if the logged-in user is a verified user (submitted verification request & admin approved it)
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();
            // dd($isAgent);
            $agentTimezone = $loggedInUser->getTimezone(true);
            if (!$isAgent) {
                return ApiResponseService::validationError(trans('You are not authorized to get agent appointments'));
            }
            $appointmentQuery = Appointment::where('agent_id', $loggedInUser->id)->with('user:id,name,profile', 'property:id,title,title_image')
                ->when($request->has('date_filter') && !empty($request->date_filter), function ($query) use ($request, $agentTimezone) {
                    switch ($request->date_filter) {
                        case 'upcoming':
                            $status = array('confirmed', 'rescheduled', 'pending', 'cancelled', 'auto_cancelled');
                            $date = now()->setTimezone($agentTimezone)->toDateString();
                            $query->whereIn('status', $status)->whereDate('start_at', '>=', $date);
                            break;
                        case 'previous':
                            $status = array('completed', 'rejected', 'cancelled', 'auto_cancelled');
                            $date = now()->setTimezone($agentTimezone)->toDateString();
                            $query->whereIn('status', $status)->where('start_at', '<', $date);
                            break;
                    }
                })
                ->when($request->has('meeting_type') && !empty($request->meeting_type), function ($query) use ($request) {
                    $query->where('meeting_type', $request->meeting_type);
                })->when($request->has('status') && !empty($request->status), function ($query) use ($request) {
                    $query->where('status', $request->status);
                });
            // Total
            $totalAppointments = $appointmentQuery->clone()->count();

            $appointments = $appointmentQuery
                ->with(['user:id,name,profile,email', 'property' => function ($propertyQuery) {
                    $propertyQuery->select('id', 'title', 'title_image', 'price', 'propery_type', 'address', 'state', 'country', 'city', 'rentduration', 'category_id')->with('category:id,slug_id,image,category', 'category.translations', 'translations');
                }, 'agent.agent_booking_preferences:id,agent_id,availability_types', 'cancellations:id,appointment_id,reason,cancelled_by'])
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
            $appointments = $appointments->map(function ($appointment) use ($agentTimezone) {
                $appointment->date = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('d M Y');
                $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('Y-m-d H:i:s');
                $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($agentTimezone)->format('Y-m-d H:i:s');
                $appointment->property->translated_title = $appointment->property->translated_title;
                $appointment->property->property_type = $appointment->property->propery_type;
                $appointment->property->is_premium = $appointment->property->is_premium;
                $appointment->property->is_promoted = $appointment->property->is_promoted;
                $appointment->property->parameters = $appointment->property->parameters;
                $appointment->property->category->translated_name = $appointment->property->category->translated_name;
                $appointment->agent->is_user_verified = $appointment->agent->is_user_verified;
                $appointment->availability_types = $appointment->agent->agent_booking_preferences->availability_types;
                $appointment->reason = $appointment->status === 'cancelled' ? optional($appointment->cancellations->last())->reason : null;
                unset($appointment->agent);
                return $appointment;
            });
            return ApiResponseService::successResponse(trans('Appointments fetched successfully'), $appointments, array('total' => $totalAppointments));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getUserAppointments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
                'date_filter' => 'nullable|string|in:upcoming,previous',
                'meeting_type' => 'nullable|string|in:phone,virtual,in_person',
                'status' => 'nullable|in:pending,confirmed,completed,cancelled,rescheduled,auto_cancelled',
            ], [
                'offset.integer' => trans('Offset must be an integer'),
                'limit.integer' => trans('Limit must be an integer'),
                'meeting_type.in' => trans('Meeting type must be phone, virtual, or in_person'),
                'status.in' => trans('Status must be pending, confirmed, completed, cancelled, rescheduled, or auto_cancelled'),
                'date_filter.in' => trans('Date filter must be upcoming, or previous'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;
            $loggedInUser = Auth::user();
            $userTimezone = HelperService::getSettingData('timezone') ?: 'UTC';
            $appointmentQuery = Appointment::where('user_id', $loggedInUser->id)
                ->when($request->has('date_filter') && !empty($request->date_filter), function ($query) use ($request, $userTimezone) {
                    switch ($request->date_filter) {
                        case 'upcoming':
                            $status = array('confirmed', 'rescheduled', 'pending', 'cancelled', 'auto_cancelled');
                            $date = now()->setTimezone($userTimezone)->toDateString();
                            $query->whereIn('status', $status)->where('start_at', '>=', $date);
                            break;
                        case 'previous':
                            $status = array('completed', 'rejected', 'cancelled', 'auto_cancelled');
                            $date = now()->setTimezone($userTimezone)->toDateString();
                            $query->whereIn('status', $status)->where('start_at', '<', $date);
                            break;
                    }
                })
                ->when($request->has('meeting_type') && !empty($request->meeting_type), function ($query) use ($request) {
                    $query->where('meeting_type', $request->meeting_type);
                })->when($request->has('status') && !empty($request->status), function ($query) use ($request) {
                    $query->where('status', $request->status);
                });

            // Total
            $totalAppointments = $appointmentQuery->count();

            $appointments = $appointmentQuery->clone()
                ->with(['agent' => function ($agentQuery) {
                    $agentQuery->with('agent_booking_preferences:id,agent_id,availability_types')->select('id', 'name', 'profile', 'email');
                }, 'admin' => function ($adminQuery) {
                    $adminQuery->with('agent_booking_preferences:id,admin_id,availability_types')->select('id', 'name', 'profile', 'email');
                }, 'property' => function ($propertyQuery) {
                    $propertyQuery->select('id', 'title', 'title_image', 'price', 'propery_type', 'address', 'state', 'country', 'city', 'rentduration', 'category_id')->with('category:id,slug_id,image,category', 'category.translations', 'translations');
                }, 'cancellations:id,appointment_id,reason,cancelled_by'])
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
            $appointments = $appointments->map(function ($appointment) use ($userTimezone) {
                $appointment->date = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($userTimezone)->format('d M Y');
                $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s');
                $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($userTimezone)->format('Y-m-d H:i:s');
                $appointment->property->translated_title = $appointment->property->translated_title;
                $appointment->property->property_type = $appointment->property->propery_type;
                $appointment->property->is_premium = $appointment->property->is_premium;
                $appointment->property->is_promoted = $appointment->property->is_promoted;
                $appointment->property->parameters = $appointment->property->parameters;
                $appointment->property->category->translated_name = $appointment->property->category->translated_name;
                if ($appointment->status == 'cancelled') {
                    $appointment->reason = $appointment->cancellations->last()->reason;
                } else {
                    $appointment->reason = null;
                }
                if ($appointment->is_admin_appointment) {
                    $appointment->admin->is_user_verified = true;
                } else {
                    $appointment->agent->is_user_verified = $appointment->agent->is_user_verified;
                }
                $appointment->availability_types = $appointment->is_admin_appointment ? $appointment->admin->agent_booking_preferences->availability_types : $appointment->agent->agent_booking_preferences->availability_types;
                if ($appointment->is_admin_appointment) {
                    unset($appointment->agent);
                    $appointment->admin->name = trans("Admin");
                } else {
                    unset($appointment->admin);
                }
                return $appointment;
            });
            return ApiResponseService::successResponse(trans('Appointments fetched successfully'), $appointments, array('total' => $totalAppointments));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function reportUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:customers,id',
                'reason' => 'required|string',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();
            $loggedInUser = Auth::user();
$isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();
            
            if (!$isAgent) {
                return ApiResponseService::validationError(trans("You are not authorized to report user"));
            }
            $agentId = $loggedInUser->id;
            $appointment = Appointment::where('agent_id', $agentId)->where('user_id', $request->user_id)->first();
            if (!$appointment) {
                return ApiResponseService::validationError(trans('Appointment not found'));
            }
            $userId = $request->user_id;
            $reason = $request->reason;
            $reportUser = ReportUserByAgent::updateOrCreate([
                'agent_id' => $agentId,
                'user_id' => $userId,
            ], [
                'reason' => $reason,
                'status' => 'pending',
            ]);
            $appointments = Appointment::where(['agent_id' => $agentId, 'user_id' => $userId])->with('property:id,title')->get();
            if (collect($appointments)->isNotEmpty()) {
                $appointmentIds = $appointments->pluck('id');
                // Cancel appointments
                Appointment::whereIn('id', $appointmentIds)->update(['status' => 'cancelled']);
                // Create appointment cancellations with reason
                $appointmentCancellationData = [];
                foreach ($appointmentIds as $appointmentId) {
                    $appointmentCancellationData[] = [
                        'appointment_id' => $appointmentId,
                        'reason' => $reason,
                        'cancelled_by' => 'system',
                    ];
                }
                AppointmentCancellation::insert($appointmentCancellationData);

                $user = Customer::where('id', $userId)->select('id', 'name', 'email')->first();
                foreach ($appointments as $appointment) {
                    // Send notification and email to user about cancellation due to being reported
                    if ($user) {
                        AppointmentNotificationService::sendCancellationByReportNotification($appointment, $reason);
                    }
                }
            }
            DB::commit();
            return ApiResponseService::successResponse(trans('Report user submitted successfully'), $reportUser);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getUserReports(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;
            $loggedInUser = Auth::user();
            $isAgent = VerifyCustomer::where('user_id', $loggedInUser->id)->where('status', 'success')->exists();   
            if (!$isAgent) {
                return ApiResponseService::validationError(trans("You are not authorized to get user reports"));
            }
            $userReports = ReportUserByAgent::where('agent_id', $loggedInUser->id)->with('user:id,name,profile')->latest()->offset($offset)->limit($limit)->get();
            return ApiResponseService::successResponse(trans('User reports fetched successfully'), $userReports);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Agent Dashboard Summery Data
    public function getAgentDashboardSummeryData(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            $last12Months = now()->subMonths(12);
            // Properties Query (only sell and rent properties)
            $propertiesQuery = Property::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); })->whereIn('propery_type', [0, 1]);

            // Projects Query
            $projectsQuery = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); });

            // Property Views Query
            $propertyViewsQuery = PropertyView::whereHas('property', function ($query) use ($loggedInUser) {
                $query->where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) { $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date'); });
            })->orderBy('views', 'DESC');

            // Appointment Query
            $appointmentQuery = Appointment::where(function ($query) use ($loggedInUser) {
                $query->where('agent_id', $loggedInUser->id)->orWhere('user_id', $loggedInUser->id);
            });

            // Last 12 Months Property Query
            $last12MonthsPropertyQuery = $propertiesQuery->clone()->where('created_at', '>=', $last12Months);

            // Last 12 Months Project Query
            $last12MonthsProjectQuery = $projectsQuery->clone()->where('created_at', '>=', $last12Months);

            // Last 12 Months Property Views Query
            $last12MonthsPropertyViewsQuery = $propertyViewsQuery->clone()->where('date', '>=', $last12Months);

            // Last 12 Months Appointment Query
            $last12MonthsAppointmentQuery = $appointmentQuery->clone()->where('start_at', '>=', $last12Months);


            // Get last 12 months total, total Properties Count, month wise total property listed counts and total properties count of this month
            $last12MonthTotalPropertiesCount = $last12MonthsPropertyQuery->clone()->count();
            $currentMonthPropertyCount = $last12MonthsPropertyQuery->clone()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $monthlyPropertyCounts = $this->getLast12MonthsPropertiesCountData($last12MonthsPropertyQuery);

            // Get last 12 months total, total Projects Count, month wise total project listed counts and total projects count of this month
            $last12MonthTotalProjectsCount = $last12MonthsProjectQuery->clone()->count();
            $currentMonthProjectCount = $last12MonthsProjectQuery->clone()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $monthlyProjectCounts = $this->getLast12MonthsProjectsCountData($last12MonthsProjectQuery);

            // Get last 12 months total, total Properties Views, month wise total property views and total properties views of this month
            $last12MonthsPropertyViews = $last12MonthsPropertyViewsQuery->clone()->sum('views');
            $currentMonthPropertyViews = $last12MonthsPropertyViewsQuery->clone()->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $monthlyPropertyViews = $this->getLast12MonthsPropertyViewsData($last12MonthsPropertyViewsQuery);

            // Get Current Month Total, Today Total, month wise total appointment counts and total appointments count of this month
            $currentMonthAppointmentCount = $appointmentQuery->clone()->whereBetween('start_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $todayAppointmentCount = $appointmentQuery->clone()->whereBetween('start_at', [now()->startOfDay(), now()->endOfDay()])->count();
            $monthlyAppointmentCounts = $this->getLast12MonthsAppointmentCounts($last12MonthsAppointmentQuery);

            // Get Chats Data
            $chats = Chats::with(['sender', 'receiver'])->with('property.translations')
                ->select(
                    'id',
                    'sender_id',
                    'receiver_id',
                    'property_id',
                    'created_at',
                    'message',
                    DB::raw('LEAST(sender_id, receiver_id) as user1_id'),
                    DB::raw('GREATEST(sender_id, receiver_id) as user2_id'),
                    DB::raw('COUNT(CASE WHEN receiver_id = ' . $loggedInUser->id . ' AND is_read = 0 THEN 1 END) AS unread_count')
                )
                ->where(function ($query) use ($loggedInUser) {
                    $query->where('sender_id', $loggedInUser->id)
                        ->orWhere('receiver_id', $loggedInUser->id);
                })
                ->orderBy('id', 'desc')
                ->groupBy('user1_id', 'user2_id', 'property_id')
                ->limit(5)
                ->get()->map(function ($chat) use ($loggedInUser) {
                    $chat->property->translated_title = $chat->property->translated_title;
                    $chat->property->category->translated_name = $chat->property->category->translated_name;

                    // Get Admin or Other user data not the logged in user
                    $otherUserID = $chat->sender_id == $loggedInUser->id ? $chat->receiver_id : $chat->sender_id;
                    if ($otherUserID == 0) {
                        $otherUser = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id')->first();
                    } else {
                        $otherUser = $chat->sender_id == $loggedInUser->id ? $chat->receiver : $chat->sender;
                    }
                    $chat->other_user = [
                        'id' => $otherUserID ?? null,
                        'name' => $otherUserID == 0 ? 'Admin' : $otherUser->name ?? null,
                        'email' => $otherUser->email ?? null,
                        'profile' => $otherUser->profile ?? null,
                        'slug_id' => $otherUser->slug_id ?? null,
                    ];

                    $chat->last_message = $chat->message;
                    $chat->last_message_time = $chat->created_at;
                    unset($chat->sender);
                    unset($chat->receiver);
                    unset($chat->message);
                    return $chat;
                });

            $data = array(
                'properties' => array(
                    'total_properties' => $last12MonthTotalPropertiesCount,
                    'current_month_property_count' => $currentMonthPropertyCount,
                    'monthly_property_counts' => $monthlyPropertyCounts,
                ),
                'projects' => array(
                    'total_projects' => $last12MonthTotalProjectsCount,
                    'current_month_project_count' => $currentMonthProjectCount,
                    'monthly_project_counts' => $monthlyProjectCounts,
                ),
                'properties_views' => array(
                    'total_views' => $last12MonthsPropertyViews,
                    'current_month_property_views' => $currentMonthPropertyViews,
                    'monthly_property_views' => $monthlyPropertyViews,
                ),
                'appointments' => array(
                    'current_month_appointment_count' => $currentMonthAppointmentCount,
                    'today_appointment_count' => $todayAppointmentCount,
                    'monthly_appointment_counts' => $monthlyAppointmentCounts,
                ),
                'chats' => $chats,
            );
            return ApiResponseService::successResponse(trans('Agent dashboard summery data fetched successfully'), $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Agent Dashboard Listings Data
    public function getAgentDashboardListingsData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
                'range' => 'required|in:weekly,monthly,yearly',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $loggedInUser = Auth::user();
            $type = $request->type;
            $range = $request->range;

            // Range Type
            if ($range == 'weekly') {
                $rangeType = now()->startOfWeek();
            } else if ($range == 'monthly') {
                $rangeType = now()->startOfMonth();
            } else if ($range == 'yearly') {
                $rangeType = now()->subMonths(12);
            } else {
                ApiResponseService::validationError(trans('Invalid range'));
            }

            if ($type == 'property') {
                // Properties Query (only sell and rent properties)
                $propertiesQuery = Property::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                })->whereIn('propery_type', [0, 1]);

                // Range Property Query
                $rangePropertyQuery = $propertiesQuery->clone()->whereBetween('created_at', [$rangeType, now()]);

                // Property Listed Counts, Sell and rent Counts, Weekly Listed Counts
                $ovrerAllPropertyCounts = $this->getPropertyCounts($rangePropertyQuery);

                // Week Wise Data Property Counts
                $rangeWiseDataPropertyCounts = array();
                if ($range == 'weekly') {
                    $rangeWiseDataPropertyCounts = $this->currentWeekPropertiesCountData($rangePropertyQuery);
                } else if ($range == 'monthly') {
                    $rangeWiseDataPropertyCounts = $this->currentMonthPropertyCountData($rangePropertyQuery);
                } else if ($range == 'yearly') {
                    $rangeWiseDataPropertyCounts = $this->last12MonthsPropertyCountData($rangePropertyQuery);
                }

                $data = array(
                    'overall' => $ovrerAllPropertyCounts,
                    'range_wise' => $rangeWiseDataPropertyCounts,
                );
                return ApiResponseService::successResponse(trans('Listings data fetched successfully'), $data);
            } else if ($type == 'project') {
                // Projects Query
                $projectsQuery = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved']);

                // Range Project Query
                $rangeProjectQuery = $projectsQuery->clone()->whereBetween('created_at', [$rangeType, now()]);
                $ovrerAllProjectCounts = $this->getProjectCounts($rangeProjectQuery);
                $rangeWiseDataProjectCounts = array();
                if ($range == 'weekly') {
                    $rangeWiseDataProjectCounts = $this->currentWeekProjectsCountData($rangeProjectQuery);
                } else if ($range == 'monthly') {
                    $rangeWiseDataProjectCounts = $this->currentMonthProjectsCountData($rangeProjectQuery);
                } else if ($range == 'yearly') {
                    $rangeWiseDataProjectCounts = $this->last12MonthsProjectsCountData($rangeProjectQuery);
                }

                // Get Last Week Projects Counts, Under Construction and Upcoming Counts, Weekly Listed Counts
                $data = array(
                    'overall' => $ovrerAllProjectCounts,
                    'range_wise' => $rangeWiseDataProjectCounts,
                );
                return ApiResponseService::successResponse(trans('Listings data fetched successfully'), $data);
            } else {
                ApiResponseService::errorResponse(trans('Invalid type'));
            }
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Recently Added Listings Data
    public function getAgentDashboardRecentlyAddedListingsData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            // Property Mapper
            $propertyMapper = function ($propertyData) {
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->parameters = $propertyData->parameters;
                $propertyData->is_premium = $propertyData->is_premium == 1;
                $propertyData->translated_title = $propertyData->translated_title;
                $propertyData->category->translated_name = $propertyData->category->translated_name;
                unset($propertyData->propery_type);
                return $propertyData;
            };

            $loggedInUser = Auth::user();
            $type = $request->type;
            if ($type == 'property') {
                // Get Recently Added Properties
                $recentlyListedData = Property::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                })->whereIn('propery_type', [0, 1])->clone()->with('category.translations', 'advertisement', 'interested_users:id,property_id,customer_id', 'interested_users.customer:id,name,profile', 'translations')->latest()->limit(5)->get()->map($propertyMapper);
            } else if ($type == 'project') {
                // Get Recently Added Projects
                $recentlyListedData = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                })->clone()->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile', 'category.translations', 'translations')->latest()->limit(5)->get()->map($propertyMapper);
            } else {
                return ApiResponseService::errorResponse(trans('Invalid type'));
            }
            return ApiResponseService::successResponse(trans('Recently added listings data fetched successfully'), $recentlyListedData);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Agent Dashboard Packages Data
    public function getAgentDashboardActivePackagesData(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            $activePackageIds = HelperService::getAllActivePackageIds($loggedInUser->id);

            if (empty($activePackageIds)) {
                ApiResponseService::successResponse(trans('No active packages found'), []);
            }

            $packages =  Package::withTrashed()
                ->whereIn('id', $activePackageIds)
                ->with([
                    'package_features.feature.translations',
                    'package_features.user_package_limits' => function ($query) use ($loggedInUser) {
                        $query->whereHas('user_package', function ($userQuery) use ($loggedInUser) {
                            $userQuery->where('user_id', $loggedInUser->id)->orderBy('id', 'desc');
                        });
                    },
                    'user_packages' => function ($query) use ($loggedInUser) {
                        $query->where('user_id', $loggedInUser->id)->orderBy('id', 'desc');
                    },
                    'translations'
                ])
                ->get()
                ->map(function ($package) {
                    $userPackage = $package->user_packages->first();
                    if (!$userPackage) return null;

                    return [
                        'id'                        => $package->id,
                        'name'                      => $package->name,
                        'package_type'              => $package->package_type,
                        'price'                     => $package->price,
                        'duration'                  => $package->duration,
                        'start_date'                => $userPackage->start_date,
                        'end_date'                  => $userPackage->end_date,
                        'created_at'                => $package->created_at,
                        'package_status'            => $package->package_payment_status,
                        'translated_name'           => $package->translated_name,
                        'features'                  => $package->package_features->map(function ($package_feature) {
                            $userPackageLimit = $package_feature->user_package_limits->first();
                            return [
                                'id'            => $package_feature->feature->id,
                                'name'          => $package_feature->feature->name,
                                'translated_name' => $package_feature->feature->translated_name,
                                'limit_type'    => $package_feature->limit_type,
                                'limit'         => $package_feature->limit,
                                'used_limit'    => $userPackageLimit ? $userPackageLimit->used_limit : null,
                                'total_limit'   => $userPackageLimit ? $userPackageLimit->total_limit : null
                            ];
                        }),
                        'is_active' => 1
                    ];
                })
                ->filter() // Remove null values
                ->values(); // Re-index array
            return ApiResponseService::successResponse(trans('Packages data fetched successfully'), $packages);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAgentDashboardMostViewedListingData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
                'range' => 'required|in:weekly,monthly,last_three_months',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $type = $request->type;
            $range = $request->range;

            // Define range start
            if ($range == 'weekly') {
                $rangeStart = now()->startOfWeek();
            } elseif ($range === 'monthly') {
                $rangeStart = now()->startOfMonth();
            } elseif ($range === 'last_three_months') {
                $rangeStart = now()->subMonths(3);
            } else {
                return ApiResponseService::validationError(trans('Invalid range'));
            }

            if ($type === 'property') {
                $mostViewedData = PropertyView::selectRaw('property_id, SUM(views) as total_views')
                    ->whereBetween('created_at', [$rangeStart, now()])
                    ->whereHas('property', function ($query) use ($loggedInUser) {
                        $query->where([
                            'added_by' => $loggedInUser->id,
                            'status' => 1,
                            'request_status' => 'approved'
                        ]);
                    })
                    ->groupBy('property_id')
                    ->orderByDesc('total_views')
                    ->with('property.translations')
                    ->limit(5)
                    ->get()
                    ->map(function ($view) {
                        return [
                            'id' => $view->property_id,
                            'title' => $view->property->translated_title ?? $view->property->title,
                            'views' => (int) $view->total_views,
                        ];
                    });
            } else { // project
                $mostViewedData = ProjectView::selectRaw('project_id, SUM(views) as total_views')
                    ->whereBetween('created_at', [$rangeStart, now()])
                    ->whereHas('project', function ($query) use ($loggedInUser) {
                        $query->where([
                            'added_by' => $loggedInUser->id,
                            'status' => 1,
                            'request_status' => 'approved'
                        ]);
                    })
                    ->groupBy('project_id')
                    ->orderByDesc('total_views')
                    ->with('project.translations')
                    ->limit(5)
                    ->get()
                    ->map(function ($view) {
                        return [
                            'id' => $view->project_id,
                            'title' => $view->project->translated_title ?? $view->project->title,
                            'views' => (int) $view->total_views,
                        ];
                    });
            }

            return ApiResponseService::successResponse(trans('Most viewed data fetched successfully'), $mostViewedData);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAgentDashboardMostViewedCategoryData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'range' => 'required|in:weekly,monthly,yearly',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $range = $request->range;

            // Define range start
            if ($range === 'weekly') {
                $rangeStart = now()->startOfWeek();
            } elseif ($range === 'monthly') {
                $rangeStart = now()->startOfMonth();
            } elseif ($range === 'yearly') {
                $rangeStart = now()->startOfYear();
            } else {
                return ApiResponseService::validationError(trans('Invalid range'));
            }

            // Get property category views
            $propertyViews = PropertyView::whereBetween('created_at', [$rangeStart, now()])
                ->whereHas('property', function ($query) use ($loggedInUser) {
                    $query->where([
                        'added_by' => $loggedInUser->id,
                        'status' => 1,
                        'request_status' => 'approved'
                    ]);
                })
                ->with('property:id,category_id', 'property.category.translations')
                ->get()
                ->groupBy('property.category_id')
                ->map(fn($group) => $group->sum('views'));

            // Get project category views
            $projectViews = ProjectView::whereBetween('created_at', [$rangeStart, now()])
                ->whereHas('project', function ($query) use ($loggedInUser) {
                    $query->where([
                        'added_by' => $loggedInUser->id,
                        'status' => 1,
                        'request_status' => 'approved'
                    ]);
                })
                ->with('project:id,category_id', 'project.category.translations')
                ->get()
                ->groupBy('project.category_id')
                ->map(fn($group) => $group->sum('views'));

            // Combine property and project views by category
            $combinedViews = collect();

            // Add property views
            foreach ($propertyViews as $categoryId => $views) {
                $combinedViews->put($categoryId, ($combinedViews->get($categoryId, 0) + $views));
            }

            // Add project views
            foreach ($projectViews as $categoryId => $views) {
                $combinedViews->put($categoryId, ($combinedViews->get($categoryId, 0) + $views));
            }

            // Sort
            $mostViewedCategories = $combinedViews
                ->sortDesc()
                ->map(function ($totalViews, $categoryId) {
                    $category = Category::find($categoryId);
                    return [
                        'id' => $categoryId,
                        'title' => $category?->translated_name ?? $category?->name,
                        'views' => (int) $totalViews,
                    ];
                })
                ->values();

            return ApiResponseService::successResponse(
                trans('Most viewed categories fetched successfully'),
                $mostViewedCategories
            );
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAgentDashboardAppointmentData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer',
                'offset' => 'nullable|integer',
                'range' => 'required|in:weekly,monthly,yearly',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $range = $request->range;

            // Define range start
            if ($range == 'weekly') {
                $rangeStart = now()->startOfWeek();
                $rangeEnd = now()->endOfWeek();
            } elseif ($range === 'monthly') {
                $rangeStart = now()->startOfMonth();
                $rangeEnd = now()->endOfMonth();
            } elseif ($range === 'yearly') {
                $rangeStart = now()->startOfYear();
                $rangeEnd = now()->endOfYear();
            } else {
                return ApiResponseService::errorResponse(trans('Invalid range'));
            }

            // Appointments Data
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 5;
            $adminTimezone = HelperService::getSettingData('timezone');
            $agentTimezone = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first()?->timezone ?? ($adminTimezone ?? 'UTC');

            // Appointment Query
            $appointmentQuery = Appointment::where(function ($query) use ($loggedInUser) {
                $query->where('agent_id', $loggedInUser->id)->orWhere('user_id', $loggedInUser->id);
            })->whereBetween('start_at', [$rangeStart, $rangeEnd]);
            // Total Appointments
            $totalAppointments = $appointmentQuery->clone()->count();
            // Appointments Data
            $appointmentData = $appointmentQuery->clone()->latest()->offset($offset)->limit($limit)->with('property:id,title,added_by,title_image', 'property.translations', 'agent:id,name')->get()->map(function ($appointment) use ($agentTimezone) {
                $appointment->date = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('d M Y');
                $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $appointment->property->translated_title = $appointment->property->translated_title;
                return $appointment;
            });

            return ApiResponseService::successResponse(trans('Appointments fetched successfully'), $appointmentData, array('total' => $totalAppointments));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }


public function getHomepagePropertiesByCity()
{
    try {
        $cityImageStyle = Setting::where('type', 'city_image_style')->first();
        $cityImageStyle = $cityImageStyle->data ?? 'style_1';
        $withImage = $cityImageStyle == 'style_1' ? true : false;
        
        $propertiesByCitiesSection = config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TYPE');
        $homepageSectionData = HomepageSection::where('section_type', $propertiesByCitiesSection)
            ->where('is_active', 1)
            ->first();
        
        $propertiesByCities = [];
        
        if ($homepageSectionData) {
            // Get all cities with their property counts (only active properties)
            $citiesData = CityImage::where('status', 1)
                ->withCount(['property' => function($query) {
                    $query->where('status', 1)
                          ->where('request_status', 'approved');
                }])
                ->having('property_count', '>', 0)
                ->orderBy('property_count', 'DESC')
                ->limit(12)
                ->get();
            
            foreach ($citiesData as $city) {
                $cityArray = [
                    'City' => $city->city,
                    'Count' => $city->property_count,
                ];
                
                if ($withImage) {
                    // Get image from database - your accessor will handle the full URL
                    $imageUrl = $city->image; // This calls getImageAttribute()
                    $cityArray['image'] = $imageUrl ?? '';
                } else {
                    $cityArray['image'] = '';
                }
                
                $propertiesByCities[] = $cityArray;
            }
            
            $data = array(
                'section_id' => $homepageSectionData->id,
                'section_title' => $homepageSectionData->title,
                'translated_title' => $homepageSectionData->translated_title ?? $homepageSectionData->title,
                'section_type' => $propertiesByCitiesSection,
                'with_image' => $withImage,
                'data' => $propertiesByCities
            );
        }
        
        return ApiResponseService::successResponse(trans("Data Fetched Successfully"), $data ?? []);
        
    } catch (Exception $e) {
        \Log::error('getHomepagePropertiesByCity Error: ' . $e->getMessage());
        return ApiResponseService::errorResponse(trans("Something Went Wrong"));
    }
}
    public function getHomepagePropertiesOnMap(Request $request)
    {
        try {
            $latitude = $request->has('latitude') ? $request->latitude : null;
            $longitude = $request->has('longitude') ? $request->longitude : null;
            $radius = $request->has('radius') ? $request->radius : null;
            $locationBasedDataAvailable = false;

            $propertiesOnMapSection = config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TYPE');
            $homepageSectionData = HomepageSection::where('section_type', $propertiesOnMapSection)->where('is_active', 1)->first();

            if ($homepageSectionData) {
                $propertyBaseQuery = Property::select(
                    'id',
                    'slug_id',
                    'category_id',
                    'city',
                    'state',
                    'country',
                    'price',
                    'propery_type',
                    'title',
                    'title_image',
                    'is_premium',
                    'address',
                    'rentduration',
                    'latitude',
                    'longitude',
                    'added_by',
                    'description'
                )
                    ->with(['category:id,slug_id,image,category', 'category.translations', 'translations'])
                    ->onlyActive()
                    ->whereIn('propery_type', [0, 1]);

                $locationBasedPropertyQuery = clone $propertyBaseQuery;
                if ($latitude && $longitude) {
                    if ($radius) {
                        $locationBasedPropertyQuery->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$latitude, $longitude, $latitude])
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->having('distance', '<', $radius);
                    } else {
                        $locationBasedPropertyQuery->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    }

                    if ($locationBasedPropertyQuery->exists()) {
                        $locationBasedDataAvailable = true;
                    } else {
                        $locationBasedPropertyQuery = clone $propertyBaseQuery;
                    }
                }
                $propertyMapper = function ($propertyData) {
                    $propertyData->promoted = $propertyData->is_promoted;
                    $propertyData->property_type = $propertyData->propery_type;
                    $propertyData->is_premium = $propertyData->is_premium == 1;
                    $propertyData->parameters = $propertyData->parameters;
                    $propertyData->category->translated_name = $propertyData->category->translated_name;
                    $propertyData->translated_title = $propertyData->translated_title;
                    $propertyData->translated_description = $propertyData->translated_description;
                    return $propertyData;
                };
                $propertiesOnMap = $locationBasedPropertyQuery->clone()->get()->map($propertyMapper);
                $data = array(
                    'section_id' => $homepageSectionData->id,
                    'section_type' => $propertiesOnMapSection,
                    'section_title' => $homepageSectionData->title,
                    'translated_title' => $homepageSectionData->translated_title,
                    'data' => $propertiesOnMap ?? []
                );
            }
            ApiResponseService::successResponse(trans("Data Fetched Successfully"), $data ?? [], array('location_based_data_available' => $locationBasedDataAvailable));
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAdBanners(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'page'      => 'required|in:homepage,property_listing,property_detail',
                'platform'  => 'required|in:app,web',
            ],
            [
                'page.required' => trans('The page field is required.'),
                'page.in' => trans('The page field must be a valid page.'),
                'platform.required' => trans('The platform field is required.'),
                'platform.in' => trans('The platform field must be a valid platform.'),
            ]
        );
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $page = $request->page;
            $platform = $request->platform;
            $now = now();
            $now = now();

            $adBanners = AdBanner::where('is_active', 1)
                ->where(['page' => $page, 'platform' => $platform])
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->with('property:id,title,slug_id')
                ->inRandomOrder()
                ->get()
                ->groupBy('placement')
                ->map(fn($group) => $group->first())
                ->values(); // removes keys, returns flat array

            if (empty($adBanners)) {
                return ApiResponseService::validationError(trans('No ad banners found.'));
            }
            ApiResponseService::successResponse(trans("Data Fetched Successfully"), $adBanners);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }


    /**
     * Get Homepage Sections Configuration
     * Returns section metadata (id, title, type, order) without actual data
     */
    public function getHomepageSectionsData(Request $request)
    {
        try {
            $sections = HomepageSection::where('is_active', 1)
                ->orderBy('sort_order')
                ->with('translations')
                ->get()
                ->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'type' => $section->section_type,
                        'title' => $section->title,
                        'translated_title' => $section->translated_title,
                        'sort_order' => $section->sort_order,
                        'is_active' => $section->is_active
                    ];
                });

            ApiResponseService::successResponse("Homepage Sections Data Fetched Successfully", $sections);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    /**
     * Get All Property-Related Sections
     * Includes: Nearby, Featured, Most Viewed, Most Liked, Properties on Map, Premium
     */
    public function getHomepagePropertySections(Request $request)
    {
        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10;

            $nearbyPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TYPE');
            $featuredPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TYPE');
            $mostViewedPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TYPE');
            $mostLikedPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TYPE');
            $premiumPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TYPE');

            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $nearbyPropertyHomepageSection,
                    $featuredPropertyHomepageSection,
                    $mostViewedPropertyHomepageSection,
                    $mostLikedPropertyHomepageSection,
                    $premiumPropertyHomepageSection
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order
                        ],
                    ];
                });

            // Build optimized property base query once
            $propertyBaseQuery = Property::select(
                'id',
                'slug_id',
                'category_id',
                'city',
                'state',
                'country',
                'price',
                'propery_type',
                'title',
                'title_image',
                'is_premium',
                'address',
                'rentduration',
                'latitude',
                'longitude',
                'added_by',
                'description',
                'total_click'
            )
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'translations'
                ])
                ->onlyActive()
                ->whereIn('propery_type', [0, 1]);

            // Apply location filter once
            $locationBasedPropertyQuery = $this->applyLocationFilterToListings($propertyBaseQuery, $latitude, $longitude, $radius);

            $propertyMapper = function ($propertyData) {
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->is_premium = $propertyData->is_premium == 1;
                $propertyData->parameters = $propertyData->parameters;
                if ($propertyData->category) {
                    $propertyData->category->translated_name = $propertyData->category->translated_name;
                }
                $propertyData->translated_title = $propertyData->translated_title;
                $propertyData->translated_description = $propertyData->translated_description;
                return $propertyData;
            };

            $data = [];

            // Nearby Properties
            $data['nearby_properties'] = [
                'section_id' => $homepageData[$nearbyPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->inRandomOrder()->limit(12)->get()->map($propertyMapper)
            ];


            // Featured Properties
            $data['featured_properties'] = [
                'section_id' => $homepageData[$featuredPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->whereHas('advertisement', function ($subQuery) {
                    $subQuery->where(['is_enable' => 1, 'status' => 0])
                        ->whereNot('type', 'Slider');
                })->inRandomOrder()->limit(4)->get()->map($propertyMapper)
            ];

            // Most Viewed Properties
            $data['most_viewed_properties'] = [
                'section_id' => $homepageData[$mostViewedPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->orderBy('total_click', 'DESC')->limit(12)->get()->map($propertyMapper)
            ];

            // Most Liked Properties
            $data['most_liked_properties'] = [
                'section_id' => $homepageData[$mostLikedPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->withCount('favourite')->orderBy('favourite_count', 'DESC')->limit(12)->get()->map($propertyMapper)
            ];

            // Premium Properties
            $data['premium_properties'] = [
                'section_id' => $homepageData[$premiumPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->where('is_premium', 1)->inRandomOrder()->limit(12)->get()->map($propertyMapper)
            ];

            // Compute location flag and fallback to global if all property sections are empty for given location
            $locationBasedDataProperties = false;
            if ($latitude && $longitude) {
                $hasNearby = isset($data['nearby_properties']['data']) && $data['nearby_properties']['data']->count() > 0;
                $hasFeatured = isset($data['featured_properties']['data']) && $data['featured_properties']['data']->count() > 0;
                $hasMostViewed = isset($data['most_viewed_properties']['data']) && $data['most_viewed_properties']['data']->count() > 0;
                $hasMostLiked = isset($data['most_liked_properties']['data']) && $data['most_liked_properties']['data']->count() > 0;
                $hasPremium = isset($data['premium_properties']['data']) && $data['premium_properties']['data']->count() > 0;

                if ($hasNearby || $hasFeatured || $hasMostViewed || $hasMostLiked || $hasPremium) {
                    $locationBasedDataProperties = true;
                } else {
                    // Rebuild all property sections from global (no location filter)
                    $globalPropertyQuery = $propertyBaseQuery->clone();

                    $data['nearby_properties'] = [
                        'section_id' => $homepageData[$nearbyPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->inRandomOrder()->limit(12)->get()->map($propertyMapper)
                    ];

                    $data['featured_properties'] = [
                        'section_id' => $homepageData[$featuredPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->whereHas('advertisement', function ($subQuery) {
                            $subQuery->where(['is_enable' => 1, 'status' => 0])
                                ->whereNot('type', 'Slider');
                        })->inRandomOrder()->limit(4)->get()->map($propertyMapper)
                    ];

                    $data['most_viewed_properties'] = [
                        'section_id' => $homepageData[$mostViewedPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->orderBy('total_click', 'DESC')->limit(12)->get()->map($propertyMapper)
                    ];

                    $data['most_liked_properties'] = [
                        'section_id' => $homepageData[$mostLikedPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->withCount('favourite')->orderBy('favourite_count', 'DESC')->limit(12)->get()->map($propertyMapper)
                    ];

                    $data['premium_properties'] = [
                        'section_id' => $homepageData[$premiumPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->where('is_premium', 1)->inRandomOrder()->limit(12)->get()->map($propertyMapper)
                    ];
                }
            }

            $data['location_based_data'] = $locationBasedDataProperties;

            ApiResponseService::successResponse("Property Sections Fetched Successfully", $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }


    /**
     * Get All Project-Related Sections
     * Includes: Projects, Featured Projects
     */
    public function getHomepageProjectSections(Request $request)
    {
        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10;

            $projectsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TYPE');
            $featuredProjectsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TYPE');
            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $projectsHomepageSection,
                    $featuredProjectsHomepageSection
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order
                        ],
                    ];
                });

            // Build optimized projects base query once
            $projectsBaseQuery = Projects::select(
                'id',
                'slug_id',
                'city',
                'state',
                'country',
                'title',
                'type',
                'image',
                'location',
                'category_id',
                'added_by',
                'latitude',
                'longitude'
            )
                ->where(['request_status' => 'approved', 'status' => 1])
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'gallary_images:id,project_id,name',
                    'customer:id,name,profile,email,mobile',
                    'translations'
                ]);

            // Apply location filter once
            $locationBasedProjectsQuery = $this->applyLocationFilterToListings($projectsBaseQuery, $latitude, $longitude, $radius);

            $projectMapper = function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                $item->translated_title = $item->translated_title;
                $item->translated_description = $item->translated_description;
                return $item;
            };

            $data = [];

            // Regular Projects
            $data['projects'] = [
                'section_id' => $homepageData[$projectsHomepageSection]['id'] ?? null,
                'data' => $locationBasedProjectsQuery->clone()->inRandomOrder()->limit(12)->get()->map($projectMapper)
            ];

            // Featured Projects
            $data['featured_projects'] = [
                'section_id' => $homepageData[$featuredProjectsHomepageSection]['id'] ?? null,
                'data' => $locationBasedProjectsQuery->clone()
                    ->whereHas('advertisement', function ($query) {
                        $query->where(['is_enable' => 1, 'status' => 0]);
                    })->inRandomOrder()->limit(12)->get()->map($projectMapper)
            ];

            // Compute location flag and fallback to global if all project sections are empty for given location
            $locationBasedDataProjects = false;
            if ($latitude && $longitude) {
                $hasProjects = isset($data['projects']['data']) && $data['projects']['data']->count() > 0;
                $hasFeaturedProjects = isset($data['featured_projects']['data']) && $data['featured_projects']['data']->count() > 0;

                if ($hasProjects || $hasFeaturedProjects) {
                    $locationBasedDataProjects = true;
                } else {
                    // Rebuild projects sections from global (no location filter)
                    $globalProjectsQuery = $projectsBaseQuery->clone();

                    $data['projects'] = [
                        'section_id' => $homepageData[$projectsHomepageSection]['id'] ?? null,
                        'data' => $globalProjectsQuery->clone()->inRandomOrder()->limit(12)->get()->map($projectMapper)
                    ];

                    $data['featured_projects'] = [
                        'section_id' => $homepageData[$featuredProjectsHomepageSection]['id'] ?? null,
                        'data' => $globalProjectsQuery->clone()->whereHas('advertisement', function ($query) {
                            $query->where(['is_enable' => 1, 'status' => 0]);
                        })->inRandomOrder()->limit(12)->get()->map($projectMapper)
                    ];
                }
            }

            $data['location_based_data'] = $locationBasedDataProjects;

            ApiResponseService::successResponse("Project Sections Fetched Successfully", $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    /**
     * Get Other Homepage Sections
     * Includes: Categories, Agents, Articles, User Recommendations, FAQs
     */
    public function getHomepageOtherSections(Request $request)
    {
        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius;
            $locationBasedData = false;

            $categoriesHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TYPE');
            $agentsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TYPE');
            $articlesHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TYPE');
            $userRecommendationsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TYPE');
            $faqsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TYPE');
            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $categoriesHomepageSection,
                    $agentsHomepageSection,
                    $articlesHomepageSection,
                    $userRecommendationsHomepageSection,
                    $faqsHomepageSection
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order
                        ],
                    ];
                });

            $data = [];

            // Categories Section
            $categoriesQuery = Category::select('id', 'category', 'image', 'slug_id')->where('status', 1);

            // Add whereHas condition for location filtering
            if ($latitude && $longitude) {
                if ($radius && !empty($radius)) {
                    $categoriesQuery->whereExists(function ($query) use ($latitude, $longitude, $radius) {
                        $query->select(DB::raw(1))
                            ->from('propertys')
                            ->whereRaw('categories.id = propertys.category_id')
                            ->where(['status' => 1, 'request_status' => 'approved'])
                            ->where(function ($q) {
                                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                            })
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->whereRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?', [$latitude, $longitude, $latitude, $radius]);
                    });
                } else {
                    $categoriesQuery->whereHas('properties', function ($query) use ($latitude, $longitude, $radius) {
                        $query->where(['status' => 1, 'request_status' => 'approved'])
                            ->where(function ($q) {
                                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                            })
                        ->where(['latitude' => $latitude, 'longitude' => $longitude,]);
                        $query->whereExists(function ($subQuery) use ($latitude, $longitude, $radius) {
                            $subQuery->select(DB::raw(1))
                                ->from('propertys')
                                ->whereRaw('categories.id = propertys.category_id')
                                ->where(['status' => 1, 'request_status' => 'approved'])
                                ->where(function ($q) {
                                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                                })
                                ->where('latitude', '!=', 0)
                                ->where('longitude', '!=', 0)
                                ->whereRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?', [$latitude, $longitude, $latitude, $radius]);
                        });
                    });
                }
            } else {
                $categoriesQuery->whereHas('properties', function ($query) {
                    $query->onlyActive();
                });
            }

            // Add properties count with location filtering
            if ($latitude && $longitude) {
                if ($radius && !empty($radius)) {
                    $categoriesQuery->selectRaw('(SELECT COUNT(*) FROM propertys WHERE categories.id = propertys.category_id AND status = 1 AND request_status = "approved" AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as properties_count', [$latitude, $longitude, $latitude, $radius]);
                } else {
                    $categoriesQuery->selectRaw('(SELECT COUNT(*) FROM propertys WHERE categories.id = propertys.category_id AND status = 1 AND request_status = "approved" AND latitude = ? AND longitude = ?) as properties_count', [$latitude, $longitude]);
                }
            } else {
                $categoriesQuery->withCount(['properties' => function ($query) {
                    $query->where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                        $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                    });
                }]);
            }

            $categoriesQuery->with('translations');

            $data['categories'] = [
                'section_id' => $homepageData[$categoriesHomepageSection]['id'] ?? null,
                'data' => $categoriesQuery->limit(12)->get()->map(function ($item) {
                    $item->translated_name = $item->translated_name;
                    return $item;
                })
            ];

            // Agents Section
            $agentsQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'facebook_id', 'twiiter_id as twitter_id', 'instagram_id', 'youtube_id')
                ->where('isActive', 1);

            // Add counts with location filtering
            if ($latitude && $longitude) {
                if ($radius && !empty($radius)) {
                    $agentsQuery->selectRaw('(SELECT COUNT(*) FROM projects WHERE customers.id = projects.added_by AND status = 1 AND request_status = "approved" AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as projects_count', [$latitude, $longitude, $latitude, $radius])
                        ->selectRaw('(SELECT COUNT(*) FROM propertys WHERE customers.id = propertys.added_by AND status = 1 AND request_status = "approved" AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as property_count', [$latitude, $longitude, $latitude, $radius]);
                } else {
                    $agentsQuery->selectRaw('(SELECT COUNT(*) FROM projects WHERE customers.id = projects.added_by AND status = 1 AND request_status = "approved" AND latitude = ? AND longitude = ?) as projects_count', [$latitude, $longitude])
                        ->selectRaw('(SELECT COUNT(*) FROM propertys WHERE customers.id = propertys.added_by AND status = 1 AND request_status = "approved" AND latitude = ? AND longitude = ?) as property_count', [$latitude, $longitude]);
                }
            } else {
                $agentsQuery->withCount([
                    'projects' => function ($query) {
                        $query->onlyActive();
                    },
                    'property' => function ($query) {
                        $query->onlyActive();
                    }
                ]);
            }

            $agents = $agentsQuery->get()
                ->map(function ($customer) {
                    $customer->is_verified = $customer->is_user_verified;
                    $customer->is_verified_user = $customer->is_user_verified;
                    $customer->total_count = $customer->projects_count + $customer->property_count;
                    $customer->is_admin = false;
                    return $customer;
                })
                ->filter(function ($customer) {
                    return $customer->projects_count > 0 || $customer->property_count > 0;
                })
                ->sortByDesc(function ($customer) {
                    return [$customer->is_verified, $customer->total_count];
                })
                ->values()
                ->take(12);

            // Add admin user if they have properties or projects
            $adminEmail = system_setting('company_email');
            $adminPropertyQuery = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])
                ->when($latitude && $longitude, function ($query) use ($latitude, $longitude, $radius) {
                    if ($radius && !empty($radius)) {
                        $query->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$latitude, $longitude, $latitude])
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->having('distance', '<', $radius);
                    } else {
                        $query->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    }
                });
            $adminProjectQuery = Projects::where(['is_admin_listing' => 1, 'status' => 1])
                ->when($latitude && $longitude, function ($query) use ($latitude, $longitude, $radius) {
                    if ($radius && !empty($radius)) {
                        $query->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$latitude, $longitude, $latitude])
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->having('distance', '<', $radius);
                    } else {
                        $query->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    }
                });

            $adminPropertiesCount = $adminPropertyQuery->count();
            $adminProjectsCount = $adminProjectQuery->count();

            if ($adminPropertiesCount > 0 || $adminProjectsCount > 0) {
                $adminQuery = User::where('type', 0)->select('id', 'slug_id', 'profile')->first();
                if ($adminQuery) {
                    $adminData = [
                        'id' => $adminQuery->id,
                        'name' => 'Admin',
                        'slug_id' => $adminQuery->slug_id,
                        'email' => !empty($adminEmail) ? $adminEmail : "",
                        'property_count' => $adminPropertiesCount,
                        'projects_count' => $adminProjectsCount,
                        'total_count' => $adminPropertiesCount + $adminProjectsCount,
                        'is_verified' => true,
                        'is_verified_user' => true,
                        'profile' => !empty($adminQuery->getRawOriginal('profile')) ? $adminQuery->profile : url('assets/images/faces/2.jpg'),
                        'is_admin' => true
                    ];
                    $agents->prepend((object)$adminData);
                }
            }

            $data['agents'] =
                [
                    'section_id' => $homepageData[$agentsHomepageSection]['id'] ?? null,
                    'data' => $agents
                ];

            // Decide location based data availability and apply fallback to global data if none
            if ($latitude && $longitude) {
                $categoriesHasData = isset($data['categories']['data']) && $data['categories']['data']->count() > 0;
                $agentsHasData = isset($data['agents']['data']) && $data['agents']['data']->count() > 0;

                if ($categoriesHasData || $agentsHasData) {
                    $locationBasedData = true;
                } else {
                    // Fallback: rebuild categories without location filters
                    $fallbackCategoriesQuery = Category::select('id', 'category', 'image', 'slug_id')
                        ->where('status', 1)
                        ->whereHas('properties', function ($query) {
                            $query->onlyActive();
                        })
                        ->withCount(['properties' => function ($query) {
                            $query->onlyActive();
                        }])
                        ->with('translations');

                    $data['categories'] = [
                        'section_id' => $homepageData[$categoriesHomepageSection]['id'] ?? null,
                        'data' => $fallbackCategoriesQuery->limit(12)->get()->map(function ($item) {
                            $item->translated_name = $item->translated_name;
                            return $item;
                        })
                    ];

                    // Fallback: rebuild agents without location filters
                    $fallbackAgentsQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'facebook_id', 'twiiter_id as twitter_id', 'instagram_id', 'youtube_id')
                        ->where('isActive', 1)
                        ->withCount([
                            'projects' => function ($query) {
                                $query->onlyActive();
                            },
                            'property' => function ($query) {
                                $query->onlyActive();
                            }
                        ]);

                    $fallbackAgents = $fallbackAgentsQuery->get()
                        ->map(function ($customer) {
                            $customer->is_verified = $customer->is_user_verified;
                            $customer->is_verified_user = $customer->is_user_verified;
                            $customer->total_count = $customer->projects_count + $customer->property_count;
                            $customer->is_admin = false;
                            return $customer;
                        })
                        ->filter(function ($customer) {
                            return $customer->projects_count > 0 || $customer->property_count > 0;
                        })
                        ->sortByDesc(function ($customer) {
                            return [$customer->is_verified, $customer->total_count];
                        })
                        ->values()
                        ->take(12);

                    // Add admin user if they have properties or projects (global, no location filter)
                    $adminEmailGlobal = system_setting('company_email');
                    $adminPropertiesCountGlobal = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->count();
                    $adminProjectsCountGlobal = Projects::where(['is_admin_listing' => 1, 'status' => 1])->count();

                    if ($adminPropertiesCountGlobal > 0 || $adminProjectsCountGlobal > 0) {
                        $adminQueryGlobal = User::where('type', 0)->select('id', 'slug_id', 'profile')->first();
                        if ($adminQueryGlobal) {
                            $adminDataGlobal = [
                                'id' => $adminQueryGlobal->id,
                                'name' => 'Admin',
                                'slug_id' => $adminQueryGlobal->slug_id,
                                'email' => !empty($adminEmailGlobal) ? $adminEmailGlobal : "",
                                'property_count' => $adminPropertiesCountGlobal,
                                'projects_count' => $adminProjectsCountGlobal,
                                'total_count' => $adminPropertiesCountGlobal + $adminProjectsCountGlobal,
                                'is_verified' => true,
                                'is_verified_user' => true,
                                'profile' => !empty($adminQueryGlobal->getRawOriginal('profile')) ? $adminQueryGlobal->profile : url('assets/images/faces/2.jpg'),
                                'is_admin' => true
                            ];
                            $fallbackAgents->prepend((object)$adminDataGlobal);
                        }
                    }

                    $data['agents'] = [
                        'section_id' => $homepageData[$agentsHomepageSection]['id'] ?? null,
                        'data' => $fallbackAgents
                    ];
                }
            }

            // Articles Section
            $data['articles'] = [
                'section_id' => $homepageData[$articlesHomepageSection]['id'] ?? null,
                'data' => Article::select('id', 'slug_id', 'category_id', 'title', 'description', 'image', 'created_at')
                    ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                    ->limit(5)
                    ->get()
                    ->map(function ($item) {
                        if ($item->category) {
                            $item->category->translated_name = $item->category->translated_name;
                        }
                        $item->translated_title = $item->translated_title;
                        $item->translated_description = $item->translated_description;
                        return $item;
                    })
            ];

            // User Recommendations Section
            if (Auth::guard('sanctum')->check()) {
                $loggedInUser = Auth::guard('sanctum')->user();
                $userInterestData = UserInterest::where('user_id', $loggedInUser->id)->first();

                if ($userInterestData) {
                    $userRecommendationQuery = Property::select(
                        'id',
                        'slug_id',
                        'category_id',
                        'city',
                        'state',
                        'country',
                        'price',
                        'propery_type',
                        'title',
                        'title_image',
                        'is_premium',
                        'address',
                        'rentduration',
                        'latitude',
                        'longitude',
                        'added_by',
                        'description'
                    )
                        ->with(['category:id,slug_id,image,category', 'category.translations', 'translations'])
                        ->onlyActive()
                        ->whereIn('propery_type', [0, 1]);

                    // Apply user interest filters
                    if (!empty($userInterestData->category_ids)) {
                        $categoryIds = explode(',', $userInterestData->category_ids);
                        $userRecommendationQuery->whereIn('category_id', $categoryIds);
                    }

                    if (!empty($userInterestData->price_range)) {
                        $priceRange = explode(',', $userInterestData->price_range);
                        if (count($priceRange) >= 2) {
                            $minPrice = floatval($priceRange[0]);
                            $maxPrice = floatval($priceRange[1]);
                            $userRecommendationQuery->whereRaw("CAST(price AS DECIMAL(10, 2)) BETWEEN ? AND ?", [$minPrice, $maxPrice]);
                        }
                    }

                    if (!empty($userInterestData->city)) {
                        $userRecommendationQuery->where('city', $userInterestData->city);
                    }

                    if (!empty($userInterestData->property_type) || $userInterestData->property_type == '0') {
                        $propertyType = explode(',', $userInterestData->property_type);
                        $userRecommendationQuery->whereIn('propery_type', $propertyType);
                    }

                    if (!empty($userInterestData->outdoor_facilitiy_ids)) {
                        $outdoorFacilityIds = explode(',', $userInterestData->outdoor_facilitiy_ids);
                        $userRecommendationQuery->whereHas('assignfacilities.outdoorfacilities', function ($q) use ($outdoorFacilityIds) {
                            $q->whereIn('id', $outdoorFacilityIds);
                        });
                    }

                    $data['user_recommendations'] = [
                        'section_id' => $homepageData[$userRecommendationsHomepageSection]['id'] ?? null,
                        'data' => $userRecommendationQuery
                            ->inRandomOrder()
                            ->limit(12)
                            ->get()
                            ->map(function ($property) {
                                $property->promoted = $property->is_promoted;
                                $property->property_type = $property->propery_type;
                                $property->is_premium = $property->is_premium == 1;
                                $property->parameters = $property->parameters;
                                if ($property->category) {
                                    $property->category->translated_name = $property->category->translated_name;
                                }
                                $property->translated_title = $property->translated_title;
                                $property->translated_description = $property->translated_description;
                                return $property;
                            })
                    ];
                } else {
                    $data['user_recommendations'] = [
                        'section_id' => $homepageData[$userRecommendationsHomepageSection]['id'] ?? null,
                        'data' => []
                    ];
                }
            } else {
                $data['user_recommendations'] = [
                    'section_id' => $homepageData[$userRecommendationsHomepageSection]['id'] ?? null,
                    'data' => []
                ];
            }

            // FAQ Section
            $data['faqs'] = [
                'section_id' => $homepageData[$faqsHomepageSection]['id'] ?? null,
                'data' => Faq::select('id', 'question', 'answer')
                    ->where('status', 1)
                    ->with('translations')
                    ->orderBy('id', 'DESC')
                    ->limit(5)
                    ->get()
                    ->map(function ($faq) {
                        $faq->translated_question = $faq->translated_question;
                        $faq->translated_answer = $faq->translated_answer;
                        return $faq;
                    })
            ];

            // Slider Section
            $lat = $request->latitude;
            $lng = $request->longitude;
            $rad = $request->radius;

            $slider = Slider::select(
                'id',
                'type',
                'image',
                'web_image',
                'category_id',
                'propertys_id',
                'show_property_details',
                'link'
            )
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'property' => fn($p) =>
                    $p->select('id', 'slug_id', 'propery_type', 'title_image', 'title', 'price', 'city', 'state', 'country', 'rentduration', 'added_by', 'is_premium', 'latitude', 'longitude', 'total_click')
                        ->when(
                            $lat && $lng && $rad && $lat != 'null' && $lng != 'null' && $rad != 'null',
                            fn($q) =>
                            $p->whereNotNull('latitude')->whereNotNull('longitude')
                                ->selectRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude))
                            * cos(radians(longitude) - radians(?)) + sin(radians(?))
                            * sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
                                ->havingRaw('distance < ?', [$rad])
                        )
                        ->with('translations')
                ])
                ->when(
                    $lat && $lng && $rad && $lat != 'null' && $lng != 'null' && $rad != 'null',
                    fn($q) => $q->where(
                        fn($sliderQuery) =>
                        $sliderQuery->where(function ($query) use ($lat, $lng, $rad) {
                            $query->where('type', 3)->has('property');
                        })->orWhere('type', '!=', 3)
                    )
                )
                ->get()
                ->map(function ($slider) {
                    $type = $slider->getRawOriginal('type');
                    $slider->slider_type = $type;
                    if ($slider->category) {
                        $slider->category->translated_name = $slider->category->translated_name;
                    }
                    if ($slider->getRawOriginal('type') == 3) {
                        if ($slider->property) {
                            $slider->property->parameters = $slider->property->parameters;
                            $slider->property->translated_title = $slider->property->translated_title;
                            $slider->property->translated_description = $slider->property->translated_description;
                            $slider->property->property_type = $slider->property->propery_type;
                            $slider->property->is_premium = $slider->property->is_premium == 1 ? true : false;
                            return $slider;
                        }
                    } else {
                        return $slider;
                    }
                })->filter()->values();
            $data['slider'] = [
                'section_id' => null,
                'data' => $slider
            ];

            // Expose location flag in response
            $data['location_based_data'] = $locationBasedData;

            ApiResponseService::successResponse("Other Sections Fetched Successfully", $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
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
            if (!$language) {
                ApiResponseService::errorResponse(trans("Language not found"));
            }
            $languageCode = $request->language_code;
            if ($languageCode == 'en') {
                $languageCode = 'en-new';
            }
            $loggedInUser = Auth::user();
            Customer::where('id', $loggedInUser->id)->update(['default_language' => $languageCode]);
            ApiResponseService::successResponse("Language Updated Successfully");
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }


    /*****************************************************************************************************************************************
     * Functions
     *****************************************************************************************************************************************
     */

    /**
     * Get active packages for user with optimized queries
     */


    function getUnsplashData($cityData)
    {
        $apiKey = env('UNSPLASH_API_KEY');
        $query = $cityData->city;
        $apiUrl = "https://api.unsplash.com/search/photos/?query=$query";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID ' . $apiKey,
        ]);
        $unsplashResponse = curl_exec($ch);

        curl_close($ch);

        $unsplashData = json_decode($unsplashResponse, true);
        // Check if the response contains data
        if (isset($unsplashData['results'])) {
            $results = $unsplashData['results'];

            // Initialize the image URL
            $imageUrl = '';

            // Loop through the results and get the first image URL
            foreach ($results as $result) {
                $imageUrl = $result['urls']['regular'];
                break; // Stop after getting the first image URL
            }
            if ($imageUrl != "") {
                return array('City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => $imageUrl);
            }
        }
        return array('City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => "");
    }

    public function getAutoApproveStatus($loggedInUserId)
    {
        // Check auto approve is on and is user is verified or not
        $autoApproveSettingStatus = HelperService::getSettingData('auto_approve');
        $autoApproveStatus = false;
        if ($autoApproveSettingStatus == 1) {
            $userData = Customer::where('id', $loggedInUserId)->first();
            $autoApproveStatus = $userData->is_user_verified ? true : false;
        }

        return $autoApproveStatus;
    }
    function roundArrayValues($array, $pointsValue)
    {
        return array_map(function ($item) use ($pointsValue) {
            if (is_array($item)) {
                return $this->roundArrayValues($item, $pointsValue); // Recursive call
            }
            return is_numeric($item) ? round($item, $pointsValue) : $item; // Base Case
        }, $array);
    }


    function mortgageCalculation($loanAmount, $downPayment, $interestRate, $loanTermYear, $showAllDetails)
    {
        if ($downPayment > 0) {
            $downPayment = (int)$downPayment;
            $loanAmount = $loanAmount - $downPayment;
        }

        // Convert annual interest rate to monthly interest rate
        $monthlyInterestRate = ($interestRate / 100) / 12;

        // Convert loan term in years to months
        $loanTermMonths = $loanTermYear * 12;

        // Calculate monthly payment
        $monthlyPayment = $loanAmount * ($monthlyInterestRate * pow(1 + $monthlyInterestRate, $loanTermMonths)) / (pow(1 + $monthlyInterestRate, $loanTermMonths) - 1);

        // Initialize an array to store the mortgage schedule
        $schedule = [];
        $schedule['main_total'] = array();

        // Initialize main totals
        $mainTotal = [
            'principal_amount' => $loanAmount,
            'down_payment' => $downPayment,
            'payable_interest' => 0,
            'monthly_emi' => $monthlyPayment,
            'total_amount' => 0,
        ];

        // Get current year and month
        $currentYear = date('Y');
        $currentMonth = date('n');

        // Initialize the remaining balance
        $remainingBalance = $loanAmount;

        // Loop through each month
        for ($i = 0; $i < $loanTermMonths; $i++) {
            $month = ($currentMonth + $i) % 12; // Ensure month wraps around by using modulo 12, so it does not exceed 12
            $year = $currentYear + floor(($currentMonth + $i - 1) / 12); // Calculate the year by incrementing when months exceed December

            // Correct month format
            $month = $month === 0 ? 12 : $month;

            // Calculate interest and principal
            $interest = $remainingBalance * $monthlyInterestRate;
            $principal = $monthlyPayment - $interest;
            $remainingBalance -= $principal;

            // Ensure remaining balance is not negative
            if ($remainingBalance < 0) {
                $remainingBalance = 0;
            }

            // Update yearly totals
            if ($showAllDetails && !isset($schedule['yearly_totals'][$year])) {
                $schedule['yearly_totals'][$year] = [
                    'year' => $year,
                    'monthly_emi' => 0,
                    'principal_amount' => 0,
                    'interest_paid' => 0,
                    'remaining_balance' => $remainingBalance,
                    'monthly_totals' => []
                ];
            }

            if ($showAllDetails) {
                $schedule['yearly_totals'][$year]['interest_paid'] += $interest;
                $schedule['yearly_totals'][$year]['principal_amount'] += $principal;

                // Store monthly totals
                $schedule['yearly_totals'][$year]['monthly_totals'][] = [
                    'month' => strtolower(date('F', mktime(0, 0, 0, $month, 1, $year))),
                    'principal_amount' => $principal,
                    'payable_interest' => $interest,
                    'remaining_balance' => $remainingBalance
                ];
            }

            // Update main total
            $mainTotal['payable_interest'] += $interest;
        }

        // Re-index the year totals array index, year used as index
        if ($showAllDetails) {
            $schedule['yearly_totals'] = array_values($schedule['yearly_totals']);
        } else {
            $schedule['yearly_totals'] = array();
        }

        // Calculate the total amount by addition of principle amount and total payable_interest
        $mainTotal['total_amount'] = $mainTotal['principal_amount'] + $mainTotal['payable_interest'];

        // Add Main Total in Schedule Variable
        $schedule['main_total'] = $mainTotal;

        // Round off values for display
        $schedule['main_total'] = $this->roundArrayValues($schedule['main_total'], 2);
        $schedule['yearly_totals'] = $this->roundArrayValues($schedule['yearly_totals'], 0);

        // Return the mortgage schedule
        return $schedule;
    }

    /**
     * Get homepage sections based on latitude and longitude also
     * @param float $latitude
     * @param float $longitude
     * @param Builder $propertyBaseQuery
     * @param Builder $projectsBaseQuery
     * @param Closure $propertyMapper
     * @param boolean $homepageLocationDataAvailable
     * @param Builder $locationBasedPropertyQuery
     * @return array
     */

    // public function getHomepageSections($latitude, $longitude, $propertyBaseQuery, $propertyMapper, $projectsBaseQuery, $homepageLocationDataAvailable, $locationBasedPropertyQuery, $locationBasedProjectsQuery){
    //     $sections = [];
    //     $homepageSections = HomepageSection::where('is_active', 1)
    //             ->orderBy('sort_order')
    //             ->with('translations')
    //             ->get()->map(function($section){
    //                 $section->translated_title = $section->translated_title;
    //                 return $section;
    //             });
    //     // Build homepageData array based on active sections
    //     foreach ($homepageSections as $section) {
    //         switch ($section->section_type) {
    //             // FAQs Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TYPE'):
    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => Faq::select('id', 'question', 'answer')
    //                         ->where('status', 1)
    //                         ->with('translations')
    //                         ->orderBy('id', 'DESC')
    //                         ->limit(5)
    //                         ->get()->map(function($faq){
    //                             $faq->translated_question = $faq->translated_question;
    //                             $faq->translated_answer = $faq->translated_answer;
    //                             return $faq;
    //                         })
    //                 ];
    //                 break;
    //             // Projects Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TYPE'):
    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $locationBasedProjectsQuery->clone()->inRandomOrder()->limit(12)->get()->map(function($item){
    //                         if($item->category){
    //                             $item->category->translated_name = $item->category->translated_name;
    //                         }
    //                         $item->translated_title = $item->translated_title;
    //                         $item->translated_description = $item->translated_description;
    //                         return $item;
    //                     })
    //                 ];
    //                 break;

    //             // Featured Projects Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TYPE'):
    //                 $featuredProjectData = $locationBasedProjectsQuery->clone()
    //                     ->whereHas('advertisement', function($query) {
    //                         $query->where(['is_enable' => 1, 'status' => 0]);
    //                     })
    //                     ->inRandomOrder()
    //                     ->limit(12)
    //                     ->get()->map(function($item){
    //                         if($item->category){
    //                             $item->category->translated_name = $item->category->translated_name;
    //                         }
    //                         $item->translated_title = $item->translated_title;
    //                         $item->translated_description = $item->translated_description;
    //                         return $item;
    //                     });

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $featuredProjectData
    //                 ];
    //                 break;

    //             // Categories Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TYPE'):
    //                 $categoriesData = Category::select('id', 'category', 'image', 'slug_id')
    //                     ->where('status', 1)
    //                     ->withCount(['properties' => function($query) {
    //                         $query->onlyActive();
    //                     }])->with('translations')
    //                     ->limit(12)
    //                     ->get()->map(function($item){
    //                         $item->translated_name = $item->translated_name;
    //                         return $item;
    //                     });

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $categoriesData
    //                 ];
    //                 break;

    //             // Articles Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TYPE'):
    //                 $articlesData = Article::select('id', 'slug_id', 'category_id', 'title', 'description', 'image', 'created_at')
    //                     ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
    //                     ->limit(5)
    //                     ->get()->map(function($item){
    //                         if($item->category){
    //                             $item->category->translated_name = $item->category->translated_name;
    //                         }
    //                         $item->translated_title = $item->translated_title;
    //                         $item->translated_description = $item->translated_description;
    //                         return $item;
    //                     });

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $articlesData
    //                 ];
    //                 break;

    //             // Agents List Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TYPE'):
    //                 $agentsData = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'facebook_id', 'twiiter_id as twitter_id', 'instagram_id', 'youtube_id')
    //                     ->withCount([
    //                         'projects' => function($query) {
    //                             $query->onlyActive();
    //                         },
    //                         'property' => function($query) {
    //                             $query->onlyActive();
    //                         }
    //                     ])
    //                     ->where('isActive', 1)
    //                     ->get()
    //                     ->map(function($customer) {
    //                         $customer->is_verified = $customer->is_user_verified;
    //                         $customer->total_count = $customer->projects_count + $customer->property_count;
    //                         $customer->is_admin = false;
    //                         return $customer;
    //                     })
    //                     ->filter(function($customer) {
    //                         return $customer->projects_count > 0 || $customer->property_count > 0;
    //                     })
    //                     ->sortByDesc(function($customer) {
    //                         return [$customer->is_verified, $customer->total_count];
    //                     })
    //                     ->values()
    //                     ->take(12);

    //                 // Add admin user if they have properties or projects
    //                 $adminEmail = system_setting('company_email');
    //                 $adminPropertyQuery = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved']);
    //                 $adminProjectQuery = Projects::where(['is_admin_listing' => 1, 'status' => 1]);

    //                 $adminPropertiesCount = $adminPropertyQuery->count();
    //                 $adminProjectsCount = $adminProjectQuery->count();

    //                 if ($adminPropertiesCount > 0 || $adminProjectsCount > 0) {
    //                     $adminQuery = User::where('type', 0)->select('id', 'slug_id', 'profile')->first();
    //                     if ($adminQuery) {
    //                         $adminData = [
    //                             'id' => $adminQuery->id,
    //                             'name' => 'Admin',
    //                             'slug_id' => $adminQuery->slug_id,
    //                             'email' => !empty($adminEmail) ? $adminEmail : "",
    //                             'property_count' => $adminPropertiesCount,
    //                             'projects_count' => $adminProjectsCount,
    //                             'total_count' => $adminPropertiesCount + $adminProjectsCount,
    //                             'is_verified' => true,
    //                             'profile' => !empty($adminQuery->getRawOriginal('profile')) ? $adminQuery->profile : url('assets/images/faces/2.jpg'),
    //                             'is_admin' => true
    //                         ];
    //                         $agentsData->prepend((object)$adminData);
    //                     }
    //                 }

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $agentsData
    //                 ];
    //                 break;

    //             // Featured Properties Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TYPE'):
    //                 $featuredSection = $locationBasedPropertyQuery->clone()
    //                     ->whereHas('advertisement', function($subQuery) {
    //                         $subQuery->where(['is_enable' => 1, 'status' => 0])
    //                             ->whereNot('type', 'Slider');
    //                     })
    //                     ->inRandomOrder()
    //                     ->limit(4)
    //                     ->get()
    //                     ->map($propertyMapper);

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $featuredSection
    //                 ];
    //                 break;

    //             // Most Liked Properties Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TYPE'):
    //                 $mostLikedProperties = $locationBasedPropertyQuery->clone()
    //                     ->withCount('favourite')
    //                     ->orderBy('favourite_count', 'DESC')
    //                     ->limit(12)
    //                     ->get()
    //                     ->map($propertyMapper);

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $mostLikedProperties
    //                 ];
    //                 break;

    //             // Most Viewed Properties Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TYPE'):
    //                 $mostViewedProperties = $locationBasedPropertyQuery->clone()
    //                     ->orderBy('total_click', 'DESC')
    //                     ->limit(12)
    //                     ->get()
    //                     ->map($propertyMapper);

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $mostViewedProperties
    //                 ];
    //                 break;

    //             // Premium Properties Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TYPE'):
    //                 $premiumPropertiesSection = $locationBasedPropertyQuery->clone()
    //                     ->where('is_premium', 1)
    //                     ->inRandomOrder()
    //                     ->limit(12)
    //                     ->get()
    //                     ->map($propertyMapper);

    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $premiumPropertiesSection
    //                 ];
    //                 break;

    //             // Nearby Properties Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TYPE'):
    //                 if(Auth::guard('sanctum')->check()){
    //                     $loggedInUser = Auth::guard('sanctum')->user();
    //                     $cityOfUser = $loggedInUser->city;
    //                     $nearbySection = $propertyBaseQuery->clone()
    //                         ->where('city', $cityOfUser)
    //                         ->inRandomOrder()
    //                         ->limit(12)
    //                         ->get()
    //                         ->map($propertyMapper);
    //                 }
    //                 $sections[] = [
    //                     'type' => $section->section_type,
    //                     'sort_order' => $section->sort_order,
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $nearbySection ?? []
    //                 ];
    //                 break;

    //             // User Recommendations Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TYPE'):
    //                 if(Auth::guard('sanctum')->check()){
    //                     $loggedInUser = Auth::guard('sanctum')->user();
    //                     $userInterestData = UserInterest::where('user_id', $loggedInUser->id)->first();

    //                     if ($userInterestData) {
    //                         $userRecommendationQuery = $propertyBaseQuery->clone();

    //                         // Apply category filters
    //                         if (!empty($userInterestData->category_ids)) {
    //                             $categoryIds = explode(',', $userInterestData->category_ids);
    //                             $userRecommendationQuery->whereIn('category_id', $categoryIds);
    //                         }

    //                         // Apply price range filters
    //                         if (!empty($userInterestData->price_range)) {
    //                             $priceRange = explode(',', $userInterestData->price_range);
    //                             if (count($priceRange) >= 2) {
    //                                 $minPrice = floatval($priceRange[0]);
    //                                 $maxPrice = floatval($priceRange[1]);
    //                                 $userRecommendationQuery->whereRaw("CAST(price AS DECIMAL(10, 2)) BETWEEN ? AND ?", [$minPrice, $maxPrice]);
    //                             }
    //                         }

    //                         // Apply city filter
    //                         if (!empty($userInterestData->city)) {
    //                             $userRecommendationQuery->where('city', $userInterestData->city);
    //                         }

    //                         // Apply property type filter
    //                         if (!empty($userInterestData->property_type) || $userInterestData->property_type == '0') {
    //                             $propertyType = explode(',', $userInterestData->property_type);
    //                             $userRecommendationQuery->whereIn('propery_type', $propertyType);
    //                         }

    //                         // Apply outdoor facilities filter
    //                         if (!empty($userInterestData->outdoor_facilitiy_ids)) {
    //                             $outdoorFacilityIds = explode(',', $userInterestData->outdoor_facilitiy_ids);
    //                             $userRecommendationQuery->whereHas('assignfacilities.outdoorfacilities', function($q) use ($outdoorFacilityIds) {
    //                                 $q->whereIn('id', $outdoorFacilityIds);
    //                             });
    //                         }

    //                         // Get recommendations
    //                         $userRecommendations = $userRecommendationQuery
    //                             ->inRandomOrder()
    //                             ->limit(12)
    //                             ->get()
    //                         ->map($propertyMapper);
    //                     }
    //                 }
    //                 $sections[] = [
    //                     'type' => config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TYPE'),
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => $userRecommendations ?? []
    //                 ];
    //                 break;
    //             // Properties By Cities Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TYPE'):
    //                 $sections[] = [
    //                     'type' => config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TYPE'),
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => []
    //                 ];
    //                 break;

    //             // Properties On Map Section
    //             case config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TYPE'):
    //                 $sections[] = [
    //                     'type' => config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TYPE'),
    //                     'title' => $section->title,
    //                     'translated_title' => $section->translated_title,
    //                     'data' => []
    //                 ];
    //                 break;
    //         }
    //     }
    //     return $sections;
    // }

    /**
     * Get the data of a property
     */
    function getPropertyData($property)
    {
        $propertyData = [
            'id' => $property->id,
            'title' => $property->title,
            'city' => $property->city,
            'state' => $property->state,
            'country' => $property->country,
            'is_premium' => $property->is_premium,
            'title_image' => $property->title_image,
            'address' => $property->address,
            'created_at' => $property->created_at,
            'price' => $property->price,
            'rentduration' => !empty($property->rentduration) ? $property->rentduration : null,
            'property_type' => $property->propery_type,
            'total_likes' => $property->favourite()->count(),
            'total_views' => $property->total_click,
            'facilities' => $property->parameters,
            'near_by_places' => $property->assign_facilities,
            'category' => array(
                'id' => $property->category->id,
                'name' => $property->category->name,
                'image' => $property->category->image,
                'translated_name' => $property->category->translated_name,
            ),
            'translated_title' => $property->translated_title,
            'translated_description' => $property->translated_description,
        ];
        return $propertyData;
    }

    function processBankDetails($bankDetails)
    {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();
        $languageId = cache()->remember("language_id_{$languageCode}", 3600, function () use ($languageCode) {
            return Language::where('code', $languageCode)->value('id');
        });
        foreach ($bankDetails as $key => $bankDetail) {
            if (isset($bankDetail['translations']) && !empty($bankDetail['translations'])) {
                foreach ($bankDetail['translations'] as $translation) {
                    if (!empty($languageId) && ($translation['language_id'] == $languageId)) {
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

    /************************************************************************************************************************ */



    // Temp API
    public function removeAccountTemp(Request $request)
    {
        try {
            Customer::where(['email' => $request->email, 'logintype' => 3])->delete();
            ApiResponseService::successResponse("Done");
        } catch (\Throwable $th) {
            ApiResponseService::errorResponse("Issue");
        }
    }

    public function resolvePlaceIdToLocation($placeId)
    {
        $googleApiKey = env('PLACE_API_KEY');
        $response = Http::get("https://maps.googleapis.com/maps/api/place/details/json", [
            'place_id' => $placeId,
            'fields' => 'address_components',
            'key' => $googleApiKey
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $location = [
                'city' => null,
                'state' => null,
                'country' => null
            ];

            if (isset($data['result']) && !empty($data['result'])) {
                foreach ($data['result']['address_components'] as $component) {
                    $types = $component['types'];

                    // Extract city (locality)
                    if (in_array('locality', $types)) {
                        $location['city'] = $component['long_name'];
                    }
                    // Extract state (administrative_area_level_1)
                    elseif (in_array('administrative_area_level_1', $types)) {
                        $location['state'] = $component['long_name'];
                    }
                    // Extract country
                    elseif (in_array('country', $types)) {
                        $location['country'] = $component['long_name'];
                    }
                }
            } else {
                Log::error($data);
            }

            return $location;
        }

        return null;
    }
    /************************************************************************************************************************ */

    /**
     * Listing Counts
     */

    // Property Counts
    function getPropertyCounts($properyQuery)
    {
        $totalPropertiesCount = $properyQuery->clone()->count();
        $sellPropertiesCount = $properyQuery->clone()->where('propery_type', 0)->count();
        $rentPropertiesCount = $properyQuery->clone()->where('propery_type', 1)->count();
        return [
            'total_properties' => $totalPropertiesCount,
            'sell_properties' => $sellPropertiesCount,
            'rent_properties' => $rentPropertiesCount
        ];
    }

    // Project Counts
    function getProjectCounts($projectQuery)
    {
        $totalProjectsCount = $projectQuery->clone()->count();
        $underConstructionProjectsCount = $projectQuery->clone()->where('type', 'under_construction')->count();
        $upcomingProjectsCount = $projectQuery->clone()->where('type', 'upcoming')->count();
        return [
            'total_projects' => $totalProjectsCount,
            'under_construction_projects' => $underConstructionProjectsCount,
            'upcoming_projects' => $upcomingProjectsCount
        ];
    }

    // Weekly Property Counts
    function currentWeekPropertiesCountData($propertyQuery)
    {
        $weeklyPropertyCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();

            $dayProperties = $propertyQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $weeklyPropertyCounts[] = [
                'date' => $date,
                'day_name' => now()->subDays($i)->format('l'),
                'total_properties' => $dayProperties->clone()->count(),
                'sell_properties' => $dayProperties->clone()->where('propery_type', 0)->count(),
                'rent_properties' => $dayProperties->clone()->where('propery_type', 1)->count()
            ];
        }
        return $weeklyPropertyCounts;
    }

    // Weekly Project Counts
    function currentWeekProjectsCountData($projectQuery)
    {
        $weeklyProjectCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();

            $dayProperties = $projectQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $weeklyProjectCounts[] = [
                'date' => $date,
                'day_name' => now()->subDays($i)->format('l'),
                'total_projects' => $dayProperties->clone()->count(),
                'under_construction_projects' => $dayProperties->clone()->where('type', 'under_construction')->count(),
                'upcoming_projects' => $dayProperties->clone()->where('type', 'upcoming')->count()
            ];
        }
        return $weeklyProjectCounts;
    }

    // Monthly Property Counts
    function currentMonthPropertyCountData($propertyQuery)
    {
        $monthlyPropertyCounts = [];
        $daysInMonth = now()->daysInMonth; // total days in current month

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = now()->startOfMonth()->addDays($i - 1);

            $dayStart = $date->copy()->startOfDay();
            $dayEnd   = $date->copy()->endOfDay();

            $dayProperties = $propertyQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $monthlyPropertyCounts[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'total_properties' => $dayProperties->clone()->count(),
                'sell_properties' => $dayProperties->clone()->where('propery_type', 0)->count(),
                'rent_properties' => $dayProperties->clone()->where('propery_type', 1)->count()
            ];
        }

        return $monthlyPropertyCounts;
    }

    // Monthly Project Counts
    function currentMonthProjectsCountData($projectQuery)
    {
        $monthlyProjectCounts = [];
        $daysInMonth = now()->daysInMonth; // total days in current month

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = now()->startOfMonth()->addDays($i - 1);

            $dayStart = $date->copy()->startOfDay();
            $dayEnd   = $date->copy()->endOfDay();

            $dayProjects = $projectQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $monthlyProjectCounts[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'total_projects' => $dayProjects->clone()->count(),
                'under_construction_projects' => $dayProjects->clone()->where('type', 'under_construction')->count(),
                'upcoming_projects' => $dayProjects->clone()->where('type', 'upcoming')->count()
            ];
        }

        return $monthlyProjectCounts;
    }

    // last 12 Months Property Counts
    function last12MonthsPropertyCountData($propertyQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();

            $monthPropertiesCounts = $propertyQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd]);

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_properties' => $monthPropertiesCounts->clone()->count(),
                'sell_properties' => $monthPropertiesCounts->clone()->where('propery_type', 0)->count(),
                'rent_properties' => $monthPropertiesCounts->clone()->where('propery_type', 1)->count()
            ];
        }

        return $last12MonthsCounts;
    }

    // Last 12 months Project Counts
    function last12MonthsProjectsCountData($projectQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();

            $monthProjectCounts = $projectQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd]);

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_projects' => $monthProjectCounts->clone()->count(),
                'under_construction_projects' => $monthProjectCounts->clone()->where('type', 'under_construction')->count(),
                'upcoming_projects' => $monthProjectCounts->clone()->where('type', 'upcoming')->count()
            ];
        }

        return $last12MonthsCounts;
    }


    // Last 12 Months Properties Count Data
    function getLast12MonthsPropertiesCountData($propertyQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();

            $monthPropertiesCounts = $propertyQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->count();

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_properties' => $monthPropertiesCounts
            ];
        }

        return $last12MonthsCounts;
    }

    // Last 12 Months Projects Count Data
    function getLast12MonthsProjectsCountData($projectQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();

            $monthProjectCounts = $projectQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->count();

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_projects' => $monthProjectCounts
            ];
        }

        return $last12MonthsCounts;
    }

    // Last 12 Months Property Views Data
    function getLast12MonthsPropertyViewsData($propertyViewsQuery)
    {
        $last12MonthsPropertyViews = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();
            $monthProperties = $propertyViewsQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->sum('views');

            $last12MonthsPropertyViews[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_views' => (int)$monthProperties
            ];
        }
        return $last12MonthsPropertyViews;
    }

    // Last 12 Months Appointment Counts
    function getLast12MonthsAppointmentCounts($appointmentQuery)
    {
        $last12MonthsAppointmentCounts = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthName  = $monthStart->format('F Y');
            $monthKey   = $monthStart->format('Y-m');

            $totalAppointmentCounts = $appointmentQuery->clone()->whereYear('start_at', $monthStart->year)->whereMonth('start_at', $monthStart->month)->count();
            $last12MonthsAppointmentCounts[] = [
                'month' => $monthName,
                'month_key' => $monthKey,
                'total_appointments' => $totalAppointmentCounts
            ];
        }
        return $last12MonthsAppointmentCounts;
    }

    /************************************************************************************************************************ */
    function getMostViewedCategories($weeklyPropertyQuery, $weeklyProjectQuery)
    {
        $propertyViewsByCategory = $weeklyPropertyQuery->clone()
            ->with('property.category.translations')
            ->get()
            ->groupBy('property.category_id')
            ->map(function ($views, $categoryId) {
                return [
                    'category_id' => $categoryId,
                    'category_name' => $views->first()->property->category->translated_name ?? $views->first()->property->category->category,
                    'category_image' => $views->first()->property->category->image,
                    'total_views' => $views->sum('views'),
                    'type' => 'property'
                ];
            });

        // Get project views by category
        $projectViewsByCategory = $weeklyProjectQuery->clone()
            ->with('project.category.translations')
            ->get()
            ->groupBy('project.category_id')
            ->map(function ($views, $categoryId) {
                return [
                    'category_id' => $categoryId,
                    'category_name' => $views->first()->project->category->translated_name ?? $views->first()->project->category->category,
                    'category_image' => $views->first()->project->category->image,
                    'total_views' => $views->sum('views'),
                    'type' => 'project'
                ];
            });

        // Combine and sum views by category
        $mostViewedCategories = $propertyViewsByCategory->merge($projectViewsByCategory)
            ->groupBy('category_id')
            ->map(function ($categoryViews, $categoryId) {
                $firstView = $categoryViews->first();
                return [
                    'id' => $categoryId,
                    'name' => $firstView['category_name'] ?? '',
                    'image' => $firstView['category_image'] ?? '',
                    'total_views' => $categoryViews->sum('total_views'),
                    'property_views' => $categoryViews->where('type', 'property')->sum('total_views'),
                    'project_views' => $categoryViews->where('type', 'project')->sum('total_views')
                ];
            })
            ->sortByDesc('total_views')
            ->take(5)
            ->values();
        return $mostViewedCategories;
    }

    /**
     * Get appointment count for a regular time slot (day of week)
     */
    private function getAppointmentCountForTimeSlot($agentId, $dayOfWeek, $startTime, $endTime, $agentTimezone)
    {
        try {
            // Get the current week's date range for the specific day of week
            $today = Carbon::now()->setTimezone($agentTimezone);
            $startOfWeek = $today->copy()->startOfWeek();
            $endOfWeek = $today->copy()->endOfWeek();

            // Find the specific day of week
            $dayMap = [
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 0
            ];

            $targetDay = $dayMap[strtolower($dayOfWeek)];
            $targetDate = $startOfWeek->copy()->addDays($targetDay);

            // Convert time slot to UTC for database query
            $slotStartAgent = Carbon::parse($targetDate->format('Y-m-d') . ' ' . $startTime, $agentTimezone);
            $slotEndAgent = Carbon::parse($targetDate->format('Y-m-d') . ' ' . $endTime, $agentTimezone);
            $slotStartUtc = $slotStartAgent->setTimezone('UTC')->toDateTimeString();
            $slotEndUtc = $slotEndAgent->setTimezone('UTC')->toDateTimeString();

            // Count appointments that overlap with this time slot
            $count = Appointment::where('agent_id', $agentId)
                ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                ->where(function ($query) use ($slotStartUtc, $slotEndUtc) {
                    $query->where(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment starts within the slot
                        $q->where('start_at', '>=', $slotStartUtc)
                            ->where('start_at', '<', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment ends within the slot
                        $q->where('end_at', '>', $slotStartUtc)
                            ->where('end_at', '<=', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment completely contains the slot
                        $q->where('start_at', '<=', $slotStartUtc)
                            ->where('end_at', '>=', $slotEndUtc);
                    });
                })
                ->count();

            return $count;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get appointment count for an extra time slot (specific date)
     */
    private function getAppointmentCountForExtraTimeSlot($agentId, $date, $startTime, $endTime, $agentTimezone)
    {
        try {
            // Convert time slot to UTC for database query
            $slotStartAgent = Carbon::parse($date . ' ' . $startTime, $agentTimezone);
            $slotEndAgent = Carbon::parse($date . ' ' . $endTime, $agentTimezone);
            $slotStartUtc = $slotStartAgent->setTimezone('UTC')->toDateTimeString();
            $slotEndUtc = $slotEndAgent->setTimezone('UTC')->toDateTimeString();

            // Count appointments that overlap with this time slot
            $count = Appointment::where('agent_id', $agentId)
                ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                ->where(function ($query) use ($slotStartUtc, $slotEndUtc) {
                    $query->where(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment starts within the slot
                        $q->where('start_at', '>=', $slotStartUtc)
                            ->where('start_at', '<', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment ends within the slot
                        $q->where('end_at', '>', $slotStartUtc)
                            ->where('end_at', '<=', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment completely contains the slot
                        $q->where('start_at', '<=', $slotStartUtc)
                            ->where('end_at', '>=', $slotEndUtc);
                    });
                })
                ->count();

            return $count;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Validate for overlapping time slots within the same request
     */
    private function validateTimeSlotOverlaps($schedule)
    {
        $dayGroups = [];

        // Group by day
        foreach ($schedule as $item) {
            $day = $item['day'];
            if (!isset($dayGroups[$day])) {
                $dayGroups[$day] = [];
            }
            $dayGroups[$day][] = $item;
        }

        // Check for overlaps within each day
        foreach ($dayGroups as $day => $slots) {
            for ($i = 0; $i < count($slots); $i++) {
                for ($j = $i + 1; $j < count($slots); $j++) {
                    if ($this->timeSlotsOverlap($slots[$i], $slots[$j])) {
                        ApiResponseService::validationError("Time slots overlap on {$day}: {$slots[$i]['start_time']}-{$slots[$i]['end_time']} and {$slots[$j]['start_time']}-{$slots[$j]['end_time']}");
                    }
                }
            }
        }
    }

    /**
     * Validate against existing time slots in database
     */
    private function validateExistingTimeSlotOverlaps($schedule, $agentData, $deletedIds)
    {
        // Get existing time slots for the agent (excluding those being deleted)
        $timezone = $agentData->getTimezone(true);
        $existingSlots = AgentAvailability::where('agent_id', $agentData->id)
            ->whereNotIn('id', $deletedIds)
            ->get()
            ->groupBy('day_of_week');

        foreach ($schedule as $newSlot) {
            $day = $newSlot['day'];

            if (isset($existingSlots[$day])) {
                foreach ($existingSlots[$day] as $existingSlot) {
                    $existingSlotArray = [
                        'start_time' => Carbon::parse($existingSlot->start_time, $timezone)->setTimezone('UTC')->format('H:i'),
                        'end_time' => Carbon::parse($existingSlot->end_time, $timezone)->setTimezone('UTC')->format('H:i')
                    ];

                    if ($this->timeSlotsOverlap($newSlot, $existingSlotArray)) {
                        if ((isset($newSlot['id']) && !empty($newSlot['id'])) && $newSlot['id'] == $existingSlot->id) {
                            continue;
                        }
                        ApiResponseService::validationError("Time slot overlaps with existing schedule on {$day}: {$newSlot['start_time']}-{$newSlot['end_time']} overlaps with {$existingSlot->start_time}-{$existingSlot->end_time}");
                    }
                }
            }
        }
    }

    /**
     * Check if two time slots overlap
     */
    private function timeSlotsOverlap($slot1, $slot2)
    {
        $start1 = strtotime($slot1['start_time']);
        $end1 = strtotime($slot1['end_time']);
        $start2 = strtotime($slot2['start_time']);
        $end2 = strtotime($slot2['end_time']);

        // Two time slots overlap if one starts before the other ends
        return $start1 < $end2 && $start2 < $end1;
    }

    private function checkPackageLimitExists($loggedInUserId, $packageId)
    {
        $hasActivePackage = UserPackage::where(['user_id' => $loggedInUserId, 'package_id' => $packageId])->with('user_package_limits')->orderBy('id', 'desc')->onlyActive()->first();
        $isAllFeatureLimitExits = true;
        if (!empty($hasActivePackage)) {
            if (!empty($hasActivePackage->user_package_limits)) {
                foreach ($hasActivePackage->user_package_limits as $userPackageLimit) {
                    if ($userPackageLimit->is_limit_over) {
                        $isAllFeatureLimitExits = false;
                        break;
                    }
                }
            }
        } else {
            $isAllFeatureLimitExits = false;
        }
        return $isAllFeatureLimitExits;
    }

    private function checkUserPurchasedPackage($loggedInUserId, $packageId)
    {
        $hasPurchased = UserPackage::where(['user_id' => $loggedInUserId, 'package_id' => $packageId])->exists();
        return $hasPurchased;
    }

    private function applyLocationFilterToListings($query, $latitude, $longitude, $radius)
    {
        if (!$latitude || !$longitude) {
            return clone $query;
        }

        $locationQuery = clone $query;

        if ($radius) {
            $locationQuery->selectRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$latitude, $longitude, $latitude]
            )
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                ->having('distance', '<', $radius);
        } else {
            $locationQuery->where(['latitude' => $latitude, 'longitude' => $longitude]);
        }

        return $locationQuery;
    }

    public function get_custom_pages(Request $request)
    {
        $offset = $request->offset ?? 0;
        $limit  = $request->limit ?? 10;

        $query = CustomPage::withCurrentLanguageTranslations()
            ->select('id', 'slug_id', 'title', 'icon', 'content', 'status')
            ->where('status', 1);

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        } elseif ($request->filled('slug_id')) {
            $query->where('slug_id', $request->slug_id);
        }

        $total  = $query->count();
        $result = $query->orderBy('id', 'ASC')->skip($offset)->take($limit)->get()->map(function ($item) {
            $item->translated_title   = $item->translated_title;
            $item->translated_content = $item->translated_content;
            return $item;
        });

        $response['error']   = false;
        $response['message'] = $result->isEmpty() ? trans('No Data Found') : trans('Data Fetched Successfully');
        $response['total']   = $total;
        $response['data']    = $result;
        return response()->json($response);
    }
}
