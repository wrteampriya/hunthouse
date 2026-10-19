<?php

use App\Http\Controllers\Api\AdvertisementApiController;
use App\Http\Controllers\Api\AgentApiController;
use App\Http\Controllers\Api\AppointmentApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\ContentApiController;
use App\Http\Controllers\Api\FavouriteApiController;
use App\Http\Controllers\Api\HomepageApiController;
use App\Http\Controllers\Api\MapApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\PackageApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\PersonalisationApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\PropertyApiController;
use App\Http\Controllers\Api\SettingsApiController;
use App\Http\Controllers\Api\VerificationApiController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\GeminiAIController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ActiveRoleMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*********************************************************************** */
Route::group(['middleware' => ['auth:sanctum']], function () {
    /*********************************************************************** */
    /** Property */
    Route::post('post_property', [PropertyApiController::class, 'post_property'])->name('post-property');
    Route::post('activate-listing', [PropertyApiController::class, 'activateListing']);
    Route::post('update_post_property', [PropertyApiController::class, 'update_post_property'])->name('update-post-property');
    Route::post('update_property_status', [PropertyApiController::class, 'update_property_status'])->name('update-property-status');
    Route::post('update_project_status', [ProjectApiController::class, 'update_project_status'])->name('update-project-status');
    Route::post('delete_property', [PropertyApiController::class, 'delete_property'])->name('delete-property');
    Route::post('interested_users', [PropertyApiController::class, 'interested_users'])->name('interested-users');
    Route::post('change-property-status', [PropertyApiController::class, 'changePropertyStatus'])->name('change-property-status');

    Route::get('get_property_inquiry', [PropertyApiController::class, 'get_property_inquiry'])->name('get-property-inquiry');
    Route::get('get-added-properties', [PropertyApiController::class, 'getAddedProperties'])->name('get-added-properties');

    Route::get('get_interested_users', [PropertyApiController::class, 'getInterestedUsers'])->name('get-interested-users');
    Route::post('remove_post_images', [PropertyApiController::class, 'remove_post_images'])->name('remove-post-images');
    Route::post('user_interested_property', [PropertyApiController::class, 'user_interested_property'])->name('user-interested-property');

    /*********************************************************************** */

    Route::post('before-logout', [AuthApiController::class, 'beforeLogout'])->name('before-logout');

    /** Users */
    Route::post('update_profile', [ProfileApiController::class, 'update_profile'])->name('update-profile');
    Route::post('delete_user', [ProfileApiController::class, 'delete_user'])->name('delete-user');
    Route::get('get-user-data', [ProfileApiController::class, 'getUserData'])->name('get-user-data');
    Route::post('update-language', [ProfileApiController::class, 'updateLanguage'])->name('update-language');

    /*********************************************************************** */

    /** Chat */
    Route::post('send_message', [ChatApiController::class, 'send_message'])->name('send-message');
    Route::post('delete_chat_message', [ChatApiController::class, 'delete_chat_message'])->name('delete-chat-message');
    Route::post('block-user', [ChatApiController::class, 'blockChatUser'])->name('block-user');
    Route::post('unblock-user', [ChatApiController::class, 'unBlockChatUser'])->name('unblock-user');
    Route::get('get_messages', [ChatApiController::class, 'get_messages'])->name('get-messages');
    Route::get('get_chats', [ChatApiController::class, 'get_chats'])->name('get-chats');
    /*********************************************************************** */

    /** Package */
    Route::post('assign_package', [PackageApiController::class, 'assign_package'])->name('assign-package');
    Route::get('check-package-limit', [PackageApiController::class, 'checkPackageLimit'])->name('check-package-limit');
    Route::delete('remove-all-packages', [PackageApiController::class, 'removeAllPackages'])->name('remove-all-packages');
    /*********************************************************************** */

    /** Payment */
    Route::get('get_payment_settings', [PaymentApiController::class, 'get_payment_settings'])->name('get-payment-settings');
    Route::get('get_payment_details', [PaymentApiController::class, 'getPaymentTransactionDetails'])->name('get-payment-details');
    Route::post('create-payment-intent', [PaymentApiController::class, 'createPaymentIntent'])->name('create-payment-intent');
    Route::post('payment-transaction-fail', [PaymentApiController::class, 'makePaymentTransactionFail'])->name('payment-transaction-fail');
    Route::get('get-payment-receipt', [PaymentApiController::class, 'getPaymentReceipt'])->name('get-payment-receipt');
    Route::post('initiate-bank-transfer', [PaymentApiController::class, 'initiateBankTransaction'])->name('initiate-bank-transfer');
    Route::post('upload-bank-receipt-file', [PaymentApiController::class, 'uploadBankReceiptFile'])->name('upload-bank-receipt-file');

    // Other's APIs
    Route::get('get_notification_list', [NotificationApiController::class, 'get_notification_list'])->name('get-notification-list');
    /*********************************************************************** */

    /** Personalised Interest */

    /*********************************************************************** */

    /** Extra */
    Route::post('store_advertisement', [AdvertisementApiController::class, 'store_advertisement'])->name('store-advertisement');
    Route::post('renew_listing', [AdvertisementApiController::class, 'renew_listing'])->name('renew-listing');
    /*********************************************************************** */

    /** Projects */
    Route::post('post_project', [ProjectApiController::class, 'post_project'])->name('post-project');
    Route::post('delete_project', [ProjectApiController::class, 'delete_project'])->name('delete-project');
    Route::get('get-added-projects', [ProjectApiController::class, 'getAddedProjects'])->name('get-added-projects');
    Route::post('change-project-status', [ProjectApiController::class, 'changeProjectStatus'])->name('change-project-status');
    /*********************************************************************** */

    /** Agent Specific Action APIs */
    Route::group(['middleware' => ['agent']], function () {
        /** Agent Profile Apis */
        Route::get('get-agent-profile', [AgentApiController::class, 'getAgentProfile'])->name('get-agent-profile');
        Route::post('update-agent-profile', [AgentApiController::class, 'updateAgentProfile'])->name('update-agent-profile');

        /** Agent Dashboard Apis */
        Route::group(['prefix' => 'agent-dashboard'], function () {
            Route::get('profile-completion', [AgentApiController::class, 'getAgentProfileCompletion'])->name('get-agent-profile-completion');
            Route::get('summery', [AgentApiController::class, 'getAgentDashboardSummeryData'])->name('get-agent-dashboard-summery-data');
            Route::get('listings', [AgentApiController::class, 'getAgentDashboardListingsData'])->name('get-agent-dashboard-listings-data');
            Route::get('recent-listing', [AgentApiController::class, 'getAgentDashboardRecentlyAddedListingsData'])->name('get-agent-dashboard-recently-added-listings-data');
            Route::get('active-packages', [AgentApiController::class, 'getAgentDashboardActivePackagesData'])->name('get-agent-dashboard-active-packages-data');
            Route::get('most-viewed-listing', [AgentApiController::class, 'getAgentDashboardMostViewedListingData'])->name('get-agent-dashboard-most-viewed-listing-data');
            Route::get('most-viewed-category', [AgentApiController::class, 'getAgentDashboardMostViewedCategoryData'])->name('get-agent-dashboard-most-viewed-category-data');
            Route::get('appointments', [AgentApiController::class, 'getAgentDashboardAppointmentData'])->name('get-agent-dashboard-appointment-data');
        });

        Route::group(['prefix' => 'appointment'], function () {
            Route::get('agent-appointments', [AppointmentApiController::class, 'getAgentAppointments'])->name('get-agent-appointments');
            Route::post('booking-preferences', [AppointmentApiController::class, 'storeBookingPreferences'])->name('store-booking-preferences');
            Route::get('booking-preferences', [AppointmentApiController::class, 'getAgentBookingPreferences'])->name('get-agent-booking-preferences');
            // Time Schedule Data
            Route::post('set-agent-time-schedule', [AppointmentApiController::class,    'setAgentTimeSchedule'])->name('set-agent-time-schedule');
            Route::get('agent-time-schedules', [AppointmentApiController::class,    'getAgentTimeSchedules'])->name('get-agent-time-schedules');
            // Unavailability Data
            Route::post('add-unavailability', [AppointmentApiController::class,     'addAgentUnavailability'])->name('add-agent-unavailability');
            Route::get('unavailability-data', [AppointmentApiController::class,     'getUnavailabilityData'])->name('get-unavailability-data');
            Route::delete('delete-unavailability', [AppointmentApiController::class,    'deleteUnavailabilityData'])->name('delete-unavailability-data');
            // Extra Time Slots (Bulk Operations Only)
            Route::get('extra-time-slots', [AppointmentApiController::class, 'getAgentExtraTimeSlots'])->name('get-agent-extra-time-slots');
            Route::post('manage-extra-time-slots', [AppointmentApiController::class,    'manageAgentExtraTimeSlots'])->name('manage-agent-extra-time-slots');
            Route::post('delete-extra-time-slots', [AppointmentApiController::class,    'deleteMultipleAgentExtraTimeSlots'])->name('delete-multiple-agent-extra-time-slots');
            // General
            // Update Meeting Type

        });
    });
    /*********************************************************************** */

    Route::group(['middleware' => ['user']], function () {

        Route::get('get_user_recommendation', [PropertyApiController::class, 'get_user_recommendation'])->name('get-user-recommendation');

        Route::get('get_user_verification_form', [VerificationApiController::class, 'getUserVerificationForm'])->name('get-user-verification-form');
        Route::get('get_user_verification_form_values', [VerificationApiController::class, 'getUserVerificationFormValues'])->name('get-user-verification-form-values');
        Route::post('apply_user_verification', [VerificationApiController::class, 'applyUserVerification'])->name('apply-user-verification');

        Route::get('mortgage-calculator', [ContentApiController::class, 'calculateMortgageCalculator'])->name('mortgage-calculator');

        Route::get('get_report_reasons', [ContentApiController::class, 'get_report_reasons'])->name('get-report-reasons');

        Route::post('add_reports', [PropertyApiController::class, 'add_reports'])->name('add-reports');

        Route::get('appointment/check-availability', [AppointmentApiController::class, 'checkAgentTimeAvailability'])->name('check-agent-time-availability');
        // Get Appointments
        Route::get('appointment/user-appointments', [AppointmentApiController::class, 'getUserAppointments'])->name('get-user-appointments');

        // Create Appointment Request
        Route::post('appointment/request', [AppointmentApiController::class, 'createAppointment'])->name('create-appointment');

        Route::get('personalised-fields', [PersonalisationApiController::class, 'getUserPersonalisedInterest'])->name('get-user-personalised-interest');
        Route::post('personalised-fields', [PersonalisationApiController::class, 'storeUserPersonalisedInterest'])->name('store-user-personalised-interest');
        Route::delete('personalised-fields', [PersonalisationApiController::class, 'deleteUserPersonalisedInterest'])->name('delete-user-personalised-interest');

        Route::get('get_favourite_property', [FavouriteApiController::class, 'get_favourite_property'])->name('get-favourite-property');
    });

    Route::post('apply-agent-verification', [VerificationApiController::class, 'applyAgentVerification'])->name('apply-agent-verification');
    Route::get('get-agent-verification-form', [VerificationApiController::class, 'getAgentVerificationForm'])->name('get-agent-verification-form');
    Route::get('get-agent-verification-form-fields', [VerificationApiController::class, 'getAgentVerificationFormFields'])->name('get-agent-verification-form-fields');
    Route::get('get-agent-verification-form-values', [VerificationApiController::class, 'getAgentVerificationFormValues'])->name('get-agent-verification-form-values');

    /** Confirmation needed */
    Route::post('set_property_inquiry', [ApiController::class, 'set_property_inquiry'])->name('set-property-inquiry');
    Route::post('add_favourite', [FavouriteApiController::class, 'add_favourite'])->name('add-favourite');
    Route::post('delete_favourite', [ApiController::class, 'delete_favourite'])->name('delete-favourite');
    Route::post('user_purchase_package', [PackageApiController::class, 'user_purchase_package'])->name('user-purchase-package');
    Route::post('delete_advertisement', [AdvertisementApiController::class, 'delete_advertisement'])->name('delete-advertisement');
    Route::post('delete_inquiry', [ApiController::class, 'delete_inquiry'])->name('delete-inquiry');
    Route::post('add_edit_user_interest', [ApiController::class, 'add_edit_user_interest'])->name('add-edit-user-interest');
    /*********************************************************************** */

    /*********************************************************************** */

    /** General Apis */
    Route::get('get-featured-data', [PackageApiController::class, 'getFeaturedData'])->name('get-featured-data');

    /** Gemini AI */
    Route::post('gemini/generate-description', [GeminiAIController::class, 'generateDescription'])->name('gemini-generate-description');
    Route::post('gemini/generate-meta', [GeminiAIController::class, 'generateMetaDetails'])->name('gemini-generate-meta');

    /** Agent Appointment Apis */
    Route::group(['prefix' => 'appointment'], function () {

        Route::get('monthly-time-slots', [AppointmentApiController::class, 'getMonthlyTimeSlots'])->name('get-monthly-time-slots');

        // Update Appointment Status
        Route::post('update-status', [AppointmentApiController::class, 'updateAppointmentStatus'])->name('update-appointment-status');
        // Report User
        Route::post('report-user', [AppointmentApiController::class, 'reportUser'])->name('report-user');
        Route::get('get-user-reports', [AppointmentApiController::class, 'getUserReports'])->name('get-user-reports');
        Route::post('update-meeting-type', [AppointmentApiController::class,    'updateAppointmentMeetingType'])->name('update-appointment-meeting-type');
    });
    /*********************************************************************** */
});

