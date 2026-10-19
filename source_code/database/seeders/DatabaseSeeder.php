<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Duplicate the file
        $sourceFile = resource_path('lang/en.json');
        $destinationFile = resource_path('lang/en-new.json');
        File::copy($sourceFile, $destinationFile);

        $sourceFile = public_path('languages/en.json');
        $destinationFile = public_path('languages/en-new.json');
        File::copy($sourceFile, $destinationFile);

        $sourceFile = public_path('web_languages/en.json');
        $destinationFile = public_path('web_languages/en-new.json');
        File::copy($sourceFile, $destinationFile);

        DB::table('languages')->insert(
            [
                'name' => 'English',
                'code' => 'en-new',
                'file_name' => 'en-new.json',
                'status' => '1',
            ],
        );

        DB::table('settings')->insert(
            [
                [
                    'type' => 'company_name',
                    'data' => 'eBroker',
                ],
                [
                    'type' => 'currency_symbol',
                    'data' => '$',
                ],
                [
                    'type' => 'ios_version',
                    'data' => '1.0.0',
                ],
                [
                    'type' => 'default_language',
                    'data' => 'en-new',
                ],
                [
                    'type' => 'force_update',
                    'data' => '0',
                ],
                [
                    'type' => 'android_version',
                    'data' => '1.0.0',
                ],
                [
                    'type' => 'number_with_suffix',
                    'data' => '0',
                ],
                [
                    'type' => 'maintenance_mode',
                    'data' => 0,
                ],
                [
                    'type' => 'privacy_policy',
                    'data' => 'Privacy Policy here',
                ],
                [
                    'type' => 'terms_conditions',
                    'data' => 'Terms and Conditions here',
                ],
                [
                    'type' => 'company_tel1',
                    'data' => '+91 97124 45459',
                ],
                [
                    'type' => 'company_tel2',
                    'data' => '+91 97124 45459',
                ],
                [
                    'type' => 'razorpay_gateway',
                    'data' => '0',
                ],
                [
                    'type' => 'paystack_gateway',
                    'data' => '0',
                ],
                [
                    'type' => 'paypal_gateway',
                    'data' => '0',
                ],
                [
                    'type' => 'system_version',
                    'data' => '1.4.1',
                ],
                [
                    'type' => 'company_logo',
                    'data' => 'logo.png',
                ],
                [
                    'type' => 'web_logo',
                    'data' => 'web_logo.png',
                ],
                [
                    'type' => 'favicon_icon',
                    'data' => 'favicon.png',
                ],
                [
                    'type' => 'web_favicon',
                    'data' => 'favicon.png',
                ],
                [
                    'type' => 'web_footer_logo',
                    'data' => 'Logo_white.svg',
                ],
                [
                    'type' => 'web_placeholder_logo',
                    'data' => 'placeholder.svg',
                ],
                [
                    'type' => 'app_home_screen',
                    'data' => 'homeLogo.png',
                ],
                [
                    'type' => 'placeholder_logo',
                    'data' => 'placeholder.png',
                ],
                [
                    'type' => 'system_color',
                    'data' => '#087c7c',
                ],
                [
                    'type' => 'facebook_id',
                    'data' => 'https://www.facebook.com/wrteam.in/',
                ],
                [
                    'type' => 'instagram_id',
                    'data' => 'https://www.instagram.com/wrteam.in/',
                ],
                [
                    'type' => 'twitter_id',
                    'data' => 'https://twitter.com/wrteamin',
                ],
                [
                    'type' => 'youtube_id',
                    'data' => 'https://www.youtube.com/@WRTeam',
                ],
                [
                    'type' => 'iframe_link',
                    'data' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3666.2870572691577!2d69.6415340756824!3d23.232638979027072!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39511e5b00000001%3A0xc42d67c61628af6d!2sWRTeam%20Pvt.%20Ltd.!5e0!3m2!1sen!2sin!4v1741677094972!5m2!1sen!2sin',
                ],
                [
                    'type' => 'latitude',
                    'data' => '23.2419997',
                ],
                [
                    'type' => 'longitude',
                    'data' => '69.6669324',
                ],
                [
                    'type' => 'company_address',
                    'data' => '#262-263, Time Square Empire, SH 42 Mirjapar highway,Bhuj - Kutch 370001 Gujarat India.',
                ],
                [
                    'type' => 'company_email',
                    'data' => 'support@wrteam.in',
                ],
                [
                    'type' => 'playstore_id',
                    'data' => 'https://play.google.com/store/apps/details?id=com.ebroker.wrteam',
                ],
                [
                    'type' => 'appstore_id',
                    'data' => 'https://testflight.apple.com/join/nrmIds1a',
                ],
            ]
        );
    }
}
