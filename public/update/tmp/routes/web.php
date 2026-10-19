<?php

use App\Http\Controllers\AdBannerController;
use App\Http\Controllers\AdminAppointmentController;
use App\Http\Controllers\AdminAppointmentReportController;
use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\AgentVerificationFormController;
use App\Http\Controllers\AgentVerificationFormSectionController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CityImagesController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\CustomPageController;
use App\Http\Controllers\DeepLinkController;
use App\Http\Controllers\DemoDataController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GeminiAIController;
use App\Http\Controllers\GeminiSettingsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HomepageSectionController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OutdoorFacilityController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PackageFeatureController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\PayAsYouGoController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PropertController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\PropertysInquiryController;
use App\Http\Controllers\ReportReasonController;
use App\Http\Controllers\SeoSettingsController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\SocialMediaLinkController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerifyCustomerFormController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\BulkImportGalleryController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('customer-privacy-policy', [SettingController::class, 'show_privacy_policy'])->name('customer-privacy-policy');

Route::get('customer-terms-conditions', [SettingController::class, 'show_terms_conditions'])->name('customer-terms-conditions');

Auth::routes();

Route::get('privacypolicy', [HomeController::class, 'privacy_policy']);
Route::post('/webhook/razorpay', [WebhookController::class, 'razorpay'])->middleware('api.localization');
Route::post('/webhook/paystack', [WebhookController::class, 'paystack'])->middleware('api.localization');
Route::post('/webhook/paypal', [WebhookController::class, 'paypal'])->middleware('api.localization');
Route::post('/webhook/stripe', [WebhookController::class, 'stripe'])->middleware('api.localization');
Route::post('/webhook/flutterwave', [WebhookController::class, 'flutterwave'])->middleware('api.localization')->name('webhook.flutterwave');
Route::post('/webhook/cashfree', [WebhookController::class, 'cashfree'])->middleware('api.localization')->name('webhook.cashfree');
Route::post('/webhook/phonepe', [WebhookController::class, 'phonepe'])->middleware('api.localization')->name('webhook.phonepe');
Route::post('/webhook/midtrans', [WebhookController::class, 'midtrans'])->middleware('api.localization')->name('webhook.midtrans');

Route::get('payment/success', [PaymentController::class, 'paymentSuccess'])->name('payment.success');
Route::get('payment/success/web', [PaymentController::class, 'paymentSuccessWeb'])->name('payment.success.web');
Route::get('payment/gateway/{gateway}/status', [PaymentController::class, 'handleGatewayStatus'])->name('payment.gateway.status');
Route::get('payment/cancel', [PaymentController::class, 'paymentCancel'])->name('payment.cancel');
Route::get('payment/cancel/web', [PaymentController::class, 'paymentCancelWeb'])->name('payment.cancel.web');

// route for payments/responses/paystack.blade.php
// Route::view('payments.responses.paystack')->name('payment.paystack-response');
Route::get('/payment/responses/paystack', static function () {
    return view('payments.responses.paystack');
})->name('payment.paystack.response');

Route::group(['prefix' => 'install', 'middleware' => ['web', 'installer']], static function () {
    Route::get('purchase-code', [InstallerController::class, 'purchaseCodeIndex'])->name('install.purchase-code.index');
    Route::post('purchase-code', [InstallerController::class, 'checkPurchaseCode'])->name('install.purchase-code.post');
    Route::post('keys', [InstallerController::class, 'setKeys']);
});

// Redirect "property-details" links to app for mobile devices
Route::get('property-details/{slug}', [DeepLinkController::class, 'handle']);