/** Property */
Route::get('get-properties-on-map', [PropertyApiController::class, 'getPropertiesOnMap'])->name('get-properties-on-map');
Route::get('compare-properties', [PropertyApiController::class, 'compareProperties'])->name('compare-properties');
Route::get('get_property', [PropertyApiController::class, 'get_property'])->name('get-property');

Route::get('get-all-similar-properties', [PropertyApiController::class, 'getAllSimilarProperties'])->name('get-all-similar-properties');
Route::get('property-advance-filter-data', [PropertyApiController::class, 'propertyAdvanceFilterData'])->name('property-advance-filter-data');
/*********************************************************************** */
Route::group(['middleware' => ['user']], function () {
    Route::get('agent-properties', [AgentApiController::class, 'getAgentProperties'])->name('agent-properties');
    Route::get('agent-list', [AgentApiController::class, 'getAgentList'])->name('agent-list');
});
/** Auth */
Route::post('user_signup', [AuthApiController::class, 'user_signup'])->name('user-signup');
Route::post('user-register', [AuthApiController::class, 'userRegister'])->name('user-register');
Route::get('check-number-password-exists', [AuthApiController::class, 'checkNumberPasswordExists'])->name('check-number-password-exists');
Route::post('update-number-password', [AuthApiController::class, 'updateNumberPassword'])->name('update-number-password');
Route::post('update-email-password', [AuthApiController::class, 'updateEmailPassword'])->name('update-email-password');
Route::get('forgot-password', [AuthApiController::class, 'forgotPassword'])->name('forgot-password');
Route::get('remove-account-temp', [AuthApiController::class, 'removeAccountTemp'])->name('remove-account-temp');
Route::get('get-otp', [AuthApiController::class, 'getOtp'])->name('get-otp');
Route::get('verify-otp', [AuthApiController::class, 'verifyOtp'])->name('verify-otp');
/*********************************************************************** */

