<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Setting;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request as RequestFacades;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Stripe\Tax\Settings;
use Symfony\Component\Intl\Currencies;
use Throwable;
use ZipArchive;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    private CachingService $cache;

    public function __construct(CachingService $cache)
    {
        $this->cache = $cache;
    }

    public function index()
    {
        $type = last(request()->segments());

        $type1 = str_replace('-', '_', $type);

        if (! has_permissions('read', $type1)) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $settingData = Setting::where('type', $type1)->with('translations')->first();
        $translationLanguages = HelperService::getActiveLanguages();

        return view('settings.'.$type, compact('type', 'translationLanguages', 'settingData'));
    }

    public function settings(Request $request)
    {
        try {

            $permissionType = str_replace('-', '_', $request->type);

            if (! has_permissions('update', $permissionType)) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            } else {
                DB::beginTransaction();
                $request->validate([
                    'data' => 'required',
                ]);

                $type1 = $request->type;
                if ($type1 != '') {
                    $message = Setting::where('type', $type1)->first();
                    if (empty($message)) {
                        Setting::create([
                            'type' => $type1,
                            'data' => $request->data,
                        ]);
                    } else {
                        $data['data'] = $request->data;
                        Setting::where('type', $type1)->update($data);
                    }
                    $setting = Setting::where('type', $type1)->first();
                    // START ::Add Translations
                    if (isset($request->translations) && ! empty($request->translations)) {
                        $translationData = [];
                        foreach ($request->translations as $translation) {
                            if (isset($translation['value']) && ! empty($translation['value'])) {
                                $translationData[] = [
                                    'id' => $translation['id'] ?? null,
                                    'translatable_id' => $setting->id,
                                    'translatable_type' => 'App\Models\Setting',
                                    'language_id' => $translation['language_id'],
                                    'key' => 'data',
                                    'value' => $translation['value'],
                                ];
                            }
                        }
                        if (! empty($translationData)) {
                            HelperService::storeTranslations($translationData);
                        }
                    }
                    DB::commit();

                    // END ::Add Translations
                    return redirect(str_replace('_', '-', $type1))->with('success', trans('Data Updated Successfully'));
                } else {
                    return redirect(str_replace('_', '-', $type1))->with('error', 'Something Wrong');
                }
            }
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', trans('Something Went Wrong'));
        }
    }

    public function paymentGatewaySettingsIndex()
    {
        if (! has_permissions('read', 'payment_gateway_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $stripe_currencies = ['USD', 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLE', 'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TOP', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW'];
        $languages = HelperService::getActiveLanguages();

        $paypalCurrencies = [
            'AUD' => 'Australian Dollar',
            'BRL' => 'Brazilian Real',
            'CAD' => 'Canadian Dollar',
            'CNY' => 'Chinese Renmenbi',
            'CZK' => 'Czeck Koruna',
            'DKK' => 'Danish Krone',
            'EUR' => 'Euro',
            'HKD' => 'Hong Kong Dollar',
            'HUF' => 'Hungarian Forint',
            'ILS' => 'Israeli New Sheqel',
            'JPY' => 'Japanese Yen',
            'MYR' => 'Malaysian Ringgit',
            'MXN' => 'Mexican Peso',
            'NOK' => 'Norwegian Krone',
            'TWD' => 'New Taiwan dollar',
            'NZD' => 'New Zealand Dollar',
            'NOK' => 'Norwegian krone',
            'PHP' => 'Philippine Peso',
            'PLN' => 'Polish Zloty',
            'GBP' => 'Pound Sterling',
            'SGD' => 'Singapore Dollar',
            'SEK' => 'Swedish Krona',
            'CHF' => 'Swiss Franc',
            'THB' => 'Thai Baht',
            'USD' => 'U.S. Dollar',
        ];
        $cashfreeCurrencies = [
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
            'USD' => ['name' => 'United States Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'British Pound Sterling', 'symbol' => '£'],
            'AED' => ['name' => 'United Arab Emirates Dirham', 'symbol' => 'د.إ'],
            'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
            'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
            'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼'],
            'QAR' => ['name' => 'Qatari Riyal', 'symbol' => '﷼'],
            'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'KD'],
            'OMR' => ['name' => 'Omani Rial', 'symbol' => '﷼'],
            'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => '.د.ب'],
            'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
            'THB' => ['name' => 'Thai Baht', 'symbol' => '฿'],
            'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
            'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱'],
            'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
            'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh'],
            'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦'],
            'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => '₵'],
            'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => '₨'],
            'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳'],
            'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => 'Rs'],
            'NPR' => ['name' => 'Nepalese Rupee', 'symbol' => '₨'],
            'MMK' => ['name' => 'Myanmar Kyat', 'symbol' => 'K'],
            'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫'],
            'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
            'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
            'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr'],
            'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
            'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł'],
            'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
            'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
            'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$'],
        ];

        $bankDetailsFieldsQuery = system_setting('bank_details');
        if (isset($bankDetailsFieldsQuery) && ! empty($bankDetailsFieldsQuery)) {
            $bankDetailsFields = json_decode($bankDetailsFieldsQuery, true);
        } else {
            $bankDetailsFields = [];
        }

        $settingsArray = [
            'paypal_client_id',
            'paypal_client_secret',
            'paypal_webhook_url',
            'paypal_currency',
            'paypal_gateway',
            'sandbox_mode',
            'paypal_webhook_id',
            'razor_key',
            'razorpay_webhook_url',
            'razor_secret',
            'razorpay_gateway',
            'razor_webhook_secret',
            'paystack_secret_key',
            'paystack_webhook_url',
            'paystack_currency',
            'paystack_gateway',
            'paystack_public_key',
            'stripe_publishable_key',
            'stripe_webhook_url',
            'stripe_currency',
            'stripe_gateway',
            'stripe_secret_key',
            'stripe_webhook_secret_key',
            'flutterwave_public_key',
            'flutterwave_secret_key',
            'flutterwave_encryption_key',
            'flutterwave_webhook_url',
            'flutterwave_currency',
            'flutterwave_status',
            'cashfree_app_id',
            'cashfree_secret_key',
            'cashfree_webhook_url',
            'cashfree_currency',
            'cashfree_gateway',
            'cashfree_sandbox_mode',
            'bank_transfer_status',
            'phonepe_client_id',
            'phonepe_client_secret',
            'phonepe_merchant_id',
            'phonepe_gateway',
            'phonepe_webhook_url',
            'phonepe_sandbox_mode',
            'phonepe_client_version',
            'phonepe_webhook_username',
            'phonepe_webhook_password',
            'midtrans_server_key',
            'midtrans_client_key',
            'midtrans_merchant_id',
            'midtrans_gateway',
            'midtrans_sandbox_mode',
        ];
        $paymentSettings = HelperService::getMultipleSettingData($settingsArray, true);

        return view('settings.payment-gateway-settings', compact('paymentSettings', 'languages', 'stripe_currencies', 'paypalCurrencies', 'cashfreeCurrencies', 'bankDetailsFields'));
    }

    public function payment_gateway_settings(Request $request)
    {
        if (! has_permissions('update', 'payment_gateway_settings')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            DB::beginTransaction();
            $input = $request->except(['_token', 'btnAdd', 'bank_details_fields']);

            if (($request->has('bank_transfer_status') && $request->bank_transfer_status == 0) && $request->razorpay_gateway == 0 && $request->paystack_gateway == 0 && $request->flutterwave_status == 0 && $request->stripe_gateway == 0 && $request->paypal_gateway == 0 && $request->cashfree_gateway == 0 && $request->phonepe_gateway == 0 && $request->midtrans_gateway == 0) {
                ResponseService::errorResponse('Please enable at least one payment gateway');
            }

            if ($request->has('bank_transfer_status')) {
                $bankDetailsEnabled = $request->bank_transfer_status;
                if ($bankDetailsEnabled == 1) {
                    $rules = [
                        'bank_details_fields' => 'required|array',
                    ];

                    $messages = [
                        'bank_details_fields.required' => 'Bank Details Fields is required',
                    ];

                    // Loop through each item to dynamically add rules and custom messages
                    foreach ($request->input('bank_details_fields', []) as $i => $field) {
                        $index = $i + 1;

                        $rules["bank_details_fields.$i.title"] = 'required';
                        $rules["bank_details_fields.$i.value"] = 'required';

                        $messages["bank_details_fields.$i.title.required"] = "Bank Details : $index Title is required";
                        $messages["bank_details_fields.$i.value.required"] = "Bank Details : $index Value is required";
                    }

                    $validator = Validator::make($request->all(), $rules, $messages);

                    if ($validator->fails()) {
                        ResponseService::validationError($validator->errors()->first());
                    }
                    $bankDetails = [];
                    foreach ($request->bank_details_fields as $key => $field) {
                        // Handle translations with the new flat structure
                        $tempArray = [
                            'title' => $field['title'],
                            'value' => $field['value'],
                        ];

                        // Loop through the option array to find translation data
                        // Check if this is a translation language ID
                        foreach ($field as $key => $value) {
                            if (str_starts_with($key, 'translation_language_id_')) {
                                $languageId = str_replace('translation_language_id_', '', $key);
                                $translationKey = 'translation_value_'.$languageId;
                                if (isset($field[$translationKey]) && ! empty($field[$translationKey])) {
                                    $tempArray['translations'][] = [
                                        'language_id' => $languageId,
                                        'title' => $field[$translationKey],
                                    ];
                                }
                            }
                        }
                        $bankDetails[] = $tempArray;
                    }

                    $input['bank_details'] = json_encode($bankDetails);
                }
            }

            $envUpdates = [
                'PAYPAL_CURRENCY' => $request->paypal_currency,
                'PAYPAL_SANDBOX' => $request->sandbox_mode == 1 ? 1 : 0,
                'FLW_PUBLIC_KEY' => $request->flutterwave_public_key ?? '',
                'FLW_SECRET_KEY' => $request->flutterwave_secret_key ?? '',
                'FLW_SECRET_HASH' => $request->flutterwave_encryption_key ?? '',
                'PAYSTACK_PUBLIC_KEY' => $request->paystack_public_key ?? '',
                'PAYSTACK_SECRET_KEY' => $request->paystack_secret_key ?? '',
                'PAYSTACK_PAYMENT_URL' => 'https://api.paystack.co',
            ];

            if ($request->has('paypal_business_id') && ! empty($request->paypal_business_id)) {
                $envUpdates['BUSINESS'] = $request->paypal_business_id;
            }

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

            // Create or update records in the 'settings' table
            foreach ($input as $key => $value) {
                if ($key == 'paypal_web_url' && ! empty($value)) {
                    // remove / from end of value
                    $value = rtrim($value, '/');
                }
                Setting::updateOrCreate(['type' => $key], ['data' => $value]);
            }

            Artisan::call('cache:clear');

            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'Something Went Wrong');
        }
    }

    public function systemSettingsIndex()
    {
        if (! has_permissions('read', 'system_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $languagesWithEnglish = HelperService::getActiveLanguages(null, true);
        $languages = HelperService::getActiveLanguages();
        $listOfCurrencies = HelperService::currencyCode();

        $settingsArray = [
            'company_name',
            'company_email',
            'company_tel1',
            'company_tel2',
            'latitude',
            'longitude',
            'company_address',
            'currency_code',
            'currency_symbol',
            'timezone',
            'min_radius_range',
            'max_radius_range',
            'map_service_provider',
            'geonames_username',
            'map_api_key',
            'place_api_key',
            'unsplash_api_key',
            'appstore_id',
            'playstore_id',
            'number_with_suffix',
            'svg_clr',
            'distance_option',
            'system_color',
            'web_url',
            'auto_approve_edited_listings',
            'number_with_otp_login',
            'otp_service_provider',
            'twilio_account_sid',
            'twilio_auth_token',
            'twilio_my_phone_number',
            'social_login',
            'email_password_login',
            'schema_for_deeplink',
            'favicon_icon',
            'company_logo',
            'login_image',
            'default_language',
            'notify_user_for_subscription_expiry',
            'show_direct_video_upload',
            'show_whatsapp_button',
            'story_max_duration',
            'story_video_max_size',
            'days_before_subscription_expiry',
            'homepage_location_alert_status',
            'show_premium_toggle',
            'show_exact_location',
            'audit_log_enabled',
        ];
        $systemSettings = HelperService::getMultipleSettingData($settingsArray, true);

        return view('settings.system-settings', compact('systemSettings', 'languages', 'languagesWithEnglish', 'listOfCurrencies'));
    }

    public function system_settings(Request $request)
    {

        if (! has_permissions('update', 'system_settings')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            DB::beginTransaction();
            $input = $request->except(['_token', 'btnAdd']);

            $logoDestinationPath = public_path('assets/images/logo');
            $backgroundDestinationPath = public_path('assets/images/bg');

            if ($request->hasFile('favicon_icon')) {
                $filename = 'favicon.'.$request->file('favicon_icon')->getClientOriginalExtension();

                // Get Data from Settings table
                $faviconDatabaseData = system_setting('favicon_icon');
                $databaseData = ! empty($faviconDatabaseData) ? $faviconDatabaseData : null;

                $input['favicon_icon'] = handleFileUpload($request, 'favicon_icon', $logoDestinationPath, $filename, $databaseData);
            }
            if ($request->hasFile('company_logo')) {
                $filename = 'logo.'.$request->file('company_logo')->getClientOriginalExtension();

                // Get Data from Settings table
                $companyLogoDatabaseData = system_setting('company_logo');
                $databaseData = ! empty($companyLogoDatabaseData) ? $companyLogoDatabaseData : null;

                $input['company_logo'] = handleFileUpload($request, 'company_logo', $logoDestinationPath, $filename, $databaseData);
            }
            if ($request->hasFile('login_image')) {
                $filename = 'Login_BG.'.$request->file('login_image')->getClientOriginalExtension();

                // Get Data from Settings table
                $LoginImageDatabaseData = system_setting('company_logo');
                $databaseData = ! empty($LoginImageDatabaseData) ? $LoginImageDatabaseData : null;

                $input['login_image'] = handleFileUpload($request, 'login_image', $backgroundDestinationPath, $filename, $databaseData);
            }

            if ($request->has('bank_transfer_status')) {
                $bankDetailsEnabled = $request->bank_transfer_status;
                if ($bankDetailsEnabled == 1) {
                    $rules = [
                        'bank_details_fields' => 'required|array',
                    ];

                    $messages = [
                        'bank_details_fields.required' => 'Bank Details Fields is required',
                    ];

                    // Loop through each item to dynamically add rules and custom messages
                    foreach ($request->input('bank_details_fields', []) as $i => $field) {
                        $index = $i + 1;

                        $rules["bank_details_fields.$i.title"] = 'required';
                        $rules["bank_details_fields.$i.value"] = 'required';

                        $messages["bank_details_fields.$i.title.required"] = "Bank Details : $index Title is required";
                        $messages["bank_details_fields.$i.value.required"] = "Bank Details : $index Value is required";
                    }

                    $validator = Validator::make($request->all(), $rules, $messages);

                    if ($validator->fails()) {
                        ResponseService::validationError($validator->errors()->first());
                    }
                    $bankDetails = [];
                    foreach ($request->bank_details_fields as $key => $field) {
                        // Handle translations with the new flat structure
                        $tempArray = [
                            'title' => $field['title'],
                            'value' => $field['value'],
                        ];

                        // Loop through the option array to find translation data
                        // Check if this is a translation language ID
                        foreach ($field as $key => $value) {
                            if (str_starts_with($key, 'translation_language_id_')) {
                                $languageId = str_replace('translation_language_id_', '', $key);
                                $translationKey = 'translation_value_'.$languageId;
                                if (isset($field[$translationKey]) && ! empty($field[$translationKey])) {
                                    $tempArray['translations'][] = [
                                        'language_id' => $languageId,
                                        'title' => $field[$translationKey],
                                    ];
                                }
                            }
                        }
                        $bankDetails[] = $tempArray;
                    }

                    $input['bank_details'] = json_encode($bankDetails);
                }
            }

            $envUpdates = [
                'APP_NAME' => $request->company_name,
                'PLACE_API_KEY' => $request->place_api_key,
                'MAP_API_KEY' => $request->map_api_key,
                'UNSPLASH_API_KEY' => $request->unsplash_api_key,
                'PRIMARY_COLOR' => $request->system_color,
                'PRIMARY_RGBA_COLOR' => $request->rgb_color,
            ];

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

            // Create or update records in the 'settings' table
            foreach ($input as $key => $value) {
                if ($key == 'paypal_web_url' && ! empty($value)) {
                    // remove / from end of value
                    $value = rtrim($value, '/');
                }
                Setting::updateOrCreate(['type' => $key], ['data' => $value]);
            }

            $this->cache->removeSystemCache(config('constants.CACHE.SYSTEM.DEFAULT_LANGUAGE'));

            // Add New Default in Session
            $defaultLanguage = $this->cache->getDefaultLanguage();
            Session::remove('language');
            Session::remove('locale');
            Session::put('language', $defaultLanguage);
            Session::put('locale', $defaultLanguage->code);
            Session::save();
            app()->setLocale($defaultLanguage->code);
            Artisan::call('cache:clear');

            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'Something Went Wrong');
        }
    }

    public function firebase_settings(Request $request)
    {
        if (! has_permissions('update', 'firebase_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $input = $request->all();

            unset($input['btnAdd1']);
            unset($input['_token']);
            foreach ($input as $key => $value) {
                $result = Setting::where('type', $key)->first();
                if (empty($result)) {
                    Setting::create([
                        'type' => $key,
                        'data' => $value,
                    ]);
                } else {
                    $data['data'] = ($value) ? $value : '';
                    Setting::where('type', $key)->update($data);
                }
            }
        }

        return redirect()->back()->with('success', trans('Data Updated Successfully'));
    }

    public function system_version()
    {
        if (! has_permissions('read', 'system_update')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('settings.system_version');
    }

    public function show_privacy_policy()
    {
        $appName = env('APP_NAME', 'eBroker');
        $privacy_policy = Setting::select('data')->where('type', 'privacy_policy')->first();

        return view('settings.show_privacy_policy', compact('privacy_policy', 'appName'));
    }

    public function show_terms_conditions()
    {
        $terms_conditions = Setting::select('data')->where('type', 'terms_conditions')->first();

        return view('settings.show_terms_conditions', compact('terms_conditions'));
    }
    // public function system_version_setting(Request $request)
    // {
    //     if (!has_permissions('update', 'system_update')) {
    //         return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'purchase_code' => 'required',
    //         'file' => 'required|file|mimes:zip',
    //     ]);

    //     if ($validator->fails()) {
    //         return redirect()->back()->with('error', $validator->errors()->first());
    //     }

    //     $destinationPath = public_path() . '/update/tmp/';
    //     $app_url = (string)url('/');
    //     $app_url = preg_replace('#^https?://#i', '', $app_url);

    //     $curl = curl_init();
    //     curl_setopt_array($curl, array(
    //         CURLOPT_URL => 'https://validator.wrteam.in/ebroker_validator?purchase_code=' . $request->purchase_code . '&domain_url=' . $app_url . '',
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => 'GET',
    //     ));
    //     $response = curl_exec($curl);
    //     $info = curl_getinfo($curl);

    //     curl_close($curl);

    //     $response = json_decode($response, true);
    //     if ($response['error']) {
    //         $response = array(
    //             'error' => true,
    //             'message' => $response["message"],
    //             'info' => $info
    //         );

    //         return redirect()->back()->with('error', $response["message"]);
    //     } else {
    //         if (!is_dir($destinationPath)) {
    //             mkdir($destinationPath, 0777, TRUE);
    //         }

    //         // zip upload
    //         $zipfile = $request->file('file');
    //         $fileName = $zipfile->getClientOriginalName();
    //         $zipfile->move($destinationPath, $fileName);

    //         $target_path = base_path();

    //         $zip = new ZipArchive();
    //         $filePath = $destinationPath . '/' . $fileName;
    //         $zipStatus = $zip->open($filePath);
    //         if ($zipStatus) {
    //             $zip->extractTo($destinationPath);
    //             $zip->close();
    //             unlink($filePath);

    //             $ver_file = $destinationPath . '/version_info.php';
    //             $source_path = $destinationPath . '/source_code.zip';
    //             if (file_exists($ver_file) && file_exists($source_path)) {
    //                 $ver_file1 = $target_path . '/version_info.php';
    //                 $source_path1 = $target_path . '/source_code.zip';
    //                 if (rename($ver_file, $ver_file1) && rename($source_path, $source_path1)) {
    //                     $version_file = require_once($ver_file1);

    //                     $current_version = Setting::select('data')->where('type', 'system_version')->pluck('data')->first();
    //                     if ($current_version == $version_file['current_version']) {
    //                         $zip1 = new ZipArchive();
    //                         $zipFile1 = $zip1->open($source_path1);
    //                         if ($zipFile1 === true) {
    //                             $zip1->extractTo($target_path);
    //                             $zip1->close();

    //                             Artisan::call('migrate');
    //                             unlink($source_path1);
    //                             unlink($ver_file1);
    //                             Setting::where('type', 'system_version')->update([
    //                                 'data' => $version_file['update_version']
    //                             ]);

    //                             $envUpdates = [
    //                                 'APP_URL' => RequestFacades::root(),
    //                             ];
    //                             updateEnv($envUpdates);
    //                             Artisan::call('optimize:clear');

    //                             return redirect()->back()->with('success', trans('System Updated Successfully'));
    //                         } else {
    //                             unlink($source_path1);
    //                             unlink($ver_file1);

    //                             return redirect()->back()->with('error', trans('Something Went Wrong'));
    //                         }
    //                     } else if ($current_version == $version_file['update_version']) {
    //                         unlink($source_path1);
    //                         unlink($ver_file1);

    //                         return redirect()->back()->with('error', trans('System Already Updated'));
    //                     } else {
    //                         unlink($source_path1);
    //                         unlink($ver_file1);

    //                         return redirect()->back()->with('error', $current_version . ' ' . trans('Update your version nearest to it'));
    //                     }
    //                 } else {

    //                     return redirect()->back()->with('error', trans('Invalid Zip Try Again'));
    //                 }
    //             } else {

    //                 return redirect()->back()->with('error', trans('Invalid Zip Try Again'));
    //             }
    //         } else {
    //             return redirect()->back()->with('error', trans('Something Went Wrong'));
    //         }
    //     }
    // }
    public function system_version_setting(Request $request)
    {
        if (! has_permissions('update', 'system_update')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $validator = Validator::make($request->all(), [
            'purchase_code' => 'required',
            'file' => 'required|file|mimes:zip',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        // Validate purchase code
        $app_url = (string) url('/');
        $app_url = preg_replace('#^https?://#i', '', $app_url);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://validator.wrteam.in/ebroker_validator?purchase_code='.$request->purchase_code.'&domain_url='.$app_url.'',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response, true);

        if (empty($response) || ! empty($response['error'])) {
            return redirect()->back()->with('error', $response['message'] ?? trans('Something Went Wrong'));
        }

        $destinationPath = public_path('update/tmp');
        $target_path = base_path();

        if (! is_dir($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        // Upload ZIP
        $zipfile = $request->file('file');
        $fileName = time().'_'.$zipfile->getClientOriginalName();
        $zipfile->move($destinationPath, $fileName);

        $zip = new ZipArchive;
        $filePath = $destinationPath.'/'.$fileName;

        if ($zip->open($filePath) !== true) {
            return redirect()->back()->with('error', 'Invalid ZIP file');
        }

        // Extract main ZIP
        $zip->extractTo($destinationPath);
        $zip->close();
        unlink($filePath);

        $ver_file = $destinationPath.'/version_info.php';
        $source_path = $destinationPath.'/source_code.zip';

        if (! file_exists($ver_file) || ! file_exists($source_path)) {
            return redirect()->back()->with('error', 'Invalid update package');
        }

        // Move files to root
        $ver_file1 = $target_path.'/version_info.php';
        $source_path1 = $target_path.'/source_code.zip';

        rename($ver_file, $ver_file1);
        rename($source_path, $source_path1);

        $version_file = require_once $ver_file1;

        $current_version = Setting::where('type', 'system_version')->value('data');

        if ($current_version != $version_file['current_version']) {
            unlink($source_path1);
            unlink($ver_file1);

            return redirect()->back()->with('error', 'Please update to nearest version first');
        }

        //  OPEN SOURCE ZIP
        $zip1 = new ZipArchive;

        if ($zip1->open($source_path1) !== true) {
            unlink($source_path1);
            unlink($ver_file1);

            return redirect()->back()->with('error', 'Unable to open source zip');
        }

        //  FORCE OVERWRITE FILES
        for ($i = 0; $i < $zip1->numFiles; $i++) {

            $originalName = $zip1->getNameIndex($i);

            // Remove "source_code/" prefix if exists
            $fileName = preg_replace('#^source_code/#', '', $originalName);

            if (empty($fileName)) {
                continue;
            }

            $filePath = $target_path.'/'.$fileName;

            // Handle directories
            if (substr($originalName, -1) == '/') {
                if (! file_exists($filePath)) {
                    mkdir($filePath, 0777, true);
                }

                continue;
            }

            $dir = dirname($filePath);

            if (! file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            //  Check writable
            if (! is_writable($dir)) {
                unlink($source_path1);
                unlink($ver_file1);

                return redirect()->back()->with('error', 'Permission denied: '.$dir);
            }

            //  FORCE overwrite
            if (! copy('zip://'.$source_path1.'#'.$originalName, $filePath)) {
                unlink($source_path1);
                unlink($ver_file1);

                return redirect()->back()->with('error', 'Failed to copy: '.$originalName);
            }
        }

        $zip1->close();

        // Run migrations
        Artisan::call('migrate', ['--force' => true]);

        // Cleanup
        unlink($source_path1);
        unlink($ver_file1);

        // Update version
        Setting::where('type', 'system_version')->update([
            'data' => $version_file['update_version'],
        ]);

        // Update env
        updateEnv([
            'APP_URL' => url('/'),
        ]);

        // Clear cache
        Artisan::call('optimize:clear');

        return redirect()->back()->with('success', 'System Updated Successfully');
    }

    public function appSettingsIndex()
    {
        if (! has_permissions('read', 'app_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $settingsArray = [
            'ios_version',
            'android_version',
            'force_update',
            'maintenance_mode',
            'light_tertiary',
            'light_secondary',
            'light_primary',
            'dark_tertiary',
            'dark_secondary',
            'dark_primary',
            'show_admob_ads',
            'android_banner_ad_id',
            'ios_banner_ad_id',
            'android_interstitial_ad_id',
            'ios_interstitial_ad_id',
            'android_native_ad_id',
            'ios_native_ad_id',
            'app_home_screen',
            'placeholder_logo',
            'dark_mode_logo',
            'app_login_background',
        ];
        $getAppSettings = HelperService::getMultipleSettingData($settingsArray);

        return view('settings.app-settings', compact('getAppSettings'));
    }

    public function app_settings(Request $request)
    {
        if (! has_permissions('update', 'app_settings')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'app_home_screen' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3000',
                'placeholder_logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3000',
            ], [
                'app_home_screen.mimes' => trans('Image must be JPG, JPEG, PNG or WebP'),
                'placeholder_logo.mimes' => trans('Image must be JPG, JPEG, PNG or WebP'),
            ]);
            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }
            $input = $request->except(['_token', 'btnAdd']);
            $destinationPath = public_path('assets/images/logo');

            $fileKeys = ['app_home_screen', 'placeholder_logo', 'dark_mode_logo', 'app_login_background'];
            foreach ($fileKeys as $key) {
                if (! $request->hasFile($key) || ! $request->file($key)->isValid()) {
                    unset($input[$key]);
                }
            }

            if ($request->hasFile('app_home_screen') && $request->file('app_home_screen')->isValid()) {
                $filename = 'homeLogo.'.$request->file('app_home_screen')->getClientOriginalExtension();

                // Get Data from Settings table
                $appHomeScreenDatabaseData = system_setting('app_home_screen');
                $databaseData = ! empty($appHomeScreenDatabaseData) ? $appHomeScreenDatabaseData : null;

                $input['app_home_screen'] = handleFileUpload($request, 'app_home_screen', $destinationPath, $filename, $databaseData);
            }
            if ($request->hasFile('placeholder_logo') && $request->file('placeholder_logo')->isValid()) {
                $filename = 'placeholder.'.$request->file('placeholder_logo')->getClientOriginalExtension();

                // Get Data from Settings table
                $placeHolderLogoDatabaseData = system_setting('placeholder_logo');
                $databaseData = ! empty($placeHolderLogoDatabaseData) ? $placeHolderLogoDatabaseData : null;

                $input['placeholder_logo'] = handleFileUpload($request, 'placeholder_logo', $destinationPath, $filename, $databaseData);
            }

            if ($request->hasFile('dark_mode_logo') && $request->file('dark_mode_logo')->isValid()) {

                $filename = 'dark_mode_logo.'.$request->file('dark_mode_logo')->getClientOriginalExtension();
                // Get Data from Settings tableweb
                $darkModeLogoDatabaseData = HelperService::getSettingData('dark_mode_logo');
                $databaseData = ! empty($darkModeLogoDatabaseData) ? $darkModeLogoDatabaseData : null;

                $input['dark_mode_logo'] = handleFileUpload($request, 'dark_mode_logo', $destinationPath, $filename, $databaseData);
            }

            if ($request->hasFile('app_login_background') && $request->file('app_login_background')->isValid()) {
                $filename = 'app_login_background.'.$request->file('app_login_background')->getClientOriginalExtension();
                // Get Data from Settings table
                $appLoginBackgroundDatabaseData = HelperService::getSettingData('app_login_background');
                $databaseData = ! empty($appLoginBackgroundDatabaseData) ? $appLoginBackgroundDatabaseData : null;

                $input['app_login_background'] = handleFileUpload($request, 'app_login_background', $destinationPath, $filename, $databaseData);
            }

            foreach ($input as $key => $value) {

                Setting::updateOrCreate(['type' => $key], ['data' => $value]);
            }
        }

        return redirect()->back()->with('success', trans('Data Updated Successfully'));
    }

    public function webSettingsIndex()
    {
        if (! has_permissions('read', 'web_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $settingsArray = ['web_favicon', 'web_logo', 'web_placeholder_logo', 'web_footer_logo', 'iframe_link', 'facebook_id', 'instagram_id','linkedin_id', 'twitter_id', 'youtube_id', 'category_background', 'sell_web_color', 'sell_web_background_color', 'rent_web_color', 'rent_web_background_color', 'buy_web_color', 'buy_web_background_color', 'web_maintenance_mode', 'allow_cookies'];
        $getWebSettings = HelperService::getMultipleSettingData($settingsArray);

        return view('settings.web-settings', compact('getWebSettings'));
    }

    public function web_settings(Request $request)
    {
        if (! has_permissions('update', 'web_settings')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'web_logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3000',
                'web_placeholder_logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3000',
                'web_footer_logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3000',
                'web_favicon' => 'nullable|mimes:png,jpg,jpeg,ico,webp|max:3000',
            ], [
                'web_logo.mimes' => trans('Image must be JPG, JPEG, PNG or WebP'),
                'web_placeholder_logo.mimes' => trans('Image must be JPG, JPEG, PNG or WebP'),
                'web_footer_logo.mimes' => trans('Image must be JPG, JPEG, PNG or WebP'),
                'web_favicon.mimes' => trans('Image must be JPG, JPEG, PNG, ICO or WebP'),
            ]);
            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }
            $input = $request->except(['_token', 'btnAdd']);
            $destinationPath = public_path('assets/images/logo');

            if ($request->hasFile('web_logo')) {
                $file = $request->file('web_logo');

                // Get Data from Settings table
                $webLogoDatabaseData = system_setting('web_logo');
                $databaseData = ! empty($webLogoDatabaseData) ? $webLogoDatabaseData : null;

                $input['web_logo'] = handleFileUpload($request, 'web_logo', $destinationPath, $file->getClientOriginalName(), $databaseData);
            }
            if ($request->hasFile('web_placeholder_logo') && $request->file('web_placeholder_logo')->isValid()) {
                $file = $request->file('web_placeholder_logo');

                // Get Data from Settings table
                $webPlaceholderLogoDatabaseData = system_setting('web_placeholder_logo');
                $databaseData = ! empty($webPlaceholderLogoDatabaseData) ? $webPlaceholderLogoDatabaseData : null;

                $input['web_placeholder_logo'] = handleFileUpload($request, 'web_placeholder_logo', $destinationPath, $file->getClientOriginalName(), $databaseData);
            }
            if ($request->hasFile('web_favicon') && $request->file('web_favicon')->isValid()) {
                $file = $request->file('web_favicon');

                // Get Data from Settings table
                $webFavicon = system_setting('web_favicon');
                $databaseData = ! empty($webFavicon) ? $webFavicon : null;

                $input['web_favicon'] = handleFileUpload($request, 'web_favicon', $destinationPath, $file->getClientOriginalName(), $databaseData);
            }
            if ($request->hasFile('web_footer_logo') && $request->file('web_footer_logo')->isValid()) {
                $file = $request->file('web_footer_logo');

                // Get Data from Settings table
                $webFooterLogo = system_setting('web_footer_logo');
                $databaseData = ! empty($webFooterLogo) ? $webFooterLogo : null;

                $input['web_footer_logo'] = handleFileUpload($request, 'web_footer_logo', $destinationPath, $file->getClientOriginalName(), $databaseData);
            }

            foreach ($input as $key => $value) {

                Setting::updateOrCreate(['type' => $key], ['data' => $value]);
            }
        }

        return redirect()->back()->with('success', trans('Data Updated Successfully'));
    }

    public function notificationSettingIndex()
    {
        if (! has_permissions('read', 'notification_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $firebaseProjectId = Setting::where('type', 'firebase_project_id')->pluck('data')->first();
        $firebaseServiceJsonFile = Setting::where('type', 'firebase_service_json_file')->pluck('data')->first();

        // Check if file actually exists in storage private or public/assets (for backward compatibility)
        if (! empty($firebaseServiceJsonFile)) {
            $privatePath = storage_path('app/private/'.$firebaseServiceJsonFile);
            $publicPath = public_path('assets/'.$firebaseServiceJsonFile);

            // If file doesn't exist in either location, clear the setting
            if (! file_exists($privatePath) && ! file_exists($publicPath)) {
                $firebaseServiceJsonFile = null;
            }
        }

        return view('settings.notification-settings', compact('firebaseProjectId', 'firebaseServiceJsonFile'));
    }

    public function notificationSettingStore(Request $request)
    {
        if (! has_permissions('update', 'notification_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            // Declare the variables
            $directType = ['firebase_project_id'];
            $fileType = ['firebase_service_json_file'];

            // Loop to other than file data
            foreach ($directType as $type) {
                $data = $request->$type;
                Setting::updateOrCreate(['type' => $type], ['data' => $data]);
            }

            // Loop to file data
            foreach ($fileType as $type) {
                if ($type == 'firebase_service_json_file') {
                    // When Type is firebase service file, save to storage private
                    if ($request->hasFile($type)) {
                        $file = $request->file($type);
                        $fileName = 'firebase-service.json';

                        // Delete old file if exists
                        $oldFileName = system_setting('firebase_service_json_file');
                        if (! empty($oldFileName) && Storage::disk('local')->exists('private/'.$oldFileName)) {
                            Storage::disk('local')->delete('private/'.$oldFileName);
                        }

                        // Save to storage/app/private
                        $file->storeAs('private', $fileName, 'local');
                        Setting::updateOrCreate(['type' => $type], ['data' => $fileName]);
                    }
                } else {
                    // When other file then use public path
                    $destinationPath = public_path('assets');
                    $file = $request->file($type);
                    if ($request->hasFile($type)) {
                        $name = handleFileUpload($request, $type, $destinationPath, $file->getClientOriginalName());
                        Setting::updateOrCreate(['type' => $type], ['data' => $name]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', trans('Data Updated Successfully'));
    }

    // Email Configuration Index
    public function emailConfigurationsIndex()
    {
        if (! has_permissions('read', 'email_configurations')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('settings.email-configurations');
    }

    // Email Configuration Store
    public function emailConfigurationsStore(Request $request)
    {
        if (! has_permissions('update', 'email_configurations')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $isGodaddy = $request->mail_mailer == 'godaddy';
            $validator = Validator::make($request->all(), [
                'mail_mailer' => 'required',
                'mail_host' => $isGodaddy ? 'nullable' : 'required',
                'mail_port' => $isGodaddy ? 'nullable' : 'required',
                'mail_username' => $isGodaddy ? 'nullable' : 'required',
                'mail_password' => $isGodaddy ? 'nullable' : 'required',
                'mail_encryption' => $isGodaddy ? 'nullable' : 'required',
                'mail_send_from' => 'required|email',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            try {
                if ($isGodaddy) {
                    $request->merge([
                        'mail_host' => 'localhost',
                        'mail_port' => '25',
                        'mail_username' => '',
                        'mail_password' => '',
                        'mail_encryption' => '',
                    ]);
                }

                // Get Request Data in Settings Array
                $settingsArray = $request->except('_token');

                // Check if any data has actually changed
                $hasChanges = false;
                foreach ($settingsArray as $key => $row) {
                    $existingSetting = Setting::where('type', $key)->first();
                    if (! $existingSetting || $existingSetting->data != $row) {
                        $hasChanges = true;
                        break;
                    }
                }

                // Create a settings data for database data insertions
                $settingsDataStore = [];
                foreach ($settingsArray as $key => $row) {
                    // If not empty then update or insert data according to type
                    Setting::updateOrInsert(
                        ['type' => $key],
                        ['data' => $row]
                    );
                }

                // Add Email Configuration Verification Record to false only if data changed
                if ($hasChanges) {
                    Setting::updateOrInsert(
                        ['type' => 'email_configuration_verification'],
                        ['data' => 0]
                    );
                }

                // Update ENV data variables
                $envUpdates = [
                    'MAIL_MAILER' => $request->mail_mailer,
                    'MAIL_HOST' => $request->mail_host,
                    'MAIL_PORT' => $request->mail_port,
                    'MAIL_USERNAME' => $request->mail_username,
                    'MAIL_PASSWORD' => $request->mail_password,
                    'MAIL_ENCRYPTION' => $request->mail_encryption,
                    'MAIL_FROM_ADDRESS' => $request->mail_send_from,
                ];
                updateEnv($envUpdates);
                ResponseService::successResponse(trans('Data Updated Successfully'));
            } catch (Exception $e) {
                ResponseService::errorResponse(trans('Something Went Wrong'));
            }
        }
    }

    public function verifyEmailConfig(Request $request)
    {
        if (! has_permissions('update', 'email_configurations')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $validator = Validator::make($request->all(), [
            'verify_email' => 'required|email',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $data = [
                'email' => $request->verify_email,
            ];

            if (! filter_var($request->verify_email, FILTER_VALIDATE_EMAIL)) {
                $response = [
                    'error' => true,
                    'message' => trans('Invalid Email'),
                ];

                return response()->json($response);
            }

            // Get Data of email type
            $propertyStatusTemplate = 'Your Email Configurations are working';

            $data = [
                'email_template' => $propertyStatusTemplate,
                'email' => $request->verify_email,
                'title' => 'Email Configuration Verification',
            ];
            HelperService::sendMail($data, true, true);

            Setting::where('type', 'email_configuration_verification')->update(['data' => 1]);
            DB::commit();

            ResponseService::successResponse(trans('Email Sent Successfully'));
        } catch (Exception $e) {
            DB::rollback();
            if (Str::contains($e->getMessage(), [
                'Failed',
                'Mail',
                'Mailer',
                'MailManager',
                'Connection could not be established',
            ])) {
                ResponseService::validationError('There is issue with mail configuration, kindly contact admin regarding this');
            }
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getCurrencySymbol(Request $request)
    {
        try {
            $countryCode = $request->country_code;
            $symbol = Currencies::getSymbol($countryCode);
            ResponseService::successResponse('', $symbol);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    public function watermarkSettingsIndex()
    {
        if (! has_permissions('read', 'watermark_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $watermarkConfig = HelperService::getWatermarkConfigDecoded();
        if ($watermarkConfig && ! empty($watermarkConfig)) {
            if (! file_exists(public_path('assets/images/logo/'.$watermarkConfig['watermark_image']))) {
                $watermarkConfig['watermark_image'] = null;
            }
        }

        // Set defaults
        $defaults = [
            'enabled' => 0,
            'watermark_image' => null,
            'opacity' => 25,
            'size' => 10,
            'style' => 'tile',
            'position' => 'center',
            'rotation' => 30,
        ];

        $watermarkSettings = $defaults;

        // Convert any existing negative rotation values to positive (0-360 range)
        if (isset($watermarkConfig['rotation']) && $watermarkConfig['rotation'] < 0) {
            $watermarkConfig['rotation'] = 360 + $watermarkConfig['rotation'];
        }

        if ($watermarkConfig) {
            $watermarkSettings = array_merge($defaults, $watermarkConfig);
        }

        return view('settings.watermark-settings', compact('watermarkSettings'));
    }

    public function watermarkSettingsStore(Request $request)
    {
        if (! has_permissions('update', 'watermark_settings')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        try {
            $validator = Validator::make($request->all(), [
                'watermark_enabled' => 'nullable|in:0,1',
                'watermark_image' => 'nullable|image|mimes:png,jpg,jpeg|max:3000',
                'opacity' => 'required_if:watermark_enabled,1|numeric|min:0|max:100',
                'size' => 'required_if:watermark_enabled,1|numeric|min:1|max:100',
                'style' => 'required_if:watermark_enabled,1|in:tile,single,center',
                'position' => 'required_if:style,single|in:top-left,top-right,bottom-left,bottom-right,center',
                'rotation' => 'nullable|numeric|min:0|max:360',
            ], [
                'watermark_image.mimes' => trans('Image must be JPG, JPEG or PNG'),
                'watermark_image.max' => trans('File size exceeds the :max limit. Please upload a smaller image.'),
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            // Get existing config to preserve values when watermark is disabled
            $watermarkConfig = Setting::where('type', 'watermark_config')->first();
            $existingConfig = [];
            if ($watermarkConfig && ! empty($watermarkConfig->data)) {
                $existingConfig = json_decode($watermarkConfig->data, true);
            }

            // Default values
            $defaults = [
                'opacity' => 25,
                'size' => 10,
                'style' => 'tile',
                'position' => 'center',
                'rotation' => 30,
            ];

            $isEnabled = ($request->watermark_enabled ?? 0) == 1;

            // If watermark is enabled, use form values; if disabled, preserve old values or use defaults
            $config = [
                'enabled' => $isEnabled ? 1 : 0,
                'opacity' => $isEnabled ? ($request->opacity ?? $existingConfig['opacity'] ?? $defaults['opacity']) : ($existingConfig['opacity'] ?? $defaults['opacity']),
                'size' => $isEnabled ? ($request->size ?? $existingConfig['size'] ?? $defaults['size']) : ($existingConfig['size'] ?? $defaults['size']),
                'style' => $isEnabled ? ($request->style ?? $existingConfig['style'] ?? $defaults['style']) : ($existingConfig['style'] ?? $defaults['style']),
                'position' => $isEnabled ? ($request->position ?? $existingConfig['position'] ?? $defaults['position']) : ($existingConfig['position'] ?? $defaults['position']),
                'rotation' => $isEnabled ? ($request->rotation ?? $existingConfig['rotation'] ?? $defaults['rotation']) : ($existingConfig['rotation'] ?? $defaults['rotation']),
            ];

            // Handle watermark image upload
            $destinationPath = public_path('assets/images/logo');
            if ($request->hasFile('watermark_image') && $request->file('watermark_image')->isValid()) {
                $filename = 'watermark.'.$request->file('watermark_image')->getClientOriginalExtension();

                // Get existing watermark image path
                $existingWatermarkImage = $existingConfig['watermark_image'] ?? null;
                $databaseData = ! empty($existingWatermarkImage) ? $existingWatermarkImage : null;

                $config['watermark_image'] = handleFileUpload($request, 'watermark_image', $destinationPath, $filename, $databaseData);
            } else {
                // Keep existing watermark image if not uploading new one
                $config['watermark_image'] = $existingConfig['watermark_image'] ?? null;
            }

            Setting::updateOrCreate(
                ['type' => 'watermark_config'],
                ['data' => json_encode($config)]
            );

            Cache::forget('watermark_config');

            ResponseService::successResponse(trans('Watermark Settings Updated Successfully'));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, trans('Something Went Wrong'));
        }
    }

    // Email Templates Index
    public function emailTemplatesIndex()
    {
        if (! has_permissions('read', 'email_templates')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        return view('mail-templates.templates-settings.index');
    }

    public function modifyMailTemplateIndex($type)
    {
        if (! has_permissions('read', 'email_templates')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $types = ['verify_mail', 'reset_password', 'welcome_mail', 'property_status', 'project_status', 'property_ads_status', 'user_status', 'agent_verification_status', 'user_verification_status', 'new_property_in_category_listing', 'subscription_expiring_soon', 'new_appointment_request', 'appointment_status', 'appointment_cancelled', 'appointment_meeting_type_change'];
        if (! in_array($type, $types)) {
            ResponseService::errorRedirectResponse(route('email-templates.index'), 'Type is invalid');
        }

        $data = HelperService::getEmailTemplatesTypes($type);

        $templateMailData = Setting::where('type', $data['type'])->first();
        $templateMail = ['template' => $templateMailData];
        $data = array_merge($templateMail, $data);

        return view('mail-templates.templates-settings.update-template', compact('data'));
    }

    public function emailTemplatesList()
    {
        if (! has_permissions('read', 'email_templates')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $data = HelperService::getEmailTemplatesTypes();
        $total = count($data);

        // $data->orderBy($sort, $order)->skip($offset)->take($limit);
        // $res = $data->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $no = 1;
        foreach ($data as $row) {
            $operate = '';
            if (has_permissions('update', 'email_templates')) {
                $operate .= BootstrapTableService::editButton(route('modify-mail-templates.index', $row['type']));
            }

            $tempRow = $row;
            $tempRow['no'] = $no;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $no++;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    public function emailTemplatesStore(Request $request)
    {
        if (! has_permissions('update', 'email_templates')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'data' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            Setting::updateOrCreate(
                ['type' => $request->type],
                ['data' => $request->data],
            );
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, "Issue in email template storing with type :- $request->type");
        }
    }
}