Route::middleware(['language'])->group(function () {
    // Route::middleware(['guest'])->group(function () {
    Route::get('/', function () {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    });
    // });

    //  Route::get('demo', [DemoDataController::class, 'index'])->name('demo');
    //  Route::post('seed-demo-data', [DemoDataController::class, 'seed'])->name('seed-demo-data');
    Route::middleware(['auth', 'checkLogin'])->group(function () {
        Route::get('render_svg', [HomeController::class, 'render_svg'])->name('render_svg');
        Route::get('dashboard', [HomeController::class, 'blank_dashboard'])->name('dashboard');
        Route::get('/home', [HomeController::class, 'index'])->name('home');
        Route::get('about-us', [SettingController::class, 'index']);
        Route::get('privacy-policy', [SettingController::class, 'index']);
        Route::get('terms-conditions', [SettingController::class, 'index']);
        Route::get('system-settings', [SettingController::class, 'systemSettingsIndex'])->name('system-settings.index');
        Route::get('payment-gateway-settings', [SettingController::class, 'paymentGatewaySettingsIndex'])->name('payment-gateway-settings.index');
        Route::get('firebase_settings', [SettingController::class, 'index']);
        Route::get('app-settings', [SettingController::class, 'appSettingsIndex'])->name('app-settings.index');
        Route::get('web-settings', [SettingController::class, 'webSettingsIndex'])->name('web-settings.index');
        Route::get('system-version', [SettingController::class, 'index']);
        Route::post('firebase-settings', [SettingController::class, 'firebase_settings']);
        Route::post('app-settings', [SettingController::class, 'app_settings']);

        // Gemini AI Settings
        Route::get('gemini-settings', [GeminiSettingsController::class, 'index'])->name('gemini-settings.index');
        Route::post('gemini-settings', [GeminiSettingsController::class, 'update'])->name('gemini-settings.update');
        Route::post('gemini-settings/clear-cache', [GeminiSettingsController::class, 'clearCache'])->name('gemini-settings.clear-cache');

        // Gemini AI - Admin Panel Routes
        Route::post('gemini/generate-description', [GeminiAIController::class, 'generateDescription'])->name('gemini.generate-description');
        Route::post('gemini/generate-meta', [GeminiAIController::class, 'generateMetaDetails'])->name('gemini.generate-meta');
        Route::get('system-version', [SettingController::class, 'system_version']);
        Route::post('web-settings', [SettingController::class, 'web_settings']);
        Route::get('notification-settings', [SettingController::class, 'notificationSettingIndex'])->name('notification-setting-index');
        Route::post('notification-settings', [SettingController::class, 'notificationSettingStore'])->name('notification-setting-store');

        // Watermark Settings
        Route::get('watermark-settings', [SettingController::class, 'watermarkSettingsIndex'])->name('watermark-settings-index');
        Route::post('watermark-settings', [SettingController::class, 'watermarkSettingsStore'])->name('watermark-settings-store');

        /** Email Settings */
        // Configuration
        Route::get('email-configurations', [SettingController::class, 'emailConfigurationsIndex'])->name('email-configurations-index');
        Route::post('email-configurations', [SettingController::class, 'emailConfigurationsStore'])->name('email-configurations-store');

        // Templates
        Route::get('email-templates', [SettingController::class, 'emailTemplatesIndex'])->name('email-templates.index');
        Route::get('modify-mail-templates/{type}', [SettingController::class, 'modifyMailTemplateIndex'])->name('modify-mail-templates.index');

        Route::get('email-templates-list', [SettingController::class, 'emailTemplatesList'])->name('email-templates.list');
        Route::post('email-templates', [SettingController::class, 'emailTemplatesStore'])->name('email-templates.store');

        // Verify
        Route::post('verify-email-config', [SettingController::class, 'verifyEmailConfig'])->name('verify-email-config');
        /** End Email Settings */

        /** Admin Appointment Settings */
        Route::prefix('admin/appointment')->group(function () {
            // Index
            Route::get('/', [AdminAppointmentController::class, 'index'])->name('admin.appointment.index');

            // Preferences
            Route::get('preferences', [AdminAppointmentController::class, 'preferencesIndex'])->name('admin.appointment.preferences.index');
            Route::post('preferences', [AdminAppointmentController::class, 'storePreferences'])->name('admin.appointment.preferences.store');

            // Time Schedule
            Route::get('time-schedule', [AdminAppointmentController::class, 'timeScheduleIndex'])->name('admin.appointment.time-schedule.index');
            Route::post('time-schedule', [AdminAppointmentController::class, 'storeTimeSchedule'])->name('admin.appointment.time-schedule.store');
            Route::delete('time-schedule/{id}/remove', [AdminAppointmentController::class, 'removeTimeSchedule'])->name('admin.appointment.time-schedule.remove');
            Route::post('time-schedule/toggle-day', [AdminAppointmentController::class, 'toggleDayActive'])->name('admin.appointment.time-schedule.toggle-day');

            // Extra Time Slots
            Route::get('extra-time-slots', [AdminAppointmentController::class, 'extraTimeSlotsIndex'])->name('admin.appointment.extra-time-slots.index');
            Route::get('extra-time-slots/list', [AdminAppointmentController::class, 'getExtraTimeSlotsList'])->name('admin.appointment.extra-time-slots.list');
            Route::post('extra-time-slots', [AdminAppointmentController::class, 'storeExtraTimeSlot'])->name('admin.appointment.extra-time-slots.store');
            Route::delete('extra-time-slots/{id}/delete', [AdminAppointmentController::class, 'deleteExtraTimeSlot'])->name('admin.appointment.extra-time-slots.delete');

            // Unavailability
            Route::get('unavailability', [AdminAppointmentController::class, 'unavailabilityIndex'])->name('admin.appointment.unavailability.index');
            Route::post('unavailability', [AdminAppointmentController::class, 'storeUnavailability'])->name('admin.appointment.unavailability.store');
            Route::get('unavailability/{id}/delete', [AdminAppointmentController::class, 'deleteUnavailability'])->name('admin.appointment.unavailability.delete');
        });
        /** End Admin Appointment Settings */

        /** Appointment Management */
        Route::prefix('appointment-management')->group(function () {
            Route::get('/', [AdminAppointmentController::class, 'appointmentManagementIndex'])->name('appointment-management.index');
            Route::get('/list', [AdminAppointmentController::class, 'getAppointmentsList'])->name('appointment-management.list');
            Route::post('/update-status', [AdminAppointmentController::class, 'appointmentManagementUpdateStatus'])->name('appointment-management.update-status');
            Route::post('/available-slots', [AdminAppointmentController::class, 'getAvailableSlots'])->name('appointment-management.available-slots');
            Route::delete('/{id}', [AdminAppointmentController::class, 'appointmentManagementDestroy'])->name('appointment-management.destroy');
        });
        /** End Appointment Management */

        /** Appointment Reports Management */
        Route::prefix('admin/appointment/reports')->group(function () {
            Route::get('/', [AdminAppointmentReportController::class, 'index'])->name('admin.appointment.reports.index');
            Route::get('/list', [AdminAppointmentReportController::class, 'getReportsList'])->name('admin.appointment.reports.list');
            Route::post('/update-status', [AdminAppointmentReportController::class, 'updateReportStatus'])->name('admin.appointment.reports.update-status');
            Route::post('/block-user', [AdminAppointmentReportController::class, 'blockUser'])->name('admin.appointment.reports.block-user');

            // Blocked Users Management
            Route::get('/blocked-users', [AdminAppointmentReportController::class, 'blockedUsersIndex'])->name('admin.appointment.reports.blocked-users');
            Route::get('/blocked-users/list', [AdminAppointmentReportController::class, 'getBlockedUsersList'])->name('admin.appointment.reports.blocked-users.list');
            Route::post('/unblock-user', [AdminAppointmentReportController::class, 'unblockUser'])->name('admin.appointment.reports.unblock-user');
        });
        /** End Appointment Reports Management */
        Route::post('system-version-setting', [SettingController::class, 'system_version_setting']);

        // / START :: HOME ROUTE
        Route::get('change-password', [HomeController::class, 'change_password'])->name('changepassword');
        Route::post('check-password', [HomeController::class, 'check_password'])->name('checkpassword');
        Route::post('store-password', [HomeController::class, 'store_password'])->name('changepassword.store');
        Route::get('changeprofile', [HomeController::class, 'changeprofile'])->name('changeprofile');
        Route::post('updateprofile', [HomeController::class, 'update_profile'])->name('updateprofile');
        Route::post('firebase_messaging_settings', [HomeController::class, 'firebase_messaging_settings'])->name('firebase_messaging_settings');

        // / END :: HOME ROUTE

        // / START :: SETTINGS ROUTE

        Route::post('settings', [SettingController::class, 'settings']);
        Route::post('store-settings', [SettingController::class, 'system_settings'])->name('store-settings');
        Route::post('store-payment-gateway-settings', [SettingController::class, 'payment_gateway_settings'])->name('store-payment-gateway-settings');
        // / END :: SETTINGS ROUTE

        // / START :: LANGUAGES ROUTE

        Route::get('language_list', [LanguageController::class, 'show']);
        Route::get('set-language/{lang}', [LanguageController::class, 'set_language']);
        Route::get('download-panel-file', [LanguageController::class, 'downloadPanelFile'])->name('download-panel-file');
        Route::get('download-app-file', [LanguageController::class, 'downloadAppFile'])->name('download-app-file');
        Route::get('download-web-file', [LanguageController::class, 'downloadWebFile'])->name('download-web-file');
        Route::get('language/{id}/translations', [LanguageController::class, 'editTranslations'])->name('language.translations.edit');
        Route::post('language/{id}/translations', [LanguageController::class, 'saveTranslations'])->name('language.translations.save');
        Route::post('language/{id}/translations/chunk', [LanguageController::class, 'saveTranslationsChunk'])->name('language.translations.save-chunk');
        Route::post('update-language-status', [LanguageController::class, 'updateStatus'])->name('update-language-status');
        Route::resource('language', LanguageController::class);
        Route::post('language_update', [LanguageController::class, 'update'])->name('language_update');
        Route::get('language-destory/{id}', [LanguageController::class, 'destroy'])->name('language.destroy');

        // / END :: LANGUAGES ROUTE

        // / START :: PAYMENT ROUTE

        Route::get('payment-list', [PaymentController::class, 'paymentList'])->name('payment.list');
        Route::get('payment', [PaymentController::class, 'index'])->name('payment.index');
        Route::post('payment-status', [PaymentController::class, 'updateStatus'])->name('payment.status');
        Route::get('payment-receipt/{id}/view', [PaymentController::class, 'viewReceipt'])->name('payment.receipt.view');
        // / END :: PAYMENT ROUTE

        // / START :: USER ROUTE

        Route::resource('users', UserController::class);
        Route::post('users-update', [UserController::class, 'update']);
        Route::post('users-reset-password', [UserController::class, 'resetpassword']);
        Route::get('userList', [UserController::class, 'userList']);
        Route::get('get_users_inquiries', [UserController::class, 'users_inquiries']);
        Route::get('users_inquiries', [UserController::class, function () {
            if (! has_permissions('read', 'users_inquiries')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }

            return view('users.users_inquiries');
        }]);
        Route::get('destroy_contact_request/{id}', [UserController::class, 'destroy_contact_request'])->name('destroy_contact_request');

        // / END :: PAYMENT ROUTE

        // / START :: PAYMENT ROUTE

        Route::resource('customer', CustomersController::class);
        Route::get('customerList', [CustomersController::class, 'customerList']);
        Route::post('customerstatus', [CustomersController::class, 'update'])->name('customer.customerstatus');
        Route::post('customer/auto-approve', [CustomersController::class, 'toggleAutoApprove'])->name('customer.auto-approve');
        Route::post('customer/user-auto-approve', [CustomersController::class, 'toggleUserAutoApprove'])->name('customer.user-auto-approve');
        Route::post('customer/change-password', [CustomersController::class, 'changePassword'])->name('customer.change-password');
        // / END :: CUSTOMER ROUTE

        // / START :: SLIDER ROUTE

        Route::resource('slider', SliderController::class);
        // Route::post('slider-order', [SliderController::class, 'update'])->name('slider.slider-order');
        Route::get('slider-destroy/{id}', [SliderController::class, 'destroy'])->name('slider.destroy');
        Route::get('sliderList', [SliderController::class, 'sliderList']);
        // / END :: SLIDER ROUTE

        // / START :: ARTICLE ROUTE

        Route::resource('article', ArticleController::class);
        Route::get('article_list', [ArticleController::class, 'show'])->name('article_list');
        Route::get('add_article', [ArticleController::class, 'create'])->name('add_article');
        Route::delete('article-destroy/{id}', [ArticleController::class, 'destroy'])->name('article.destroy');
        Route::post('article/generate-slug', [ArticleController::class, 'generateAndCheckSlug'])->name('article.generate-slug');
        // / END :: ARTICLE ROUTE

        // / START :: AD BANNERS ROUTE
        Route::post('ad-banners/update-status', [AdBannerController::class, 'updateStatus'])->name('ad-banners.update-status');
        Route::get('properties/by-category', [AdBannerController::class, 'getPropertiesByCategory'])->name('properties.by-category');
        Route::post('ad-banners/update/{id}', [AdBannerController::class, 'update'])->name('ad-banners.update');
        Route::resource('ad-banners', AdBannerController::class)->except(['update']);
        // / END :: AD BANNERS ROUTE

        // / START :: ADVERTISEMENT ROUTE

        Route::resource('featured_properties', AdvertisementController::class);
        Route::get('featured_properties_list', [AdvertisementController::class, 'show']);
        Route::post('featured_properties_status', [AdvertisementController::class, 'updateStatus'])->name('featured_properties.update-advertisement-status');
        Route::post('adv-status-update', [AdvertisementController::class, 'update'])->name('adv-status-update');
        // / END :: ADVERTISEMENT ROUTE

        // / START :: PACKAGE ROUTE
        // Agent Specific
        Route::post('agent-package-features/status-update', [PackageFeatureController::class, 'updateStatus'])->name('agent-package-features.status-update');
        Route::get('agent-package-features/translated-names/{id}', [PackageFeatureController::class, 'translatedNames'])->name('agent-package-features.translated-names');
        Route::post('agent-package-features/update-translated-names', [PackageFeatureController::class, 'updateTranslatedNames'])->name('agent-package-features.update-translated-names');
        Route::resource('agent-package-features', PackageFeatureController::class)->parameters(['agent-package-features' => 'package_feature']);

        Route::post('agent-package-status', [PackageController::class, 'updatestatus'])->name('agent-packages.updatestatus');
        Route::get('agent-user-packages', [PackageController::class, 'userPackages'])->name('agent-user-packages.index');
        Route::get('agent-user-package-list', [PackageController::class, 'getUserPackageList'])->name('agent-user-packages.list');
        Route::get('assign-agent-package', [PackageController::class, 'assignPackageToUserIndex'])->name('assign-agent-package.index');
        Route::post('assign-agent-package', [PackageController::class, 'assignPackageToUser'])->name('assign-agent-package.store');
        Route::resource('agent-packages', PackageController::class)->parameters(['agent-packages' => 'package']);

        // User Specific
        Route::post('package-features/status-update', [PackageFeatureController::class, 'updateStatus'])->name('package-features.status-update');
        Route::get('package-features/translated-names/{id}', [PackageFeatureController::class, 'translatedNames'])->name('package-features.translated-names');
        Route::post('package-features/update-translated-names', [PackageFeatureController::class, 'updateTranslatedNames'])->name('package-features.update-translated-names');
        Route::resource('package-features', PackageFeatureController::class);

        Route::post('package-status', [PackageController::class, 'updatestatus'])->name('package.updatestatus');
        Route::get('user-packages', [PackageController::class, 'userPackages'])->name('user-packages.index');
        Route::get('user-package-list', [PackageController::class, 'getUserPackageList'])->name('user-packages.list');
        Route::get('assign-package', [PackageController::class, 'assignPackageToUserIndex'])->name('assign-package.index');
        Route::post('assign-package', [PackageController::class, 'assignPackageToUser'])->name('assign-package.store');
        // Select2 AJAX endpoints
        Route::get('select2/packages', [PackageController::class, 'selectPackages'])->name('select2.packages');
        Route::get('select2/customers', [PackageController::class, 'selectCustomers'])->name('select2.customers');
        Route::resource('package', PackageController::class);

        // / START :: PAY AS YOU GO ROUTE
        Route::get('pay-as-you-go/show', [PayAsYouGoController::class, 'show'])->name('pay-as-you-go.show');
        Route::post('pay-as-you-go/status', [PayAsYouGoController::class, 'updateStatus'])->name('pay-as-you-go.updatestatus');
        Route::post('pay-as-you-go/{id}', [PayAsYouGoController::class, 'update'])->name('pay-as-you-go.update-post');
        Route::resource('pay-as-you-go', PayAsYouGoController::class)->only(['index', 'update']);
        // / END :: PAY AS YOU GO ROUTE

        // / END :: PACKAGE ROUTE

        // / START :: CATEGORY ROUTE
        Route::resource('categories', CategoryController::class);
        Route::get('categoriesList', [CategoryController::class, 'categoryList']);
        Route::post('categories-update', [CategoryController::class, 'update']);
        Route::post('categorystatus', [CategoryController::class, 'updateCategory'])->name('categorystatus');
        Route::post('category/generate-slug', [CategoryController::class, 'generateAndCheckSlug'])->name('category.generate-slug');
        Route::get('/reorder-facilities/{id}', [CategoryController::class, 'reorderFacilities'])->name('categories.reorder-facilities');
        Route::post('/save-facility-order', [CategoryController::class, 'saveFacilityOrder'])->name('categories.save-facility-order');
        // / END :: CATEGORYW ROUTE

        // / START :: PARAMETER FACILITY ROUTE

        Route::resource('parameters', ParameterController::class);
        Route::get('parameter-list', [ParameterController::class, 'show']);
        Route::post('parameter-update', [ParameterController::class, 'update']);
        // / END :: PARAMETER FACILITY ROUTE

        // / START :: OUTDOOR FACILITY ROUTE
        Route::resource('outdoor_facilities', OutdoorFacilityController::class);
        Route::get('facility-list', [OutdoorFacilityController::class, 'show']);
        Route::post('facility-update', [OutdoorFacilityController::class, 'update']);
        Route::get('facility-delete/{id}', [OutdoorFacilityController::class, 'destroy'])->name('outdoor_facilities.destroy');
        // / END :: OUTDOOR FACILITY ROUTE

        // / START :: PROPERTY ROUTE

        Route::prefix('property')->group(function () {
            Route::post('generate-slug', [PropertController::class, 'generateAndCheckSlug'])->name('property.generate-slug');
            Route::delete('remove-threeD-image/{id}', [PropertController::class, 'removeThreeDImage'])->name('property.remove-threeD-image');
            Route::post('property-documents', [PropertController::class, 'removeDocument'])->name('property.remove-documents');
        });

        Route::resource('property', PropertController::class);
        Route::get('getPropertyList', [PropertController::class, 'getPropertyList']);
        Route::post('updatepropertystatus', [PropertController::class, 'updateStatus'])->name('updatepropertystatus');
        Route::post('property-gallery', [PropertController::class, 'removeGalleryImage'])->name('property.removeGalleryImage');
        Route::get('get-state-by-country', [PropertController::class, 'getStatesByCountry'])->name('property.getStatesByCountry');
        Route::get('property-destroy/{id}', [PropertController::class, 'destroy'])->name('property.destroy');
        Route::get('getFeaturedPropertyList', [PropertController::class, 'getFeaturedPropertyList']);
        Route::post('updateaccessability', [PropertController::class, 'updateaccessability'])->name('updateaccessability');
        Route::post('update-property-request-status', [PropertController::class, 'updateRequestStatus'])->name('update-property-request-status');

        Route::get('updateFCMID', [UserController::class, 'updateFCMID']);
        // / END :: PROPERTY ROUTE

        // / START :: PROPERTY INQUIRY
        Route::resource('property-inquiry', PropertysInquiryController::class);
        Route::get('getPropertyInquiryList', [PropertysInquiryController::class, 'getPropertyInquiryList']);
        Route::post('property-inquiry-status', [PropertysInquiryController::class, 'updateStatus'])->name('property-inquiry.updateStatus');
        // / ENND :: PROPERTY INQUIRY

        // / START :: REPORTREASON
        Route::resource('report-reasons', ReportReasonController::class);
        Route::get('report-reasons-list', [ReportReasonController::class, 'show']);
        Route::post('report-reasons-update', [ReportReasonController::class, 'update']);
        Route::get('report-reasons-destroy/{id}', [ReportReasonController::class, 'destroy'])->name('reasons.destroy');
        Route::get('users_reports', [ReportReasonController::class, 'users_reports']);
        Route::get('user_reports_list', [ReportReasonController::class, 'user_reports_list']);
        // / END :: REPORTREASON

        // / START :: COUNTRY
        Route::get('country', [CountryController::class, 'index'])->name('country.index');
        Route::post('country', [CountryController::class, 'store'])->name('country.store');
        Route::get('country-list', [CountryController::class, 'show']);
        Route::post('country-update', [CountryController::class, 'update'])->name('country.update');
        Route::post('country-status-update', [CountryController::class, 'statusUpdate'])->name('country.status-update');
        Route::get('country-destroy/{id}', [CountryController::class, 'destroy'])->name('country.destroy');
        // / END :: COUNTRY

        // / START :: CURRENCY
        Route::get('currency', [CurrencyController::class, 'index'])->name('currency.index');
        Route::post('currency', [CurrencyController::class, 'store'])->name('currency.store');
        Route::get('currency-list', [CurrencyController::class, 'show']);
        Route::post('currency-update', [CurrencyController::class, 'update'])->name('currency.update');
        Route::post('currency-status-update', [CurrencyController::class, 'statusUpdate'])->name('currency.status-update');
        Route::get('currency-destroy/{id}', [CurrencyController::class, 'destroy'])->name('currency.destroy');
        // / END :: CURRENCY

        Route::resource('property-inquiry', PropertysInquiryController::class);

        // / START :: CHAT ROUTE

        Route::get('get-chat-list', [ChatController::class, 'getChats'])->name('get-chat-list');
        Route::post('store_chat', [ChatController::class, 'store']);
        Route::get('getAllMessage', [ChatController::class, 'getAllMessage']);
        Route::post('block-user/{c_id}', [ChatController::class, 'blockUser'])->name('block-user');
        Route::post('unblock-user/{c_id}', [ChatController::class, 'unBlockUser'])->name('unblock-user');
        // / END :: CHAT ROUTE

        // / START :: NOTIFICATION
        Route::resource('notification', NotificationController::class);
        Route::get('notificationList', [NotificationController::class, 'notificationList']);
        Route::get('notification-delete', [NotificationController::class, 'destroy']);
        Route::post('notification-multiple-delete', [NotificationController::class, 'multiple_delete']);
        // / END :: NOTIFICATION

        // / START :: BULK IMPORT
        Route::prefix('bulk-import')->name('bulk-import.')->group(function () {
            // Pages
            Route::get('property', [BulkImportController::class, 'propertyIndex'])->name('property');
            Route::get('project', [BulkImportController::class, 'projectIndex'])->name('project');

            // Example CSV downloads
            Route::get('download/property-csv', [BulkImportController::class, 'downloadPropertyCsv'])->name('download.property');
            Route::get('download/project-csv', [BulkImportController::class, 'downloadProjectCsv'])->name('download.project');
            Route::get('download/categories-csv', [BulkImportController::class, 'downloadCategoriesCsv'])->name('download.categories');

            // CSV processing
            Route::post('process/property', [BulkImportController::class, 'processPropertyCsv'])->name('process.property');
            Route::post('process/project', [BulkImportController::class, 'processProjectCsv'])->name('process.project');

            // Customer search (Select2) + info for "upload for customer" mode
            Route::get('customer-search', [BulkImportController::class, 'searchCustomers'])->name('customer.search');
            Route::get('customer-info', [BulkImportController::class, 'getCustomerInfo'])->name('customer.info');

            // Gallery (shared between property and project import)
            Route::get('gallery', [BulkImportGalleryController::class, 'index'])->name('gallery.index');
            Route::post('gallery/upload', [BulkImportGalleryController::class, 'upload'])->name('gallery.upload');
            Route::post('gallery/upload-video', [BulkImportGalleryController::class, 'uploadVideo'])->name('gallery.upload-video');
            Route::post('gallery/destroy', [BulkImportGalleryController::class, 'destroy'])->name('gallery.destroy');
        });
        // / END :: BULK IMPORT

        // / START :: PROJECT
        Route::post('project-generate-slug', [ProjectController::class, 'generateAndCheckSlug'])->name('project.generate-slug');
        Route::post('updateProjectStatus', [ProjectController::class, 'updateStatus'])->name('updateProjectStatus');
        Route::post('project-gallery', [ProjectController::class, 'removeGalleryImage'])->name('project.remove-gallary-images');
        Route::post('project-document', [ProjectController::class, 'removeDocument'])->name('project.remove-document');
        Route::delete('remove-project-floor/{id}', [ProjectController::class, 'removeFloorPlan'])->name('project.remove-floor-plan');
        Route::post('update-project-request-status', [ProjectController::class, 'updateRequestStatus'])->name('update-project-request-status');
        Route::resource('project', ProjectController::class);

        /** Stories */
        Route::get('story', [StoryController::class, 'index'])->name('story.index');
        Route::get('getStoryList', [StoryController::class, 'getStoryList']);
        Route::get('story/create', [StoryController::class, 'create'])->name('story.create');
        Route::get('story/entity-media', [StoryController::class, 'getEntityMedia'])->name('story.entity-media');
        Route::post('story', [StoryController::class, 'store'])->name('story.store');
        Route::get('story/{id}', [StoryController::class, 'destroy'])->name('story.destroy');
        // / END :: PROJECT

        /** Audit Logs */
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/list', [AuditLogController::class, 'list'])->name('audit-logs.list');

        // / START :: SEO SETTINGS
        Route::resource('seo_settings', SeoSettingsController::class);
        Route::get('seo-settings-destroy/{id}', [SeoSettingsController::class, 'destroy'])->name('seo_settings.destroy');
        // / END :: SEO SETTINGS

        // / START :: FAQs
        Route::post('faq/status-update', [FaqController::class, 'statusUpdate'])->name('faqs.status-update');
        Route::resource('faqs', FaqController::class);
        // / END :: FAQs

        // / START :: City Images
        Route::post('city-images/status-update', [CityImagesController::class, 'statusUpdate'])->name('city-images.status-update');
        Route::post('city-image-settings', [CityImagesController::class, 'cityImageSettings'])->name('city-image-settings');
        Route::resource('city-images', CityImagesController::class);
        // / END :: City Images

        // / START :: Homepage Sections
        Route::post('homepage-sections/update-toggles', [HomepageSectionController::class, 'updateToggles'])->name('homepage-sections.update-toggles');
        Route::post('homepage-sections/status-update', [HomepageSectionController::class, 'statusUpdate'])->name('homepage-sections.status-update');
        Route::post('homepage-sections/update-order', [HomepageSectionController::class, 'updateOrder'])->name('homepage-sections.update-order');
        Route::get('homepage-sections/change-order', [HomepageSectionController::class, 'changeOrder'])->name('homepage-sections.change-order');
        Route::get('homepage-sections/select2/properties', [HomepageSectionController::class, 'selectProperties'])->name('homepage-sections.select2.properties');
        Route::get('homepage-sections/select2/projects', [HomepageSectionController::class, 'selectProjects'])->name('homepage-sections.select2.projects');
        Route::resource('homepage-sections', HomepageSectionController::class);
        // / END :: Homepage Sections

        Route::get('calculator', function () {
            if (! has_permissions('read', 'calculator')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            }

            return view('Calculator.calculator');
        });

        // Flush only the Google Maps cache store (gplaces)
        Route::post('admin/cache/gmaps/clear', function () {
            Log::info('Google Maps cache cleared');
            Cache::store('gplaces')->clear();

            return true;
        })->name('cache.gmaps.clear');

        // Flush only the Open Street Maps cache store (osmmaps)
        Route::post('admin/cache/osm/clear', function () {
            Log::info('Open Street Maps cache cleared');
            Cache::store('osmmaps')->clear();

            return true;
        })->name('cache.osm.clear');

        // / Start :: User Verification Form
        Route::prefix('verify-customer')->group(function () {
            Route::get('/custom-form', [VerifyCustomerFormController::class, 'verifyCustomerFormIndex'])->name('verify-customer.form');
            Route::post('/save-custom-form', [VerifyCustomerFormController::class, 'verifyCustomerFormStore'])->name('verify-customer-form.store');
            Route::get('/list-custom-form', [VerifyCustomerFormController::class, 'verifyCustomerFormShow'])->name('verify-customer-form.show');
            Route::post('/update-custom-form', [VerifyCustomerFormController::class, 'verifyCustomerFormUpdate'])->name('verify-customer-form.update');
            Route::post('/status-custom-form', [VerifyCustomerFormController::class, 'verifyCustomerFormStatus'])->name('verify-customer-form.status');
            Route::delete('/delete-custom-form/{id}', [VerifyCustomerFormController::class, 'verifyCustomerFormDestroy'])->name('verify-customer-form.delete');
            Route::delete('/delete-custom-form-option/{id}', [VerifyCustomerFormController::class, 'verifyCustomerFormOptionDestroy'])->name('verify-customer-form.delete-option');
        });

        Route::prefix('agent-verification-form-sections')->group(function () {
            Route::get('/', [AgentVerificationFormSectionController::class, 'index'])->name('agent-verification-form-sections.index');
            Route::post('/store', [AgentVerificationFormSectionController::class, 'store'])->name('agent-verification-form-sections.store');
            Route::get('/list', [AgentVerificationFormSectionController::class, 'show'])->name('agent-verification-form-sections.show');
            Route::post('/update', [AgentVerificationFormSectionController::class, 'update'])->name('agent-verification-form-sections.update');
            Route::post('/status', [AgentVerificationFormSectionController::class, 'status'])->name('agent-verification-form-sections.status');
            Route::delete('/delete/{id}', [AgentVerificationFormSectionController::class, 'destroy'])->name('agent-verification-form-sections.destroy');
            Route::post('/update-sequence', [AgentVerificationFormSectionController::class, 'updateSequence'])->name('agent-verification-form-sections.update-sequence');
            Route::get('/reorder-fields/{id}', [AgentVerificationFormSectionController::class, 'reorderFields'])->name('agent-verification-form-sections.reorder-fields');
            Route::post('/save-field-order', [AgentVerificationFormSectionController::class, 'saveFieldOrder'])->name('agent-verification-form-sections.save-field-order');
        });

        Route::prefix('agent-verification-form-fields')->group(function () {
            Route::get('/', [AgentVerificationFormController::class, 'index'])->name('agent-verification-form-fields.index');
            Route::post('/store', [AgentVerificationFormController::class, 'store'])->name('agent-verification-form-fields.store');
            Route::get('/list', [AgentVerificationFormController::class, 'show'])->name('agent-verification-form-fields.show');
            Route::post('/update', [AgentVerificationFormController::class, 'update'])->name('agent-verification-form-fields.update');
            Route::post('/status', [AgentVerificationFormController::class, 'status'])->name('agent-verification-form-fields.status');
            Route::post('/required-status', [AgentVerificationFormController::class, 'requiredStatus'])->name('agent-verification-form-fields.required-status');
            Route::delete('/delete/{id}', [AgentVerificationFormController::class, 'destroy'])->name('agent-verification-form-fields.destroy');
            Route::post('/update-sequence', [AgentVerificationFormController::class, 'updateSequence'])->name('agent-verification-form-fields.update-sequence');
        });

        Route::prefix('social-media-links')->group(function () {
            Route::get('/', [SocialMediaLinkController::class, 'index'])->name('social-media-links.index');
            Route::post('/store', [SocialMediaLinkController::class, 'store'])->name('social-media-links.store');
            Route::get('/list', [SocialMediaLinkController::class, 'show'])->name('social-media-links.show');
            Route::post('/update', [SocialMediaLinkController::class, 'update'])->name('social-media-links.update');
            Route::post('/status', [SocialMediaLinkController::class, 'status'])->name('social-media-links.status');
            Route::delete('/delete/{id}', [SocialMediaLinkController::class, 'destroy'])->name('social-media-links.destroy');
        });

        Route::prefix('agent-verification')->group(function () {
            Route::get('/', [VerifyCustomerFormController::class, 'agentVerificationListIndex'])->name('agent-verification.index');
            Route::get('/list', [VerifyCustomerFormController::class, 'agentVerificationList'])->name('agent-verification.list');
            Route::get('/submitted-form/{id}', [VerifyCustomerFormController::class, 'getAgentSubmittedForm'])->name('agent-verification.show-form');
            Route::post('/update-verification-status', [VerifyCustomerFormController::class, 'updateVerificationStatus'])->name('agent-verification.change-status');
            Route::post('/auto-approve-settings', [VerifyCustomerFormController::class, 'agentAutoApproveSettings'])->name('agent-verification.auto-approve');
            Route::post('/verification-required-for-agent-settings', [VerifyCustomerFormController::class, 'verificationRequiredForAgentSettings'])->name('agent-verification.verification-required-for-agent');
        });

        Route::prefix('user-verification')->group(function () {
            Route::get('/', [VerifyCustomerFormController::class, 'userVerificationListIndex'])->name('user-verification.index');
            Route::get('/list', [VerifyCustomerFormController::class, 'userVerificationList'])->name('user-verification.list');
            Route::get('/submitted-form/{id}', [VerifyCustomerFormController::class, 'getUserSubmittedForm'])->name('user-verification.show-form');
            Route::post('/update-verification-status', [VerifyCustomerFormController::class, 'updateUserVerificationStatus'])->name('user-verification.change-status');
            Route::post('/auto-approve-settings', [VerifyCustomerFormController::class, 'userAutoApproveSettings'])->name('user-verification.auto-approve');
            Route::post('/verification-required-for-user-settings', [VerifyCustomerFormController::class, 'verificationRequiredForUserSettings'])->name('user-verification.verification-required-for-user');
        });

        // / START :: DEMO DATA MANAGEMENT
        Route::prefix('demo-data')->group(function () {
            Route::get('/', [DemoDataController::class, 'index'])->name('demo-data.index');
            Route::post('/seed', [DemoDataController::class, 'seedDemoData'])->name('demo-data.seed');
            Route::post('/clear', [DemoDataController::class, 'clearDemoData'])->name('demo-data.clear');
            Route::post('/reset', [DemoDataController::class, 'resetDemoData'])->name('demo-data.reset');
        });
        // / END :: DEMO DATA MANAGEMENT

        // / START :: CUSTOM PAGES ROUTE
        Route::resource('custom-page', CustomPageController::class);
        Route::post('update-custom-page-status', [CustomPageController::class, 'updateStatus'])->name('update-custom-page-status');
        Route::post('custom-page/generate-slug', [CustomPageController::class, 'generateAndCheckSlug'])->name('custom-page.generate-slug');
        // / END :: CUSTOM PAGES ROUTE

    });

    Route::get('get-currency-symbol', [SettingController::class, 'getCurrencySymbol'])->name('get-currency-symbol');
    // Reset Password
    Route::get('reset-password', [CustomersController::class, 'resetPasswordIndex']);
    Route::post('change-password', [CustomersController::class, 'resetPassword'])->name('customer.reset-password');

    // Public Custom Page Render
    Route::get('page/{slug}', [CustomPageController::class, 'renderPage'])->name('custom-page.render');
});
Route::get('deep-link', function () {
    return view('settings.deep-link');
});
// Local Language Values for JS
Route::get('/js/lang', static function () {
    //    https://medium.com/@serhii.matrunchyk/using-laravel-localization-with-javascript-and-vuejs-23064d0c210e
    header('Content-Type: text/javascript');
    $labels = Cache::remember('lang.js', 3600, static function () {
        $lang = Session::get('locale') ?? 'en';
        $files = resource_path('lang/'.$lang.'.json');

        return File::get($files);
    });
    echo 'window.trans = '.$labels;
    exit();
})->name('assets.lang');

