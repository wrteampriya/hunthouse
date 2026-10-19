<?php

use App\Models\Appointment;
use App\Models\BlockedChatUser;
use App\Models\Customer;
use App\Models\Favourite;
use App\Models\InterestedUser;
use App\Models\Language;
use App\Models\OldUserPurchasedPackage;
use App\Models\parameter;
use App\Models\Projects;
use App\Models\Property;
use App\Models\PropertysInquiry;
use App\Models\Setting;
use App\Models\User;
use App\Models\user_reports;
use App\Models\Usertokens;
use App\Services\ApiResponseService;
use App\Services\HelperService;
use App\Services\NotificationTranslationService;
use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use kornrunner\Blurhash\Blurhash;

if (! function_exists('system_setting')) {

    function system_setting($type)
    {

        $db = Setting::where('type', $type)->first();
        if (isset($db)) {
            if ($db->type == 'default_language') {
                return $db->getRawOriginal('data');
            }

            return $db->data;
        }

        return '';
    }
}

function form_submit($data = '', $value = '', $extra = '')
{
    $defaults = [
        'type' => 'submit',
        'name' => is_array($data) ? '' : $data,
        'value' => $value,
    ];

    return '<input '._parse_form_attributes($data, $defaults)._attributes_to_string($extra)." />\n";
}
function _parse_form_attributes($attributes, $default)
{
    if (is_array($attributes)) {
        foreach ($default as $key => $val) {
            if (isset($attributes[$key])) {
                $default[$key] = $attributes[$key];
                unset($attributes[$key]);
            }
        }

        if (count($attributes) > 0) {
            $default = array_merge($default, $attributes);
        }
    }

    $att = '';

    foreach ($default as $key => $val) {
        if ($key === 'value') {
            $val = ($val);
        } elseif ($key === 'name' && ! strlen($default['name'])) {
            continue;
        }

        $att .= $key.'="'.$val.'" ';
    }

    return $att;
}

// ------------------------------------------------------------------------

if (! function_exists('_attributes_to_string')) {
    /**
     * Attributes To String
     *
     * Helper function used by some of the form helpers
     *
     * @param	mixed
     * @return string
     */
    function _attributes_to_string($attributes)
    {
        if (empty($attributes)) {
            return '';
        }

        if (is_object($attributes)) {
            $attributes = (array) $attributes;
        }

        if (is_array($attributes)) {
            $atts = '';

            foreach ($attributes as $key => $val) {
                $atts .= ' '.$key.'="'.$val.'"';
            }

            return $atts;
        }

        if (is_string($attributes)) {
            return ' '.$attributes;
        }

        return false;
    }
}

