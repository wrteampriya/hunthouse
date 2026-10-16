<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini AI Service
 *
 * Handles integration with Google Gemini 1.5 Pro API for natural language processing
 * and real estate search parameter extraction. Provides intelligent parsing of queries
 * to extract location, pricing, property types, property titles, facilities, and nearby places.
 *
 * Features:
 * - Global currency support (K/M and lakh/crore formats)
 * - Property title extraction from natural language queries
 * - Comprehensive facilities extraction (20+ property features)
 * - Nearby places detection with distance mapping
 * - Result caching for performance optimization (1-hour TTL)
 * - Smart parameter type validation and constraint enforcement
 */
class GeminiService
{
    /**
     * Guzzle HTTP client for API communication
     */
    private $client;

    /**
     * Google Gemini API key from configuration
     */
    private $apiKey;

    /**
     * Google Gemini API endpoint URL
     */
    private $apiUrl;

    /**
     * Initialize the Gemini service with HTTP client and API configuration
     *
     * Sets up Guzzle client with optimized timeouts and headers for reliable
     * communication with Google Gemini API. Loads API credentials from Laravel config.
     */
    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
            'headers' => [
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
            ],
        ]);
        $this->apiKey = config('services.gemini.api_key');
        $this->apiUrl = config('services.gemini.api_url');
    }

    /**
     * Generate content using Google Gemini API
     *
     * Sends a prompt to Gemini 1.5 Pro model and returns the AI-generated response.
     * Supports optional tools parameter for function calling and structured outputs.
     *
     * @param string $prompt The text prompt to send to the AI model
     * @param array|null $tools Optional tools/functions for structured AI responses
     * @return array Response array with success status and data/error
     */
    public function generateContent($prompt, $tools = null)
    {
        try {
            // Validate API key
            if (empty($this->apiKey)) {
                Log::error('Gemini API Error: Missing API key. Set GEMINI_API_KEY in environment.');
                return [
                    'success' => false,
                    'error' => 'Missing Gemini API key',
                ];
            }

            // Ensure endpoint includes :generateContent
            $endpoint = $this->apiUrl;
            if (strpos($endpoint, ':generateContent') === false && strpos($endpoint, ':streamGenerateContent') === false) {
                $endpoint .= ':generateContent';
            }

            $requestData = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 500, // Limit output to reduce tokens
                    'temperature' => 0.7, // Balance creativity and consistency
                ],
            ];

            if ($tools) {
                $requestData['tools'] = $tools;
            }

            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
                'json' => $requestData,
            ]);

            $status = $response->getStatusCode();
            $raw = $response->getBody()->getContents();
            $body = json_decode($raw, true);

            if ($status < 200 || $status >= 300) {
                Log::error('Gemini API HTTP Error', ['status' => $status, 'body' => $raw]);
                return [
                    'success' => false,
                    'error' => 'Content Generation currently not available please try again later.',
                ];
            }

            return [
                'success' => true,
                'data' => $body,
            ];

        } catch (RequestException $e) {
            Log::error('Gemini API Error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'Failed to generate content: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Extract structured real estate search parameters from natural language query
     *
     * Main entry point for AI-powered parameter extraction. Converts natural language
     * queries like "3 bedroom furnished villa in Mumbai under 2 crore near hospital"
     * or "Sunrise Villa in Bangalore" into structured parameters for property search APIs.
     *
     * Features:
     * - Location extraction (city, state, country) with smart capitalization
     * - Price parsing with global currency support (K/M, lakh/crore)
     * - Property type detection (buy/sell/rent) and category matching
     * - Property title extraction from natural language descriptions
     * - Facilities extraction with intelligent type validation
     * - Nearby places detection with optional distance mapping
     * - Result caching with 1-hour TTL for performance optimization
     *
     * @param string $query Natural language property search query
     * @param array $categories Available property categories with id/name pairs
     * @param array $nearbyPlaces Available nearby places with id/name pairs
     * @param array $facilities Available property facilities with id/name/type/values
     * @return array Extraction result with success status and structured parameters
     */
    public function extractSearchParameters($query, $categories = [], $nearbyPlaces = [], $facilities = [])
    {
		// Translate incoming query to English to support multi-language inputs
		$translatedQuery = $this->translateToEnglish($query);
		Log::info('Translation result', ['original' => $query, 'translated' => $translatedQuery]);

        // Generate cache key based on query and parameters
        // Cache key includes all input parameters to ensure accurate cache hits
		$cacheKey = 'gemini_search_'.md5($translatedQuery.serialize($categories).serialize($nearbyPlaces).serialize($facilities));

        // Try to get from cache first (cache for 1 hour)
        // Caching dramatically improves performance for repeated queries
        $cachedResult = Cache::store('gemini')->get($cacheKey);
        if ($cachedResult) {
            Log::info('Using cached result for query: '.$query);

            return $cachedResult;
        }

        $startTime = microtime(true);

        // Prepare categories section for prompt
        // Dynamic prompt building based on available categories
        $categoriesText = '';
        if (! empty($categories)) {
            $categoriesText = "\n• category_id: Match property type to ID: ";
            $categoryMappings = [];
            foreach ($categories as $category) {
                $categoryMappings[] = "{$category['id']}={$category['name']}";
            }
            $categoriesText .= implode(' ', $categoryMappings);
        }

        // Prepare nearby places section for prompt
        // Builds dynamic list of available nearby places for AI matching
        $nearbyPlacesText = '';
        if (! empty($nearbyPlaces)) {
            $nearbyPlacesText = "\n• nearbyplace: Extract nearby places as array of objects with id, name, and distance (if mentioned): ";
            $nearbyMappings = [];
            foreach ($nearbyPlaces as $place) {
                $nearbyMappings[] = "{$place['id']}={$place['name']}";
            }
            $nearbyPlacesText .= implode(' ', $nearbyMappings);
        }

        // Prepare facilities section for prompt
        // Complex section handling multiple parameter types (textbox, dropdown, checkbox, etc.)
        $facilitiesText = '';
        if (! empty($facilities)) {
            $facilitiesText = "\n• facilities: Extract facilities as array of objects with id, name, and values (comma-separated for multiple values). For textbox/textarea/number types, extract values from query. For dropdown/checkbox/radiobutton, choose from given options: ";
            $facilityMappings = [];
            foreach ($facilities as $facility) {
                $facilityInfo = "{$facility['id']}={$facility['name']}({$facility['type_of_parameter']})";
                if($facility['type_of_parameter'] == 'dropdown' || $facility['type_of_parameter'] == 'checkbox' || $facility['type_of_parameter'] == 'radiobutton'){
                    if(!empty($facility['translated_option_value'])){
                        $translatedOptionValue = $facility['translated_option_value'][0]['translated'] ?? $facility['translated_option_value'][0]['value'];
                        $facilityInfo .= '['.$translatedOptionValue.']';
                    } else {
                        $facilityInfo .= '['.$facility['values'].']';
                    }
                } else {
                    $facilityInfo .= '['.$facility['values'].']';
                }
                $facilityMappings[] = $facilityInfo;
            }
            $facilitiesText .= implode(' ', $facilityMappings);
        }

        // Build optimized AI prompt for parameter extraction
        // Prompt is carefully engineered for 70% token reduction while maintaining accuracy
        // Supports global currency formats and comprehensive parameter extraction
		$prompt = "Extract real estate parameters from: \"{$translatedQuery}\"\n\nReturn JSON with: city, state, country, property_type(0=sell,1=rent), min_price, max_price, title{$categoriesText}{$nearbyPlacesText}{$facilitiesText}\n\nProperty Type Rules:\n- 0=sell: buy, purchase, for sale, selling, buyable\n- 1=rent: rent, rental, renting, rentable, lease, leasing, monthly rent\n- 2=sold: sold, already sold, not available\n- 3=rented: rented, already rented, occupied\n- If no property type mentioned, use null\n\nProperty Title: Extract any specific property name, title, or description mentioned in the query. Use null if no specific property title is mentioned.\n\nPrice: K=×1000, M=×1000000, lakh=×100000, crore=×10000000\nRules: Extract only from provided lists, match case-insensitive, use null/[] for missing. NEVER assign default category_id values.\n\nExamples:\n\"villa London rent\" → {\"city\":\"London\",\"state\":null,\"country\":\"UK\",\"property_type\":1,\"min_price\":null,\"max_price\":null,\"title\":null,\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"buy apartment Mumbai\" → {\"city\":\"Mumbai\",\"state\":null,\"country\":\"India\",\"property_type\":0,\"min_price\":null,\"max_price\":null,\"title\":null,\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"properties in india\" → {\"city\":null,\"state\":null,\"country\":\"India\",\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":null,\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"3 bedroom furnished apartment with parking\" → {\"city\":null,\"state\":null,\"country\":null,\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":null,\"category_id\":null,\"nearbyplace\":[],\"facilities\":[{\"id\":7,\"name\":\"Bedrooms\",\"values\":\"3\"},{\"id\":11,\"name\":\"Furnishing Status\",\"values\":\"Fully Furnished\"},{\"id\":1,\"name\":\"Parking\",\"values\":\"Yes\"}]}\n\"properties in dubai\" → {\"city\":\"Dubai\",\"state\":null,\"country\":\"United Arab Emirates\",\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":null,\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"Sunrise Villa in Bangalore\" → {\"city\":\"Bangalore\",\"state\":null,\"country\":\"India\",\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":\"Sunrise Villa\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"Luxury Penthouse Ocean View\" → {\"city\":null,\"state\":null,\"country\":null,\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":\"Luxury Penthouse Ocean View\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"Royal Gardens Apartment for sale\" → {\"city\":null,\"state\":null,\"country\":null,\"property_type\":0,\"min_price\":null,\"max_price\":null,\"title\":\"Royal Gardens Apartment\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"Marina Heights Tower rent in Dubai\" → {\"city\":\"Dubai\",\"state\":null,\"country\":\"United Arab Emirates\",\"property_type\":1,\"min_price\":null,\"max_price\":null,\"title\":\"Marina Heights Tower\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"Green Valley Residency 2BHK\" → {\"city\":null,\"state\":null,\"country\":null,\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":\"Green Valley Residency\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[{\"id\":7,\"name\":\"Bedrooms\",\"values\":\"2\"}]}\n\"Palm Springs Villa with pool\" → {\"city\":null,\"state\":null,\"country\":null,\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":\"Palm Springs Villa\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[{\"id\":15,\"name\":\"Swimming Pool\",\"values\":\"Yes\"}]}\n\"Skyline Plaza commercial space\" → {\"city\":null,\"state\":null,\"country\":null,\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":\"Skyline Plaza\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\"Complex in bhuj\" → {\"city\":\"bhuj\",\"state\":null,\"country\":null,\"property_type\":null,\"min_price\":null,\"max_price\":null,\"title\":\"Complex\",\"category_id\":null,\"nearbyplace\":[],\"facilities\":[]}\n\nJSON:";


        $result = $this->generateContent($prompt);

        if (! $result['success']) {
            return $result;
        }

        try {
            $content = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Clean up the AI response by removing markdown formatting
            // Handles various response formats from Gemini API
            $content = trim($content);
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*$/', '', $content);
            $content = trim($content);

            Log::info('Gemini Raw Response: '.$content);

            $extractedData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error: '.json_last_error_msg());
                Log::error('Content: '.$content);

                return [
                    'success' => false,
                    'error' => 'Failed to parse extracted parameters: '.json_last_error_msg(),
                ];
            }

            // Validate and structure the response data
            // Ensures consistent output format regardless of AI response variations
            $validatedData = [
                'location' => array(
                    'city' => $extractedData['city'] ?? null,
                    'state' => $extractedData['state'] ?? null,
                    'country' => $extractedData['country'] ?? null,
                    'place_id' => $extractedData['place_id'] ?? null,
                    'latitude' => $extractedData['latitude'] ?? null,
                    'longitude' => $extractedData['longitude'] ?? null,
                    'range' => $extractedData['range'] ?? null,
                ),
                'price' => array(
                    'min_price' => $extractedData['min_price'] ?? null,
                    'max_price' => $extractedData['max_price'] ?? null,
                ),
                'property_type' => $extractedData['property_type'] ?? null,
                'title' => $extractedData['title'] ?? null,
                'category_id' => $extractedData['category_id'] ?? null,
                'nearbyplace' => $extractedData['nearbyplace'] ?? [],
                'facilities' => $extractedData['facilities'] ?? [],
            ];

            Log::info('Extracted Parameters: ', $validatedData);

            $result = [
                'success' => true,
                'data' => $validatedData,
            ];

            // Cache the result for 1 hour (3600 seconds)
            // Significantly improves performance for repeated or similar queries
            Cache::store('gemini')->put($cacheKey, $result, 3600);

            // Log performance metrics for monitoring and optimization
            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            Log::info("Gemini API call completed in: {$executionTime}ms for query: ".$query);

            return $result;

        } catch (\Exception $e) {
            Log::error('Parameter extraction error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'Failed to extract parameters: '.$e->getMessage(),
            ];
        }

    }

	/**
	 * Translate any incoming query to English using Gemini for consistent extraction
	 * Falls back to original text on any failure. Caches results for 1 hour.
	 *
	 * @param string $text
	 * @return string
	 */
	private function translateToEnglish($text)
	{
		try {
			$cacheKey = 'gemini_translate_'.md5($text);
			$cached = Cache::store('gemini')->get($cacheKey);
			if ($cached) {
				return $cached;
			}

			$prompt = "Translate the following real estate search query into natural, fluent English. Only return the translated query text without quotes, code blocks, or explanations.\n\n".$text;
			$response = $this->generateContent($prompt);
			if (! $response['success']) {
				return $text;
			}

			$content = $response['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';
			$content = trim($content);
			$content = preg_replace('/```json\s*/', '', $content);
			$content = preg_replace('/```\s*$/', '', $content);
			$content = trim($content, " \t\n\r\0\x0B\"'");

			if ($content === '') {
				return $text;
			}

			Cache::store('gemini')->put($cacheKey, $content, 3600);
			return $content;
		} catch (\Exception $e) {
			Log::warning('Translation failed, using original query: '.$e->getMessage());
			return $text;
		}
	}

    /**
     * Generate property/project description using Gemini AI
     *
     * @param array $data Property/Project data (title, location, price, etc.)
     * @param string $entityType 'property' or 'project'
     * @return array Response with success status and generated description
     */
    public function generateDescription(array $data, string $entityType = 'property')
    {
        try {
            // Build optimized prompt
            $prompt = $this->buildDescriptionPrompt($data, $entityType);
            $promptHash = md5($prompt);

            // Include language in cache key if provided
            $languageSuffix = '';
            if (!empty($data['language_code'])) {
                $languageSuffix = '_' . $data['language_code'];
            } elseif (!empty($data['language_name'])) {
                $languageSuffix = '_' . md5($data['language_name']);
            }

            // Check cache first
            $cacheKey = "gemini_description_{$promptHash}{$languageSuffix}";
            $cached = Cache::store('gemini')->get($cacheKey);
            if ($cached) {
                return [
                    'success' => true,
                    'data' => $cached,
                    'cached' => true,
                ];
            }

            // Generate content
            $result = $this->generateContent($prompt);

            if (!$result['success']) {
                return $result;
            }

            $content = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $content = $this->cleanResponse($content);

            // Cache for 24 hours
            Cache::store('gemini')->put($cacheKey, $content, 86400);

            return [
                'success' => true,
                'data' => $content,
                'cached' => false,
                'tokens_used' => $this->estimateTokens($prompt . $content),
            ];

        } catch (\Exception $e) {
            Log::error('Gemini Description Generation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate description: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate meta details (title, description, keywords) using Gemini AI
     *
     * @param array $data Property/Project data
     * @param string $entityType 'property' or 'project'
     * @return array Response with success status and generated meta details
     */
    public function generateMetaDetails(array $data, string $entityType = 'property')
    {
        try {
            $prompt = $this->buildMetaPrompt($data, $entityType);
            $promptHash = md5($prompt);

            // Include language in cache key if provided
            $languageSuffix = '';
            if (!empty($data['language_code'])) {
                $languageSuffix = '_' . $data['language_code'];
            } elseif (!empty($data['language_name'])) {
                $languageSuffix = '_' . md5($data['language_name']);
            }

            // Check cache
            $cacheKey = "gemini_meta_{$promptHash}{$languageSuffix}";
            $cached = Cache::store('gemini')->get($cacheKey);
            if ($cached) {
                return [
                    'success' => true,
                    'data' => $cached,
                    'cached' => true,
                ];
            }

            // Simple generateContent call, no tools
            $result = $this->generateContent($prompt);

            if (!$result['success']) {
                return $result;
            }

            // Read raw text
            $content = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $content = $this->cleanResponse($content);

            // Try JSON decode first
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $response = [
                    'meta_title'       => $decoded['meta_title']       ?? '',
                    'meta_description' => $decoded['meta_description'] ?? '',
                    'meta_keywords'    => $decoded['meta_keywords']    ?? '',
                ];
            } else {
                // Fallback to text parsing
                $response = $this->parseMetaFromText($content);
            }

            // Cache for 24 hours
            Cache::store('gemini')->put($cacheKey, $response, 86400);

            return [
                'success' => true,
                'data'    => $response,
                'cached'  => false,
                'tokens_used' => $this->estimateTokens($prompt . json_encode($response)),
            ];

        } catch (\Exception $e) {
            Log::error('Gemini Meta Generation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'Failed to generate meta details: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build optimized description prompt
     */
    public function buildDescriptionPrompt(array $data, string $entityType): string
    {
        $entityName = $entityType === 'property' ? 'property' : 'project';

        // More concise prompt
        $prompt = "Write SEO-friendly {$entityName} description (200-300 words):\n";
        $prompt .= "Title: " . ($data['title'] ?? 'N/A');

        // Only add non-empty fields
        $fields = [];
        if (!empty($data['location'])) $fields[] = "Location: {$data['location']}";
        if (!empty($data['city'])) $fields[] = "City: {$data['city']}";
        if (!empty($data['state'])) $fields[] = "State: {$data['state']}";
        if (!empty($data['country'])) $fields[] = "Country: {$data['country']}";
        if (!empty($data['price'])) $fields[] = "Price: {$data['price']}";
        if (!empty($data['propery_type']) || !empty($data['type'])) {
            $type = $data['propery_type'] ?? $data['type'] ?? '';
            $fields[] = "Type: {$type}";
        }
        if (!empty($data['category_name'])) {
            $fields[] = "Category: {$data['category_name']}";
        }

        if (!empty($fields)) {
            $prompt .= "\n" . implode("\n", $fields);
        }

        $prompt .= "\n\nRequirements: Engaging, professional, highlight features, location benefits. Text only.";

        // Add language instruction if language is specified
        if (!empty($data['language_name']) || !empty($data['language_code'])) {
            $language = $data['language_name'] ?? $data['language_code'];
            $prompt .= "\n\nIMPORTANT: Write the description in {$language} language. The entire description must be in {$language}.";
        }

        return $prompt;
    }

    /**
     * Check if cached description exists
     */
    public function hasCachedDescription(array $data, string $entityType): bool
    {
        $prompt = $this->buildDescriptionPrompt($data, $entityType);
        $promptHash = md5($prompt);

        // Include language in cache key if provided
        $languageSuffix = '';
        if (!empty($data['language_code'])) {
            $languageSuffix = '_' . $data['language_code'];
        } elseif (!empty($data['language_name'])) {
            $languageSuffix = '_' . md5($data['language_name']);
        }

        $cacheKey = "gemini_description_{$promptHash}{$languageSuffix}";
        return Cache::store('gemini')->has($cacheKey);
    }

    /**
     * Check if cached meta details exist
     */
    public function hasCachedMetaDetails(array $data, string $entityType): bool
    {
        $prompt = $this->buildMetaPrompt($data, $entityType);
        $promptHash = md5($prompt);

        // Include language in cache key if provided
        $languageSuffix = '';
        if (!empty($data['language_code'])) {
            $languageSuffix = '_' . $data['language_code'];
        } elseif (!empty($data['language_name'])) {
            $languageSuffix = '_' . md5($data['language_name']);
        }

        $cacheKey = "gemini_meta_{$promptHash}{$languageSuffix}";
        return Cache::store('gemini')->has($cacheKey);
    }

    /**
     * Build optimized meta prompt
     */
    public function buildMetaPrompt(array $data, string $entityType): string
    {
        $entityName = $entityType === 'property' ? 'property' : 'project';

        $prompt = "You are an SEO assistant. Based on the following {$entityName} data, ".
                  "generate SEO meta details.\n\n";

        // Add language instruction if language is specified
        if (!empty($data['language_name']) || !empty($data['language_code'])) {
            $language = $data['language_name'] ?? $data['language_code'];
            $prompt .= "\n\nIMPORTANT: Write the meta details in {$language} language. The entire meta details must be in {$language}.";
        }

        $prompt .= "Title: " . ($data['title'] ?? 'N/A') . "\n";

        if (!empty($data['location'])) {
            $prompt .= "Location: {$data['location']}\n";
        }
        if (!empty($data['city'])) {
            $prompt .= "City: {$data['city']}\n";
        }
        if (!empty($data['state'])) {
            $prompt .= "State: {$data['state']}\n";
        }
        if (!empty($data['country'])) {
            $prompt .= "Country: {$data['country']}\n";
        }
        if (!empty($data['price'])) {
            $prompt .= "Price: {$data['price']}\n";
        }

        $prompt .= "\nReturn ONLY a valid JSON object with this exact structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"meta_title\": \"...\",\n";
        $prompt .= "  \"meta_description\": \"...\",\n";
        $prompt .= "  \"meta_keywords\": \"...\"\n";
        $prompt .= "}\n\n";
        $prompt .= "Rules:\n";
        $prompt .= "- meta_title: 50-60 characters, include location and key feature\n";
        $prompt .= "- meta_description: 150-160 characters, compelling and clear\n";
        $prompt .= "- meta_keywords: 10-15 comma-separated keywords\n";
        $prompt .= "- Do NOT add any explanation, markdown, or extra text. JSON ONLY.";

        return $prompt;
    }

    /**
     * Extract structured response from Gemini function calling
     */
    private function extractStructuredResponse(array $data): ?array
    {
        try {
            $candidates = $data['candidates'] ?? [];
            if (empty($candidates)) {
                return null;
            }

            $functionCalls = $candidates[0]['content']['parts'][0]['functionCall'] ?? null;
            if ($functionCalls && isset($functionCalls['args'])) {
                return $functionCalls['args'];
            }

            // Try to find function response in parts
            foreach ($candidates[0]['content']['parts'] ?? [] as $part) {
                if (isset($part['functionResponse'])) {
                    $response = $part['functionResponse']['response'] ?? null;
                    if ($response) {
                        return is_string($response) ? json_decode($response, true) : $response;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to extract structured response: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse meta details from text response (fallback)
     */
    private function parseMetaFromText(string $content): array
    {
        $content = $this->cleanResponse($content);

        // Try to extract JSON if present
        if (preg_match('/\{[^}]+\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return [
                    'meta_title' => $json['meta_title'] ?? '',
                    'meta_description' => $json['meta_description'] ?? '',
                    'meta_keywords' => $json['meta_keywords'] ?? '',
                ];
            }
        }

        // Fallback: simple parsing
        $lines = explode("\n", $content);
        $meta = [
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
        ];

        foreach ($lines as $line) {
            if (stripos($line, 'title') !== false) {
                $meta['meta_title'] = trim(str_replace(['Title:', 'Meta Title:'], '', $line));
            } elseif (stripos($line, 'description') !== false) {
                $meta['meta_description'] = trim(str_replace(['Description:', 'Meta Description:'], '', $line));
            } elseif (stripos($line, 'keyword') !== false) {
                $meta['meta_keywords'] = trim(str_replace(['Keywords:', 'Meta Keywords:'], '', $line));
            }
        }

        return $meta;
    }

    /**
     * Clean AI response text
     */
    private function cleanResponse(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content, " \t\n\r\0\x0B\"'");
        return $content;
    }

    /**
     * Estimate token count (rough approximation: 1 token ≈ 4 characters)
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