// Add New Migration Route
Route::get('migrate', function () {
    Artisan::call('migrate');
    $output = Artisan::output();
    echo nl2br($output); // Convert newlines to <br> for better readability in HTML
});

Route::get('migrate-status', function () {
    Artisan::call('migrate:status');
    $output = Artisan::output();
    echo nl2br($output); // Convert newlines to <br> for better readability in HTML
});

// // Rollback last step Migration Route
// Route::get('/rollback', function () {
//     Artisan::call('migrate:rollback');

//     return redirect()->back();
// });

// // Storage Link
Route::get('/storage-link', function () {
    Artisan::call('storage:link');

    return redirect()->back();
});

// Clear Config
Route::get('/clear', function () {
    Artisan::call('optimize:clear');
    return redirect()->back();
});

Route::get('/add-url', function () {
    $envUpdates = [
        'APP_URL' => Request::root(),
    ];
    updateEnv($envUpdates);
})->name('add-url-in-env');

Route::get('/seed-demo-data', function () {
    Artisan::call('db:seed', ['--class' => 'DemoDataSeeder']);
    $output = Artisan::output();
    echo nl2br($output); // Convert newlines to <br> for better readability in HTML
});

Route::get('/run-scheduler', function () {
    if (Cache::has('scheduler_running')) {
        return response()->json(['status' => 'Already processed recently']);
    }

    Cache::put('scheduler_running', true, 60); // 1 minute lock

    Artisan::call('schedule:run', ['--quiet' => true]);

    return response()->json(['status' => 'Scheduler processed']);
});


