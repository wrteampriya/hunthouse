<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationTranslationService
{
    /**
     * Get customer's language with fallback chain
     * Priority: Customer default → Admin default → 'en'
     */
    public static function getCustomerLanguage($customerId)
    {
        try {
            // Get customer's default language
            $customer = Customer::select('default_language')->find($customerId);

            if ($customer && ! empty($customer->default_language)) {
                // Validate language exists and is active
                $language = Language::where('code', $customer->default_language)
                    ->where('status', 1)
                    ->first();

                if ($language) {
                    return $customer->default_language;
                }
            }

            // Fallback to admin's default language
            $adminDefaultLanguage = HelperService::getSettingData('default_language');
            if (! empty($adminDefaultLanguage)) {
                $language = Language::where('code', $adminDefaultLanguage)
                    ->where('status', 1)
                    ->first();

                if ($language) {
                    return $adminDefaultLanguage;
                }
            }

            // Final fallback to 'en'
            return 'en';

        } catch (\Exception $e) {
            Log::error('Error getting customer language: '.$e->getMessage());

            return 'en';
        }
    }

    /**
     * Load translation file for a specific language code
     * Uses caching to avoid repeated file reads
     */
    public static function loadTranslationFile($languageCode)
    {
        try {
            // Handle 'en' special case (might be 'en-new' in database)
            if ($languageCode == 'en') {
                $languageCode = 'en';
            }

            return Cache::remember("translation_file_{$languageCode}", 3600, function () use ($languageCode) {
                $filePath = resource_path("lang/{$languageCode}.json");

                if (! file_exists($filePath)) {
                    // Fallback to en.json if language file doesn't exist
                    if ($languageCode != 'en') {
                        $filePath = resource_path('lang/en.json');
                    }
                }

                if (file_exists($filePath)) {
                    $json = file_get_contents($filePath);
                    $translations = json_decode($json, true);

                    return is_array($translations) ? $translations : [];
                }

                return [];
            });

        } catch (\Exception $e) {
            Log::error("Error loading translation file for {$languageCode}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Translate text using translation keys
     * Returns translated text if key exists, otherwise returns original text
     */
    public static function translate($text, $languageCode, array $replace = [])
    {
        try {
            if (empty($languageCode) || $languageCode === 'en') {
                return self::applyReplacements($text, $replace);
            }

            $translations = self::loadTranslationFile($languageCode);
            $translatedText = $translations[$text] ?? $text;

            return self::applyReplacements($translatedText, $replace);
        } catch (\Exception $e) {
            Log::error('Error translating text: '.$e->getMessage());

            return self::applyReplacements($text, $replace);
        }
    }

    /**
     * Apply replacements like Laravel’s trans() function
     */
    protected static function applyReplacements($text, array $replace)
    {
        foreach ($replace as $key => $value) {
            $text = str_replace(':'.$key, $value, $text);
        }

        return $text;
    }

    /**
     * Translate notification title and body
     */
    public static function translateNotification($title, $body, $languageCode, array $replace = [])
    {
        return [
            'title' => self::translate($title, $languageCode, $replace),
            'body' => self::translate($body, $languageCode, $replace),
        ];
    }
}