/** Content */
Route::post('contact-us', [ContentApiController::class, 'contactUs'])->name('contact-us');
Route::get('get-slider', [ContentApiController::class, 'getSlider'])->name('get-slider');
Route::get('get_facilities', [ContentApiController::class, 'get_facilities'])->name('get-facilities');
Route::get('get_seo_settings', [ContentApiController::class, 'get_seo_settings'])->name('get-seo-settings');

Route::get('get_articles', [ContentApiController::class, 'get_articles'])->name('get-articles');
Route::get('get_custom_pages', [ContentApiController::class, 'get_custom_pages'])->name('get-custom-pages');

Route::get('faqs', [ContentApiController::class, 'getFaqData'])->name('get_faqs');
/*********************************************************************** */

/** Google Maps */
Route::get('get-map-places-list', [MapApiController::class, 'getMapPlacesListData'])->name('get-map-places-list');
Route::get('get-map-place-details', [MapApiController::class, 'getMapPlaceDetailsData'])->name('get-map-place-details');
Route::get('get-cities-data', [MapApiController::class, 'getCitiesData'])->name('get-cities-data');
/*********************************************************************** */

/** Category */
Route::get('get_categories', [CategoryApiController::class, 'get_categories'])->name('get_categories');
/*********************************************************************** */