function send_push_notification($registrationIDs = [], $fcmMsg = '')
{
    try {
        file_put_contents(storage_path('logs/laravel.log'), 'Helper: send_push_notification ENTRY with count: '.count($registrationIDs).' at '.now()."\n", FILE_APPEND);
        Log::info('Helper Send Push Notification ENTRY : ', [
            'registrationIDs' => $registrationIDs,
            'fcmMsg' => $fcmMsg,
        ]);

        if (! count($registrationIDs)) {
            Log::info('Helper Send Push Notification : No IDs found, returning false.');

            return false;
        }
        $client = new GuzzleClient;
        $access_token = getAccessToken(); // Get Access Token
        $projectId = system_setting('firebase_project_id'); // Get Project Id
        $url = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send'; // Create URL
        // Headers
        $headers = [
            'Authorization' => 'Bearer '.$access_token,
            'Content-Type' => 'application/json',
        ];

        // Map FCM tokens to customer IDs and group by language
        $tokensByLanguage = [];
        $tokensWithoutCustomer = [];

        $userTokens = Usertokens::whereIn('fcm_id', $registrationIDs)
            ->select('fcm_id', 'customer_id')
            ->get();

        $tokenToCustomerMap = $userTokens->pluck('customer_id', 'fcm_id')->toArray();

        foreach ($registrationIDs as $token) {
            if (isset($tokenToCustomerMap[$token])) {
                $customerId = $tokenToCustomerMap[$token];
                $languageCode = NotificationTranslationService::getCustomerLanguage($customerId);

                if (! isset($tokensByLanguage[$languageCode])) {
                    $tokensByLanguage[$languageCode] = [];
                }
                $tokensByLanguage[$languageCode][] = $token;
            } else {
                // Token without customer - use default language
                $tokensWithoutCustomer[] = $token;
            }
        }

        // Add tokens without customer to default language group
        if (! empty($tokensWithoutCustomer)) {
            $defaultLanguage = HelperService::getSettingData('default_language') ?? 'en';
            if (! isset($tokensByLanguage[$defaultLanguage])) {
                $tokensByLanguage[$defaultLanguage] = [];
            }
            $tokensByLanguage[$defaultLanguage] = array_merge(
                $tokensByLanguage[$defaultLanguage],
                $tokensWithoutCustomer
            );
        }

        // Create Requests - grouped by language
        // Create a generator for requests
        $requests = function ($tokensByLanguage) use ($url, $headers, $fcmMsg) {
            foreach ($tokensByLanguage as $languageCode => $tokens) {
                $replace = isset($fcmMsg['replace']) && is_array($fcmMsg['replace']) ? $fcmMsg['replace'] : [];

                $translated = NotificationTranslationService::translateNotification(
                    $fcmMsg['title'] ?? '',
                    $fcmMsg['body'] ?? $fcmMsg['message'] ?? '',
                    $languageCode,
                    $replace
                );

                $translatedFcmMsg = $fcmMsg;
                $translatedFcmMsg['title'] = $translated['title'];
                $translatedFcmMsg['body'] = $translated['body'];

                if (isset($fcmMsg['message'])) {
                    $translatedFcmMsg['message'] = $translated['body'];
                }
                unset($translatedFcmMsg['replace']);
                $translatedFcmMsg['data'] = json_encode($translatedFcmMsg['data'] ?? []);

                foreach ($tokens as $registrationID) {
                    $payload = [
                        'message' => [
                            'token' => $registrationID,
                            'notification' => [
                                'title' => $translatedFcmMsg['title'],
                                'body' => $translatedFcmMsg['body'],
                            ],
                            'data' => $translatedFcmMsg,
                        ],
                    ];

                    // Yield the request with the registration ID as the key
                    yield $registrationID => new Request('POST', $url, $headers, json_encode($payload));
                }
            }
        };

        $pool = new Pool($client, $requests($tokensByLanguage), [
            'concurrency' => 10,
            'fulfilled' => function ($response, $registrationID) {
                Log::info("FCM Success Response for token {$registrationID}: ", [$response->getBody()->getContents()]);
                file_put_contents(storage_path('logs/laravel.log'), "FCM Success for token {$registrationID}"."\n", FILE_APPEND);

            },
            'rejected' => function ($reason, $registrationID) use (&$unregisteredIDsNested) {
                file_put_contents(storage_path('logs/laravel.log'), "FCM Rejected for token {$registrationID}: ".$reason->getMessage()."\n", FILE_APPEND);
                Log::error("FCM Error for token {$registrationID}: ".$reason->getMessage());

                if ($reason instanceof RequestException && $reason->hasResponse()) {
                    $response = $reason->getResponse();
                    $decodedResult = json_decode($response->getBody(), true);

                    $errorMessage = strtolower($decodedResult['error']['message'] ?? '');
                    $isInvalidToken = str_contains($errorMessage, 'invalid registration token') ||
                                    str_contains($errorMessage, 'registration token is not a valid') ||
                                    ($decodedResult['error']['status'] ?? '') == 'NOT_FOUND';

                    if ($isInvalidToken) {
                        $unregisteredIDsNested[] = $registrationID;
                    }
                }
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        // Flatten the nested array if it exists
        $unregisteredIDs = ! empty($unregisteredIDsNested) ? (is_array($unregisteredIDsNested[0]) ? array_merge(...$unregisteredIDsNested) : $unregisteredIDsNested) : [];
        $unregisteredIDs = array_filter($unregisteredIDs); // Remove null values

        if (! empty($unregisteredIDs)) {
            Usertokens::whereIn('fcm_id', $unregisteredIDs)->delete();
        }

        return true;
    } catch (Exception $e) {
        Log::error('Error in Notification Sending :- '.$e->getMessage());

        return false;
    }
}

if (! function_exists('get_countries_from_json')) {
    function get_countries_from_json()
    {
        $country = json_decode(file_get_contents(public_path('json').'/cities.json'), true);

        $tempRow = [];
        foreach ($country['countries'] as $row) {
            $tempRow[] = $row['country'];
        }

        return $tempRow;
    }
}

if (! function_exists('get_states_from_json')) {
    function get_states_from_json($country)
    {

        $state = json_decode(file_get_contents(public_path('json').'/cities.json'), true);

        $tempRow = [];
        foreach ($state['countries'] as $row) {
            // echo $row;
            if ($row['country'] == $country) {
                $tempRow = $row['states'];
            }
        }

        return $tempRow;
    }
}

function update_subscription($userId)
{
    // Array Initialize
    $updateUserPackage = [];
    // User Package Query
    $userPackages = OldUserPurchasedPackage::with('package', 'customer')->where('modal_id', $userId);
    // Result Data
    $result = $userPackages->clone()->with('package', 'customer')->get();

    // Get Package Count
    $packageCount = $userPackages->clone()->where(function ($query) {
        $query->where(function ($subQuery) {
            $subQuery->where('prop_status', 1)->orWhere('adv_status', 1);
        });
    })->count();

    // loop on result data
    if (collect($result)->isNotEmpty()) {
        foreach ($result as $key => $row) {
            // Get end date of current looped data
            $endDate = Carbon::parse($row->end_date, 'UTC')->startOfDay(); // Parse the date with UTC time zone and set time to start of the day
            $currentDate = Carbon::now()->startOfDay(); // Set current date time to start of the day

            // Get Difference in days
            $diffInDays = $currentDate->diffInDays($endDate, false); // Use 'false' parameter to get absolute difference

            // If days are zero or in negative
            if ($diffInDays < 0) {
                $updateUserPackage[] = [
                    'id' => $row->id,
                    'prop_status' => 0,
                    'adv_status' => 0,
                ];
                if (! empty($row->package) && $row->package->type == 'premium_user') {
                    $customerPremiumStatus = 0;
                }
            }
        }
    }

    // Bulk Update the user packages limits to zero
    if (! empty($updateUserPackage)) {
        OldUserPurchasedPackage::upsert($updateUserPackage, ['id'], ['prop_status', 'adv_status']);
    }

    // if package count is 0 then update the customer's subscription to 0 and is_premium according to $customerPremiumStatus
    if ($packageCount == 0) {
        $customer = Customer::find($userId);
        $customer->subscription = 0;
        // if there is customerPremiumStatus and its zero then only update
        if (isset($customerPremiumStatus) && $customerPremiumStatus == 0) {
            $customer->is_premium = 0;
        }
        $customer->update();
    }
}
function get_hash($img)
{

    $image_make = Image::make($img);
    $width = $image_make->width();
    $height = $image_make->height();

    $pixels = [];
    for ($y = 0; $y < $height; $y++) {
        $row = [];
        for ($x = 0; $x < $width; $x++) {
            $colors = $image_make->pickColor($x, $y);

            $row[] = [$colors[0], $colors[1], $colors[2]];
        }
        $pixels[] = $row;
    }

    $components_x = 4;
    $components_y = 3;
    $hash = Blurhash::encode($pixels, $components_x, $components_y);

    //  "ll";
    return $hash;
}
if (! function_exists('form_hidden')) {
    /**
     * Hidden Input Field
     *
     * Generates hidden fields. You can pass a simple key/value string or
     * an associative array with multiple values.
     *
     * @param  mixed  $name  Field name
     * @param  string  $value  Field value
     * @param  bool  $recursing
     * @return string
     */
    function form_hidden($name, $value = '', $recursing = false)
    {
        static $form;

        if ($recursing === false) {
            $form = "\n";
        }

        if (is_array($name)) {
            foreach ($name as $key => $val) {
                form_hidden($key, $val, true);
            }

            return $form;
        }

        if (! is_array($value)) {
            $form .= '<input type="hidden" name="'.$name.'" value="'.($value)."\" />\n";
        } else {
            foreach ($value as $k => $v) {
                $k = is_int($k) ? '' : $k;
                form_hidden($name.'['.$k.']', $v, true);
            }
        }

        return $form;
    }
}
if (! function_exists('form_close')) {
    /**
     * Form Close Tag
     *
     * @param	string
     * @return string
     */
    function form_close($extra = '')
    {
        return '</form>'.$extra;
    }
}
function get_property_details($result, $current_user = null, $skipLimitCheck = false)
{
    if ($result instanceof Model) {
        $result = collect([$result]);
    }
    $rows = [];
    $tempRow = [];
    $count = 1;
    foreach ($result as $row) {
        if ($row->is_premium == 1 && $skipLimitCheck == false) {
            if (Auth::guard('sanctum')->check()) {
                // Check if the user has a premium property list feature in package
                $response = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true);
                if ($response['package_available'] == false || $response['feature_available'] == false) {
                    return ApiResponseService::validationError('Cannot Access Premium Property, Feature Not Available', $response);
                }
            } else {
                return ApiResponseService::validationError('Cannot Access Premium Property, Feature Not Available');
            }
        }
        $isAppointmentAvailable = false;
        $customer = $row->customer;

        // Get Property's Added by details
        if ($customer && $row->added_by != 0) {
            $isBlockedByMe = false;
            $isBlockedByUser = false;
            if ($current_user) {

                $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                    ->where('user_id', $row->added_by)
                    ->exists();

                $isBlockedByUser = BlockedChatUser::where('by_user_id', $row->added_by)
                    ->where('user_id', $current_user)
                    ->exists();
            }
            $tempRow['is_blocked_by_me'] = $isBlockedByMe;
            $tempRow['is_blocked_by_user'] = $isBlockedByUser;

            $tempRow['customer_name'] = $customer->name;
            $tempRow['customer_id'] = $customer->id;
            $tempRow['customer_slug_id'] = $customer->slug_id;
            $tempRow['email'] = $customer->email;
            $tempRow['mobile'] = '+'.$customer->country_code.' '.$customer->mobile;
            $tempRow['profile'] = $customer->profile;
            $tempRow['client_address'] = $customer->address;

            $roleContext = $row->role_context ?? 'user';

            // Check if eager-loaded counts exist (useful for APIs where we load it dynamically, but here we enforce role_context)
            $tempRow['total_projects'] = Projects::where('added_by', $customer->id)
                ->where('status', 1)
                ->where('request_status', 'approved')
                ->where('role_context', $roleContext)
                ->count();

            $tempRow['total_properties'] = Property::where('added_by', $customer->id)
                ->where('status', 1)
                ->where('request_status', 'approved')
                ->where('role_context', $roleContext)
                ->count();

            $tempRow['is_admin'] = false;
            $tempRow['is_agent'] = $customer->is_agent;
            $tempRow['is_agent_verified'] = $customer->is_agent_verified;
            $tempRow['is_user_verified'] = $customer->verifyCustomer?->status === 'approved';
            $tempRow['agent_profile'] = $customer->agent_profile;
            $isAppointmentAvailable = $customer->is_appointment_available;
        } elseif ($row->added_by == 0) {
            $isBlockedByMe = false;
            $isBlockedByAdmin = false;

            if ($current_user) {

                $isBlockedByMe = BlockedChatUser::where('by_user_id', $current_user)
                    ->where('admin', 1)
                    ->exists();

                $isBlockedByUser = BlockedChatUser::where('by_admin', 1)
                    ->where('user_id', $current_user)
                    ->exists();
            }
            $tempRow['is_blocked_by_me'] = $isBlockedByMe;
            $tempRow['is_blocked_by_user'] = $isBlockedByAdmin;

            $adminData = User::where('type', 0)->select('id', 'name', 'slug_id', 'profile')->first();

            $adminCompanyTel1 = system_setting('company_tel1');
            $adminEmail = system_setting('company_email');
            $tempRow['customer_name'] = 'Admin';
            $tempRow['customer_slug_id'] = $adminData->slug_id;
            $tempRow['mobile'] = ! empty($adminCompanyTel1) ? $adminCompanyTel1 : '';
            $tempRow['email'] = ! empty($adminEmail) ? $adminEmail : '';
            $tempRow['profile'] = ! empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg');
            $tempRow['client_address'] = $row->client_address;
            $totalPropertiesOfAdmin = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->count();
            $totalProjectsOfAdmin = Projects::where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->count();
            $tempRow['total_projects'] = $totalProjectsOfAdmin;
            $tempRow['total_properties'] = $totalPropertiesOfAdmin;
            $tempRow['is_admin'] = true;
            $isAppointmentAvailable = $adminData->is_appointment_available;
        }

        $tempRow['id'] = $row->id;
        $tempRow['slug_id'] = $row->slug_id;
        $tempRow['title'] = $row->title;
        $tempRow['price'] = $row->price;
        $tempRow['category'] = $row->category;
        $tempRow['description'] = $row->description;
        $tempRow['address'] = $row->address;
        $tempRow['property_type'] = $row->propery_type;
        $tempRow['is_interest_available'] = $row->getRawOriginal('propery_type') == 0 || $row->getRawOriginal('propery_type') == 1 ? true : false;
        $tempRow['is_report_available'] = $row->getRawOriginal('propery_type') == 0 || $row->getRawOriginal('propery_type') == 1 ? true : false;
        $tempRow['request_status'] = $row->request_status;
        $tempRow['title_image'] = $row->title_image;
        $tempRow['title_image_hash'] = $row->title_image_hash != '' ? $row->title_image_hash : '';
        $tempRow['three_d_image'] = $row->three_d_image;
        $tempRow['posted_on'] = $row->created_at->diffForHumans();
        $tempRow['created_at'] = $row->created_at;
        $tempRow['gallery'] = $row->gallery;
        $tempRow['documents'] = $row->documents;
        $tempRow['total_view'] = $row->total_click;
        $tempRow['status'] = $row->status;
        $tempRow['state'] = $row->state;
        $tempRow['city'] = $row->city;
        $tempRow['country'] = $row->country;
        $tempRow['latitude'] = $row->latitude;
        $tempRow['longitude'] = $row->longitude;
        $tempRow['added_by'] = $row->added_by;
        $tempRow['role_context'] = $row->role_context ?? 'user';
        $tempRow['video_link'] = $row->video_link;
        $tempRow['rentduration'] = ($row->rentduration != '') ? $row->rentduration : 'Monthly';
        $tempRow['meta_title'] = ! empty($row->meta_title) ? $row->meta_title : $row->title;
        $tempRow['meta_description'] = ! empty($row->meta_description) ? $row->meta_description : $row->description;
        $tempRow['meta_keywords'] = $row->meta_keywords;
        $tempRow['meta_image'] = ! empty($row->meta_image) ? $row->meta_image : $row->title_image;
        $tempRow['is_premium'] = $row->is_premium == 1 ? true : false;
        $tempRow['assign_facilities'] = $row->assign_facilities;
        // $tempRow['is_verified'] = $row->is_user_verified;
        // $tempRow['is_verified_user'] = $row->is_user_verified;
        $tempRow['is_agent_verified'] = $row?->customer?->is_agent_verified ?? false;
        $tempRow['is_appointment_available'] = $isAppointmentAvailable;
        $tempRow['low_quality_title_image'] = $row->low_quality_title_image;
        if ($current_user) {
            $appointmentStatus = Appointment::where(['property_id' => $row->id, 'user_id' => $current_user])->whereNotIn('status', ['cancelled', 'completed', 'auto_cancelled'])->exists();
            $tempRow['is_appointment_available'] = $isAppointmentAvailable && $appointmentStatus == false ? true : false;
        }
        $tempRow['translated_title'] = $row->translated_title;
        $tempRow['translated_description'] = $row->translated_description;
        $tempRow['translations'] = $row->translations;

        // Get Property Inquiry Data on the basis of current user and status is completed
        $inquiry = PropertysInquiry::where('customers_id', $current_user)->where('propertys_id', $row->id)->where('status', 2)->first();
        if ($inquiry) {
            $tempRow['inquiry'] = true;
        } else {
            $tempRow['inquiry'] = false;
        }
        $tempRow['promoted'] = $row->is_promoted;

        $interested_users = [];
        $favourite_users = [];
        foreach ($row->favourite as $favourite_user) {
            if ($favourite_user->property_id == $row->id) {
                array_push($favourite_users, $favourite_user->user_id);
            }
        }
        foreach ($row->interested_users as $interested_user) {
            if ($interested_user->property_id == $row->id) {
                array_push($interested_users, $interested_user->customer_id);
            }
        }

        $tempRow['favourite_users'] = $favourite_users;
        $tempRow['total_favourite_users'] = count($favourite_users);
        $tempRow['interested_users'] = $interested_users;
        $tempRow['total_interested_users'] = count($interested_users);

        $favourite = Favourite::where('property_id', $row->id)->where('user_id', $current_user)->get();
        $interest = InterestedUser::where('property_id', $row->id)->where('customer_id', $current_user)->get();
        $report_count = user_reports::where('property_id', $row->id)->where('customer_id', $current_user)->get();

        if (count($report_count) != 0) {
            $tempRow['is_reported'] = true;
        } else {
            $tempRow['is_reported'] = false;
        }

        if (count($favourite) != 0) {
            $tempRow['is_favourite'] = 1;
        } else {
            $tempRow['is_favourite'] = 0;
        }

        if (count($interest) != 0) {
            $tempRow['is_interested'] = 1;
        } else {
            $tempRow['is_interested'] = 0;
        }

        if ($row->advertisement) {
            $tempRow['advertisement'] = $row->advertisement;
        }

        $tempRow['parameters'] = $row->parameters;

        $rows[] = $tempRow;
        $count++;
    }

    return $rows;
}
function get_language()
{
    return Language::get();
}
function get_unregistered_fcm_ids($registeredIDs = [])
{

    // Convert the arrays to lowercase for case-insensitive comparison
    $registeredIDsLower = array_map('strtolower', $registeredIDs);

    // Retrieve the FCM IDs from the 'usertoken' table
    $fcmIDs = Usertokens::pluck('fcm_id')->toArray();

    // Now you have an array ($fcmIDs) containing all the FCM IDs from the 'usertoken' table

    $allIDsLower = array_map('strtolower', $fcmIDs);

    // Use array_diff to find the FCM IDs that are not registered
    $unregisteredIDsLower = array_diff($allIDsLower, $registeredIDsLower);

    // Convert the IDs back to their original case
    $unregisteredIDs = array_map('strtoupper', $unregisteredIDsLower);
    Usertokens::WhereIn('fcm_id', $fcmIDs)->delete();
}
function handleFileUpload($request, $key, $destinationPath, $filename, $databaseData = null)
{
    if ($request->hasFile($key)) {
        if (! empty($databaseData)) {
            // Delete the old file if it exists
            $oldFilePath = $destinationPath.'/'.$databaseData;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
        $extension = $request->file($key)->getClientOriginalExtension();
        // Change the file name
        if (empty($filename)) {
            $filename = microtime(true).'.'.$extension;
        } else {
            $filename = $filename;
        }

        $profile = $request->file($key);
        $profile->move($destinationPath, $filename);
        $value = $filename;

        return $value;
    }

    return null;
}
function get_url_contents($url)
{
    $crl = curl_init();

    curl_setopt($crl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
    curl_setopt($crl, CURLOPT_URL, $url);
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, 5);

    $ret = curl_exec($crl);
    curl_close($crl);

    return $ret;
}

function check_subscription($user, $type, $status)
{
    DB::enableQueryLog();
    $current_package = OldUserPurchasedPackage::where('modal_id', $user)
        // ->with(['package' => function ($q) use ($type) {
        //     $q->select('id', $type)->where($type, '>', 0)->orWhere($type, null);
        // }])

        ->whereHas('package', function ($q) use ($type) {
            $q->where($type, '>', 0)->orWhere($type, null);
        })->where($status, 1)
        ->first();

    return $current_package;
}
function store_image($file, $path)
{
    $destinationPath = public_path('images').config('global.'.$path);
    if (! is_dir($destinationPath)) {
        mkdir($destinationPath, 0777, true);
    }

    // Check if the file is an instance of UploadedFile
    if ($file instanceof UploadedFile) {
        $extension = $file->getClientOriginalExtension();

        // Initialize the filename
        // $filename = $originalName . '.' . $extension;
        $filename = microtime(true).'.'.$extension;

        $file->move($destinationPath, $filename);

        return $filename;
    } else {
        // Handle the case when the file is not an instance of UploadedFile
        // You can log an error or throw an exception here
        // For now, we return null
        return null;
    }
}
function unlink_image($url)
{
    if (! empty($url)) {
        $relativePath = parse_url($url, PHP_URL_PATH);
        if (file_exists(public_path().$relativePath)) {
            unlink(public_path().$relativePath);
        }
    }
}

/** Generate Slugs Functions */
if (! function_exists('generateUniqueSlug')) {
    function generateUniqueSlug($title, $type, $originalSlug = null, $exceptId = null)
    {
        if (! $originalSlug) {
            $originalSlug = unicodeSlug($title);
        } else {
            $originalSlug = unicodeSlug($originalSlug);
        }

        if (empty($originalSlug)) {
            $originalSlug = 'slug';
        }

        $tableNames = [
            1 => 'propertys',
            2 => 'articles',
            3 => 'categories',
            4 => 'projects',
            5 => 'customers',
            6 => 'users',
            7 => 'custom_pages',
        ];

        $tableName = $tableNames[$type] ?? null;

        return generateSlug($originalSlug, $tableName, $exceptId);
    }
}

if (! function_exists('generateSlug')) {
    function generateSlug($originalSlug, $tableName, $exceptId)
    {
        $counter = 1;
        $slug = $originalSlug;

        if (empty($exceptId)) {
            while (DB::table($tableName)->where('slug_id', $slug)->exists()) {
                $slug = $originalSlug.'-'.$counter;
                $counter++;
            }
        } else {
            while (DB::table($tableName)->whereNot('id', $exceptId)->where('slug_id', $slug)->exists()) {
                $slug = $originalSlug.'-'.$counter;
                $counter++;
            }
        }

        return $slug;
    }
}

/** Unicode-friendly slug generator */
if (! function_exists('unicodeSlug')) {
    function unicodeSlug($string)
    {
        $slug = Str::slug($string);
        // // Replace spaces with hyphen
        // $slug = preg_replace('/\s+/u', '-', trim($string));

        // // Keep letters, numbers, combining marks, and hyphens
        // $slug = preg_replace('/[^\p{L}\p{M}\p{N}\-]+/u', '', $slug);

        // // Convert only English A–Z to lowercase
        // $slug = preg_replace_callback('/[A-Z]/u', function ($matches) {
        //     return mb_strtolower($matches[0], 'UTF-8');
        // }, $slug);

        return $slug;
    }
}

if (! function_exists('getAccessToken')) {
    function getAccessToken()
    {
        $file_name = system_setting('firebase_service_json_file');

        // Check if file exists in storage private, otherwise fallback to public/assets for backward compatibility
        if (! empty($file_name)) {
            $privatePath = storage_path('app/private/'.$file_name);
            $publicPath = public_path('assets/'.$file_name);

            // Prioritize storage private, fallback to public for migration period
            if (file_exists($privatePath)) {
                $file_path = $privatePath;
            } elseif (file_exists($publicPath)) {
                $file_path = $publicPath;
            } else {
                throw new Exception('Firebase service JSON file not found');
            }
        } else {
            throw new Exception('Firebase service JSON file name not configured');
        }

        $client = new Client;
        $client->setAuthConfig($file_path);
        $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);
        $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];

        return $accessToken;
    }
}
if (! function_exists('updateEnv')) {
    function updateEnv($envUpdates)
    {
        $envFile = file_get_contents(base_path('.env'));

        foreach ($envUpdates as $key => $value) {
            // Check if the key exists in the .env file
            if (strpos($envFile, "{$key}=") === false) {
                // If the key doesn't exist, add it
                $envFile .= "\n{$key}=\"{$value}\"";
            } else {
                // If the key exists, replace its value
                $envFile = preg_replace("/{$key}=.*/", "{$key}=\"{$value}\"", $envFile);
            }
        }

        // Save the updated .env file
        file_put_contents(base_path('.env'), $envFile);
    }
}
