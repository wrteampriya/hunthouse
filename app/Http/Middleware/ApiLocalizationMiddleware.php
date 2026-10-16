<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiLocalizationMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return JsonResponse
     */
    public function handle(Request $request, Closure $next) {
        $localization = $request->header('Content-Language') ?? 'en';
        
        // Validate UTF-8 encoding and sanitize input
        $localization = $this->validateAndSanitizeLocale($localization);
        
        app()->setLocale($localization);

        return $next($request);
    }

    /**
     * Validate and sanitize the locale string
     *
     * @param string $locale
     * @return string
     */
    private function validateAndSanitizeLocale($locale) {
        // Check if the string is valid UTF-8
        if (!mb_check_encoding($locale, 'UTF-8')) {
            return 'en'; // Fallback to default
        }

        // Remove any non-printable characters and trim
        $locale = trim(preg_replace('/[^\x20-\x7E]/', '', $locale));
        
        // Validate format: only lowercase letters, numbers, and hyphens
        if (!preg_match('/^[a-z0-9-]+$/i', $locale)) {
            return 'en'; // Fallback to default
        }

        // Convert to lowercase for consistency
        $locale = strtolower($locale);

        // Check if the language exists in the database
        $validLanguage = Language::where('code', $locale)
                                ->where('status', 1)
                                ->first();

        if (!$validLanguage) {
            return 'en'; // Fallback to default if language not found or inactive
        }

        return $locale;
    }
}