/** Payment */
Route::match(['GET', 'POST'], 'flutterwave-payment-status', [PaymentController::class, 'flutterwavePaymentStatus'])->name('flutterwave-payment-status');
Route::match(['GET', 'POST'], 'flutterwave-payment-status-web', [PaymentController::class, 'flutterwavePaymentStatusWeb'])->name('flutterwave-payment-status-web');
/*********************************************************************** */

/** Advertisement */
Route::get('get_advertisement', [AdvertisementApiController::class, 'get_advertisement'])->name('get_advertisement');
/*********************************************************************** */
Route::post('mortgage_calc', [ApiController::class, 'mortgage_calc'])->name('mortgage_calc');

/** Projects */
Route::get('get-projects', [ProjectApiController::class, 'getProjects'])->name('get-projects');
Route::get('get-project-detail', [ProjectApiController::class, 'getProjectDetail'])->name('get-project-detail');
/*********************************************************************** */

/** Package */
// Route::get('get_package', [ApiController::class, 'get_package']);
Route::get('get-package', [PackageApiController::class, 'getPackages'])->name('get-package');
Route::get('get-features', [PackageApiController::class, 'getFeatures'])->name('get-features');
/*********************************************************************** */

/** Agents */

/*********************************************************************** */

/** Settings */
Route::get('get_languages', [SettingsApiController::class, 'get_languages'])->name('get_languages');
Route::get('get_app_settings', [SettingsApiController::class, 'get_app_settings'])->name('get_app_settings');
Route::get('web-settings', [SettingsApiController::class, 'getWebSettings'])->name('web-settings');
Route::get('app-settings', [SettingsApiController::class, 'getAppSettings'])->name('app-settings');
Route::get('deep-link', [SettingsApiController::class, 'deepLink'])->name('deep-link');
Route::get('privacy-policy', [SettingsApiController::class, 'getPrivacyPolicy'])->name('privacy-policy');
Route::get('terms-conditions', [SettingsApiController::class, 'getTermsAndConditions'])->name('terms-conditions');
Route::get('about-us', [SettingsApiController::class, 'getAboutUs'])->name('about-us');
/*********************************************************************** */

