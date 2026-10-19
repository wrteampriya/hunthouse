<?php

namespace App\Http\Controllers;

use App\Services\HelperService;

class DeeplinkController extends Controller
{
    public function handle($slug)
    {
        $settingsData = HelperService::getMultipleSettingData(['playstore_id', 'appstore_id', 'schema_for_deeplink']);

        // Just return a Blade view with the slug
        return view('deep-link.redirect-to-app', compact('slug', 'settingsData'));
    }
}
