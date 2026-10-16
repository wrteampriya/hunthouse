<?php

namespace App\Console\Commands;

use App\Models\Usertokens;
use App\Models\UserPackage;
use App\Services\HelperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotifyExpiringSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-expiring-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users before their subscriptions expire';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('NotifyExpiringSubscriptions command started');
        $settingsData = array(
            'notify_user_for_subscription_expiry',
            'days_before_subscription_expiry'
        );
        $settingsQuery = HelperService::getMultipleSettingData($settingsData);
        $notifyUserForSubscriptionExpiry = $settingsQuery['notify_user_for_subscription_expiry'];
        $daysBeforeSubscriptionExpiry = $settingsQuery['days_before_subscription_expiry'];

        if(empty($notifyUserForSubscriptionExpiry)){
            return;
        }
        if(empty($daysBeforeSubscriptionExpiry)){
            $daysBeforeSubscriptionExpiry = 5;
        }

        $targetDate = now()->addDays($daysBeforeSubscriptionExpiry)->startOfDay();

        $userPackagesQuery = UserPackage::whereDate('end_date', $targetDate);
        $userPackages = $userPackagesQuery->clone()->with('customer')->get();

        // Get Data of email type
        $emailTypeData = HelperService::getEmailTemplatesTypes("subscription_expiring_soon");
        $appName = env("APP_NAME") ?? "eBroker";
        foreach ($userPackages as $userPackage) {
            $variables = array(
                'app_name' => $appName,
                'user_name' => !empty($userPackage->customer->name) ? $userPackage->customer->name : "$appName User",
                'email' => $userPackage->customer->email,
                'package_name' => $userPackage->package->name,
                'subscription_end_date' => $userPackage->end_date,
            );
            $subscriptionExpiringSoonTemplateData = HelperService::getSettingData($emailTypeData['type']);
            $subscriptionExpiringSoonTemplate = HelperService::replaceEmailVariables($subscriptionExpiringSoonTemplateData,$variables);

            $data = array(
                'email_template' => $subscriptionExpiringSoonTemplate,
                'email' => $userPackage->customer->email,
                'title' => $emailTypeData['title'],
            );
            HelperService::sendMail($data);
        }


        $userIds = $userPackagesQuery->clone()->pluck('user_id');
        $userFCMTokens = Usertokens::whereIn('customer_id', $userIds)->pluck('fcm_id');
        if(!empty($userFCMTokens)){
            $fcmMsg = array(
                'title' => 'Subscription Expiring Soon',
                'message' => 'Your subscription is expiring soon',
                'type' => 'subscription_expiring_soon',
                'body' => 'Your subscription is expiring soon',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default'
            );
            send_push_notification($userFCMTokens, $fcmMsg);
        }

    }
}
