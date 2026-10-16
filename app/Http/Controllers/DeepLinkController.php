<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\HelperService;

class DeeplinkController extends Controller
{
    public function handle(Request $request, $param1 = null, $param2 = null)
    {
        $slug = $param2 ?? $param1;
        $path = $request->path();
        $settingsData = HelperService::getMultipleSettingData(['playstore_id', 'appstore_id', 'schema_for_deeplink']);
        // Return Blade view with slug, path, and settingsData
        return view('deep-link.redirect-to-app', compact('slug', 'path', 'settingsData'));
    }
}
