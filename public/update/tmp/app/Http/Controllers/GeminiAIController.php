<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\GeminiUsage;
use App\Models\Language;
use App\Services\GeminiService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GeminiAIController extends Controller
{
    private GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Check if Gemini AI is enabled
     */
    private function isGeminiEnabled(): bool
    {
        $enabled = HelperService::getSettingData('gemini_ai_enabled');

        return $enabled == '1';
    }

    /**
     * Generate description for property/project
     */
    public function generateDescription(Request $request)
    {
        // Check if Gemini AI is enabled
        if (! $this->isGeminiEnabled()) {
            Log::warning('Gemini AI: generateDescription called but gemini_ai_enabled is not set to 1 in settings.');

            return ResponseService::validationError('Content Generation currently not available please try again later.');
        }

        try {
            $validator = Validator::make($request->all(), [
                'entity_type' => 'required|in:property,project',
                'entity_id' => 'nullable|integer',
                'title' => 'required|string|max:255',
                'location' => 'nullable|string',
                'city' => 'nullable|string',
                'state' => 'nullable|string',
                'country' => 'nullable|string',
                'price' => 'nullable|string',
                'property_type' => 'nullable|string|in:sell,rent',
                'category_id' => 'nullable|integer|exists:categories,id',
                'language_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            // Get user info
            $user = Auth::guard('sanctum')->user() ?? Auth::user();
            $userId = $user ? $user->id : null;
            $userType = $user instanceof Customer ? 'customer' : 'admin';

            // Prepare data
            $data = $request->only([
                'title', 'location', 'city', 'state', 'country',
                'price', 'property_type',
            ]);
            foreach ($data as $key => $value) {
                if (empty($value)) {
                    unset($data[$key]);
                }
            }
            if ($request->has('category_id') && $request->category_id != null) {
                $category = Category::find($request->category_id);
                if (collect($category)->isNotEmpty()) {
                    $data['category_name'] = $category->category;
                }
            }

            // Add language information if provided
            // First check if language is explicitly provided in request
            if ($request->has('language_id') && ! empty($request->language_id)) {
                $data['language_id'] = $request->language_id;
                // Get language from database - check for exact match first
                $language = Language::where('id', $request->language_id)->where('status', 1)->first();
                if ($language) {
                    $data['language_name'] = $language->name;
                    $data['language_code'] = $language->code;
                } else {
                    return ResponseService::validationError('Language not found');
                }
            }

            // Check if cached data exists first - if cached, skip rate limit check
            $isCached = $this->geminiService->hasCachedDescription($data, $request->entity_type);

            // Only check limits if data is not cached (new request)
            if (! $isCached) {
                // 1) Global limit (all users combined)
                $globalLimit = $this->getGlobalRateLimit('description');
                if ($globalLimit > 0 && GeminiUsage::hasExceededGlobalLimit('description', $globalLimit, 24)) {
                    return ResponseService::validationError(
                        'Daily limit reached.'
                    );
                }

                // 2) Per-user limit (kept for backward compatibility, mainly for customers)
                if ($userType === 'customer') {
                    $limit = $this->getRateLimit('description');
                    if ($userId && GeminiUsage::hasExceededLimit($userId, $userType, 'description', $limit, 24)) {
                        return ResponseService::validationError(
                            'Daily limit reached.'
                        );
                    }
                }
            }

            // Generate description
            $result = $this->geminiService->generateDescription(
                $data,
                $request->entity_type
            );

            if (! $result['success']) {
                return ResponseService::validationError($result['error'] ?? 'Failed to generate description');
            }

            // Track usage only if not cached (new request)
            if ($userId && ! ($result['cached'] ?? false)) {
                $promptHash = md5($this->geminiService->buildDescriptionPrompt($data, $request->entity_type));
                GeminiUsage::create([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'type' => 'description',
                    'entity_type' => $request->entity_type,
                    'entity_id' => $request->entity_id,
                    'prompt_hash' => $promptHash,
                    'tokens_used' => $result['tokens_used'] ?? null,
                    'ip_address' => $request->ip(),
                ]);
            }

            return ResponseService::successResponse('Description generated successfully', [
                'description' => $result['data'],
                'cached' => $result['cached'] ?? false,
            ]);

        } catch (Exception $e) {
            Log::error('Gemini Description Generation Error: '.$e->getMessage());

            return ResponseService::validationError('Content Generation currently not available please try again later.');
        }
    }

    /**
     * Generate meta details for property/project
     */
    public function generateMetaDetails(Request $request)
    {
        // Check if Gemini AI is enabled
        if (! $this->isGeminiEnabled()) {
            Log::warning('Gemini AI: generateMetaDetails called but gemini_ai_enabled is not set to 1 in settings.');

            return ResponseService::validationError('Content Generation currently not available please try again later.');
        }

        try {
            $validator = Validator::make($request->all(), [
                'entity_type' => 'required|in:property,project',
                'entity_id' => 'nullable|integer',
                'title' => 'required|string|max:255',
                'location' => 'nullable|string',
                'city' => 'nullable|string',
                'state' => 'nullable|string',
                'country' => 'nullable|string',
                'price' => 'nullable|string',
                'language_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            // Get user info
            $user = Auth::guard('sanctum')->user() ?? Auth::user();
            $userId = $user ? $user->id : null;
            $userType = $user instanceof Customer ? 'customer' : 'admin';

            // Prepare data
            $data = $request->only(['title', 'location', 'city', 'state', 'country', 'price']);

            // Add language information if provided
            if ($request->has('language_id') && ! empty($request->language_id)) {
                $data['language_id'] = $request->language_id;
                // Get language from database - check for exact match first
                $language = Language::where('id', $request->language_id)->where('status', 1)->first();
                if ($language) {
                    $data['language_name'] = $language->name;
                    $data['language_code'] = $language->code;
                } else {
                    return ResponseService::validationError('Language not found');
                }
            }

            // Check if cached data exists first - if cached, skip rate limit check
            $isCached = $this->geminiService->hasCachedMetaDetails($data, $request->entity_type);

            // Only check limits if data is not cached (new request)
            if (! $isCached) {
                // 1) Global limit (all users combined)
                $globalLimit = $this->getGlobalRateLimit('meta');
                if ($globalLimit > 0 && GeminiUsage::hasExceededGlobalLimit('meta', $globalLimit, 24)) {
                    return ResponseService::validationError(
                        'Daily limit reached.'
                    );
                }

                // 2) Per-user limit (kept for backward compatibility, mainly for customers)
                if ($userType === 'customer') {
                    $limit = $this->getRateLimit('meta');
                    if ($userId && GeminiUsage::hasExceededLimit($userId, $userType, 'meta', $limit, 24)) {
                        return ResponseService::validationError(
                            'Daily limit reached.'
                        );
                    }
                }
            }

            // Generate meta details
            $result = $this->geminiService->generateMetaDetails(
                $data,
                $request->entity_type
            );

            if (! $result['success']) {
                return ResponseService::validationError($result['error'] ?? 'Failed to generate meta details');
            }

            // Track usage only if not cached (new request)
            if ($userId && ! ($result['cached'] ?? false)) {
                $promptHash = md5($this->geminiService->buildMetaPrompt($data, $request->entity_type));
                GeminiUsage::create([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'type' => 'meta',
                    'entity_type' => $request->entity_type,
                    'entity_id' => $request->entity_id,
                    'prompt_hash' => $promptHash,
                    'tokens_used' => $result['tokens_used'] ?? null,
                    'ip_address' => $request->ip(),
                ]);
            }

            return ResponseService::successResponse('Meta details generated successfully', [
                'meta_title' => $result['data']['meta_title'] ?? '',
                'meta_description' => $result['data']['meta_description'] ?? '',
                'meta_keywords' => $result['data']['meta_keywords'] ?? '',
                'cached' => $result['cached'] ?? false,
            ]);

        } catch (Exception $e) {
            Log::error('Gemini Meta Generation Error: '.$e->getMessage());

            return ResponseService::errorResponse('Content Generation currently not available please try again later.');
        }
    }

    /**
     * Get rate limit from settings
     */
    private function getRateLimit(string $type): int
    {
        $settingKey = $type === 'description'
            ? 'gemini_description_limit'
            : 'gemini_meta_limit';

        $limit = HelperService::getSettingData($settingKey);

        // If setting is not defined at all, use default 10
        if ($limit === null || $limit === '') {
            return 10;
        }

        // Explicit 0 means "unlimited" (handled in hasExceededLimit as no limit when <= 0)
        return (int) $limit;
    }

    /**
     * Get GLOBAL rate limit from settings (applies to all users combined)
     *
     * Falls back to per-user limit if global keys are not set.
     */
    private function getGlobalRateLimit(string $type): int
    {
        $settingKey = $type === 'description'
            ? 'gemini_description_limit_global'
            : 'gemini_meta_limit_global';

        $limit = HelperService::getSettingData($settingKey);

        if ($limit !== null && $limit !== '') {
            return (int) $limit;
        }

        // Fallback to existing per-user limit if global not configured
        return $this->getRateLimit($type);
    }
}
