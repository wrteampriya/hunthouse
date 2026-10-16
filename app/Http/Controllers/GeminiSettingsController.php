<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Services\HelperService;
use App\Services\ResponseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiSettingsController extends Controller
{
    /**
     * Display Gemini AI settings page
     */
    public function index()
    {
        if (!has_permissions('read', 'gemini_settings')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $settingsArray = [
            'gemini_ai_enabled',
            'gemini_api_key',
            'gemini_description_limit',
            'gemini_meta_limit',
            'gemini_description_limit_global',
            'gemini_meta_limit_global',
            'gemini_search_limit_user',
            'gemini_search_limit_global',
        ];

        $settings = HelperService::getMultipleSettingData($settingsArray, true);

        return view('settings.gemini-settings', compact('settings'));
    }

    /**
     * Update Gemini AI settings
     */
    public function update(Request $request)
    {
        if (!has_permissions('update', 'gemini_settings')) {
            ResponseService::errorResponse(trans(PERMISSION_ERROR_MSG));
        }

        try {
            DB::beginTransaction();
            // allow 0 = unlimited

            $validated = $request->validate([
                'gemini_ai_enabled'                 => 'nullable|in:0,1',
                'gemini_api_key'                    => 'nullable|string',
                'gemini_description_limit'          => 'required|integer|min:0|max:1000',
                'gemini_meta_limit'                 => 'required|integer|min:0|max:1000',
                'gemini_description_limit_global'   => 'required|integer|min:0|max:1000',
                'gemini_meta_limit_global'          => 'required|integer|min:0|max:1000',
                // 'gemini_search_limit_user'          => 'required|integer|min:0|max:1000',
                // 'gemini_search_limit_global'        => 'required|integer|min:0|max:1000',
            ]);

            // Handle toggle - if not set, default to 0 (disabled)
            $validated['gemini_ai_enabled'] = $request->has('gemini_ai_enabled') ? ($request->gemini_ai_enabled ?? '0') : '0';

            foreach ($validated as $key => $value) {
                Setting::updateOrCreate(
                    ['type' => $key],
                    ['data' => $value]
                );
            }

            // Update .env if API key changed
            if ($request->has('gemini_api_key') && !empty($request->gemini_api_key)) {
                $this->updateEnvFile('GEMINI_API_KEY', $request->gemini_api_key);
            }

            DB::commit();

            ResponseService::successResponse(trans('Settings updated successfully'));
        } catch (\Exception $e) {
            DB::rollBack();
            ResponseService::errorResponse('Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Update .env file
     */
    private function updateEnvFile(string $key, string $value)
    {
        $envFile = base_path('.env');
        if (!file_exists($envFile)) {
            return;
        }

        $envContent = file_get_contents($envFile);
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
        } else {
            $envContent .= "\n{$key}={$value}\n";
        }

        file_put_contents($envFile, $envContent);
    }

    /**
     * Clear Gemini AI cache
     */
    public function clearCache()
    {
        if (!has_permissions('update', 'gemini_settings')) {
            return ResponseService::errorResponse(trans(PERMISSION_ERROR_MSG));
        }

        try {
            Log::info('Gemini AI cache cleared');
            Cache::store('gemini')->clear();
            return ResponseService::successResponse(trans('Gemini AI cache cleared successfully'));
        } catch (\Exception $e) {
            Log::error('Failed to clear Gemini AI cache: ' . $e->getMessage());
            return ResponseService::errorResponse('Failed to clear cache: ' . $e->getMessage());
        }
    }
}