// Route::get('homepage-data', [ApiController::class, 'homepageData']);

/** Homepage Grouped APIs */
Route::get('ad-banners', [HomepageApiController::class, 'getAdBanners'])->name('ad-banners');

Route::group(['middleware' => ['user']], function () {
    Route::get('get-property-list', [PropertyApiController::class, 'getPropertyList'])->name('get-property-list');

    Route::prefix('homepage')->group(function () {
        Route::get('sections-data', [HomepageApiController::class, 'getHomepageSectionsData'])->name('sections-data');
        Route::get('property-sections', [HomepageApiController::class, 'getHomepagePropertySections'])->name('property-sections');
        Route::get('project-sections', [HomepageApiController::class, 'getHomepageProjectSections'])->name('project-sections');
        Route::get('other-sections', [HomepageApiController::class, 'getHomepageOtherSections'])->name('other-sections');
        Route::get('properties-by-city', [HomepageApiController::class, 'getHomepagePropertiesByCity'])->name('properties-by-city');
        Route::get('properties-on-map', [HomepageApiController::class, 'getHomepagePropertiesOnMap'])->name('properties-on-map');
    });

});

// Routes without active role middleware (agent/user distinction not needed)
Route::withoutMiddleware(ActiveRoleMiddleware::class)->group(function () {
    Route::get('get-agent-packages', [PackageApiController::class, 'getAgentPackages'])->name('get-agent-packages');
});
/*********************************************************************** */
