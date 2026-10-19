<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Advertisement;
use App\Models\AgentVerification;
use App\Models\AssignedOutdoorFacilities;
use App\Models\AssignParameters;
use App\Models\Category;
use App\Models\CityImage;
use App\Models\InterestedUser;
use App\Models\OutdoorFacilities;
use App\Models\parameter;
use App\Models\PaymentTransaction;
use App\Models\Projects;
use App\Models\PropertiesDocument;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Models\user_reports;
use App\Models\UserInterest;
use App\Models\VerifyCustomer;
use App\Rules\VideoUrlRule;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PropertyApiController extends Controller
{
    public function get_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'id' => 'nullable|integer',
            'slug_id' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'search' => 'nullable|string',
            'max_price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'property_type' => 'nullable|in:0,1,2,3',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'parameter_id' => 'nullable|integer',
            'users_promoted' => 'nullable|integer',
            'top_rated' => 'nullable|in:0,1',
            'most_liked' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
            $current_user = Auth::guard('sanctum')->user()->id;
        } else {
            $current_user = null;
        }
        $property = Property::with(['customer' => function ($query) {
            $query->withCount([
                'projects' => function ($query) {
                    $query->onlyActive();
                },
                'property' => function ($query) {
                    $query->onlyActive();
                },
            ]);
        }, 'user', 'category' => function ($categoryQuery) {
            $categoryQuery->select('id', 'category', 'image', 'slug_id')->with('translations');
        }, 'parameters', 'favourite', 'interested_users', 'translations'])->onlyActive();

        $max_price = isset($request->max_price) ? $request->max_price : Property::max('price');
        $min_price = isset($request->min_price) ? $request->min_price : 0;
        $totalClicks = 0;

        // If parameter ID passed
        if ($request->has('parameter_id') && ! empty($request->parameter_id)) {
            $parameterId = $request->parameter_id;
            $property = $property->whereHas('parameters', function ($q) use ($parameterId) {
                $q->where('parameter_id', $parameterId);
            });
        }

        // If Max Price And Min Price passed
        if (isset($request->max_price) && isset($request->min_price) && (! empty($request->max_price) && ! empty($min_price))) {
            $property = $property->whereBetween('price', [$min_price, $max_price]);
        }

        $property_type = $request->property_type;  // 0 : Sell 1:Rent
        // If Property Type Passed
        if (isset($property_type) && (! empty($property_type) || $property_type == 0)) {
            $property = $property->where('propery_type', $property_type);
        }

        // If Posted Since 0 or 1 is passed
        if ($request->has('posted_since') && $request->posted_since !== '') {
            $posted_since = $request->posted_since;
            // 0 - Last Week
            if ($posted_since == 0) {
                $startDateOfWeek = Carbon::now()->subWeek()->startOfWeek();
                $endDateOfWeek = Carbon::now()->subWeek()->endOfWeek();
                $property = $property->whereBetween('created_at', [$startDateOfWeek, $endDateOfWeek]);
            }
            // 1 - Yesterday
            if ($posted_since == 1) {
                $yesterdayDate = Carbon::yesterday();
                $property = $property->whereDate('created_at', $yesterdayDate);
            }
            // 2 - Last Month
            if ($posted_since == 2) {
                $property = $property->where('created_at', '>=', Carbon::now()->subMonth());
            }
            // 3 - Last 3 Months
            if ($posted_since == 3) {
                $property = $property->where('created_at', '>=', Carbon::now()->subMonths(3));
            }
            // 4 - Last 6 Months
            if ($posted_since == 4) {
                $property = $property->where('created_at', '>=', Carbon::now()->subMonths(6));
            }
        }

        // If Category Id is Passed
        if ($request->has('category_id') && ! empty($request->category_id)) {
            $property = $property->where('category_id', $request->category_id);
        }

        // If Id is passed
        if ($request->has('id') && ! empty($request->id)) {
            $property = $property->where('id', $request->id);
            if (! $request->has('with_seo') || ($request->has('with_seo') && $request->with_seo != 1)) {
                HelperService::incrementTotalClick('property', $request->id);
            }
        }

        if ($request->has('category_slug_id') && ! empty($request->category_slug_id)) {
            // Get the category date on category slug id
            $category = Category::where('slug_id', $request->category_slug_id)->first();
            // if category data exists then get property on the category id
            if (collect($category)->isNotEmpty()) {
                $property = $property->where('category_id', $category->id);
            }
        }

        // If Property Slug is passed
        if ($request->has('slug_id') && ! empty($request->slug_id)) {
            $property = $property->where('slug_id', $request->slug_id);
            if (! $request->has('with_seo') || ($request->has('with_seo') && $request->with_seo != 1)) {
                HelperService::incrementTotalClick('property', null, $request->slug_id);
            }
        }

        // If Country is passed
        if ($request->has('country') && ! empty($request->country)) {
            $property = $property->where('country', $request->country);
        }

        // If State is passed
        if ($request->has('state') && ! empty($request->state)) {
            $property = $property->where('state', $request->state);
        }

        // If City is passed
        if ($request->has('city') && ! empty($request->city)) {
            $property = $property->where('city', $request->city);
        }

        // If place ID is passed, resolve it to city name
        if ($request->has('place_id') && ! empty($request->place_id)) {
            $locationData = $this->resolvePlaceIdToLocation($request->place_id);
            if ($locationData) {
                if ($locationData['city']) {
                    $property = $property->where('city', $locationData['city']);
                }
                if ($locationData['state']) {
                    $property = $property->where('state', $locationData['state']);
                }
                if ($locationData['country']) {
                    $property = $property->where('country', $locationData['country']);
                }
            }
        }

        // If promoted is passed then get the properties according to advertisement's data except the advertisement's slider data
        if ($request->has('promoted') && ! empty($request->promoted)) {
            $propertiesId = Advertisement::whereNot('type', 'Slider')->where('is_enable', 1)->pluck('property_id');
            $property = $property->whereIn('id', $propertiesId)->inRandomOrder();
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        // IF User Promoted Param Passed then show the User's Advertised data
        if ($request->has('users_promoted') && ! empty($request->users_promoted)) {
            $propertiesId = Advertisement::where('customer_id', $current_user)->where('role_context', $request->user_active_role)->pluck('property_id');
            $property = $property->whereIn('id', $propertiesId);
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $property = $property->where(function ($query) use ($search) {
                $query->where('title', 'LIKE', "%$search%")
                    ->orWhere('address', 'LIKE', "%$search%")
                    ->orWhereHas('category', function ($query1) use ($search) {
                        $query1->where('category', 'LIKE', "%$search%");
                    });
            });
        }

        // If Top Rated passed then show the property data with Order by on Total Click Descending
        if ($request->has('top_rated') && $request->top_rated == 1) {
            $property = $property->orderBy('total_click', 'DESC');
        }

        // IF Most Liked Passed then show the data according to
        if ($request->has('most_liked') && ! empty($request->most_liked)) {
            $property = $property->withCount('favourite')->orderBy('favourite_count', 'DESC');
        }

        $total = $property->count();
        $result = $property->orderBy('id', 'DESC')->skip($offset)->take($limit)->get()->map(function ($item) {
            if ($item->category) {
                $item->category->translated_name = $item->category->translated_name;
            }

            return $item;
        });

        if (! $result->isEmpty()) {
            $property_details = get_property_details($result, $current_user, true);

            // Check that Property Details exists or not
            if (isset($property_details) && collect($property_details)->isNotEmpty()) {

                foreach ($property_details as $key => $property) {

                    $customerId = $property['customer']['id'] ?? null;

                    // if ($customerId) {
                    //     $meta = HelperService::getCustomerMeta($customerId);

                    //     $property_details[$key]['is_agent'] = $meta['is_agent'] ?? false;
                    //     $property_details[$key]['is_agent_verified'] = $meta['is_agent_verified'] ?? false;
                    //     $property_details[$key]['is_user_verified'] = $meta['is_user_verified'] ?? false;
                    //     $property_details[$key]['agent_verification_status'] = $meta['agent_verification_status'] ?? 'not_applied';
                    //     $property_details[$key]['become_agent_status'] = $meta['become_agent_status'] ?? 'not_applied';
                    //     $property_details[$key]['user_verification_status'] = $meta['user_verification_status'] ?? 'not_applied';
                    // }
                }
                /**
                 * Check that id or slug id passed and get the similar properties data according to param passed
                 * If both passed then priority given to id param
                 * */
                $propertyAddedAs = $property_details[0]['role_context'] ?? 'user';
                $categoryId = $property_details[0]['category']['id'] ?? null;
                $similarPropertyQuery = Property::onlyActive()->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'role_context', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image', 'request_status', 'is_premium')->when($categoryId, function ($q) use ($categoryId) {
                    return $q->where('category_id', $categoryId);
                })->where('role_context', $propertyAddedAs)->inRandomOrder()->with(['category.translations', 'translations', 'customer' => function ($query) {
                    $query->withCount([
                        'projects' => function ($query) {
                            $query->onlyActive();
                        },
                        'property' => function ($query) {
                            $query->onlyActive();
                        },
                    ]);
                }])->limit(10);
                if ((isset($id) && ! empty($id))) {
                    $getSimilarPropertiesQueryData = $similarPropertyQuery->where('id', '!=', $id)->get()->map(function ($item) {
                        if ($item->category) {
                            $item->category->translated_name = $item->category->translated_name;
                        }

                        return $item;
                    });
                    $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $current_user);
                } elseif ((isset($request->slug_id) && ! empty($request->slug_id))) {
                    $getSimilarPropertiesQueryData = $similarPropertyQuery->where('slug_id', '!=', $request->slug_id)->get()->map(function ($item) {
                        if ($item->category) {
                            $item->category->translated_name = $item->category->translated_name;
                        }

                        return $item;
                    });
                    $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $current_user, true);
                }

                if (! empty($getSimilarProperties)) {
                    foreach ($getSimilarProperties as $key => $property) {

                        $customerId = $property['customer']['id'] ?? null;

                        if ($customerId) {
                            $meta = HelperService::getCustomerMeta($customerId);

                            $getSimilarProperties[$key]['is_agent'] = $meta['is_agent'] ?? false;
                            $getSimilarProperties[$key]['is_agent_verified'] = $meta['is_agent_verified'] ?? false;
                            $getSimilarProperties[$key]['is_user_verified'] = $meta['is_user_verified'] ?? false;
                            $getSimilarProperties[$key]['agent_verification_status'] = $meta['agent_verification_status'] ?? 'not_applied';
                            $getSimilarProperties[$key]['become_agent_status'] = $meta['become_agent_status'] ?? 'not_applied';
                            $getSimilarProperties[$key]['user_verification_status'] = $meta['user_verification_status'] ?? 'not_applied';
                        }
                    }
                }
            }

            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['similar_properties'] = $getSimilarProperties ?? [];
            $response['total'] = $total;
            $response['data'] = $property_details;
        } else {

            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return $response;
    }

    public function getPropertyList(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|numeric',
                'limit' => 'nullable|numeric',
                'get_all_premium_properties' => 'nullable|in:1',
                'filters' => 'nullable|string',
                'ai_search_prompt' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            // First decode filters from base64
            // $filters = $request->filters;

            // First decode filters from base64
            $filters = $request->filters;
            if (! empty($filters)) {
                $filters = base64_decode($filters);
                if (json_validate($filters)) {
                    $filters = json_decode($filters, true);
                } else {
                    $filters = [];
                }
            } else {
                $filters = [];
            }

            // Normalize EMPTY filters properly
            // $filters = [
            //     'property_type' => isValid($filters['property_type'] ?? null) ? $filters['property_type'] : null,
            //     'category_id' => isValid($filters['category_id'] ?? null) ? $filters['category_id'] : null,
            //     'search' => isValid($filters['search'] ?? null) ? $filters['search'] : null,

            //     'posted_since' => isset($filters['posted_since']) && $filters['posted_since'] !== ''
            //                         ? $filters['posted_since']
            //                         : null,

            //     'price' => [
            //         'min_price' => (isset($filters['price']['min_price']) && $filters['price']['min_price'] > 0)
            //                         ? $filters['price']['min_price']
            //                         : null,

            //         'max_price' => (isset($filters['price']['max_price']) && $filters['price']['max_price'] > 0)
            //                         ? $filters['price']['max_price']
            //                         : null,
            //     ],

            //     'location' => [
            //         'city' => isValid($filters['location']['city'] ?? null) ? $filters['location']['city'] : null,
            //     ],
            // ];

            $isAiEnabled = 0;
            // // Check if AI search is enabled in settings and API key exists
            // $isAiEnabled = HelperService::getSettingData('gemini_ai_search') ? 1 : 0;
            // // Extract AI search prompt from filters (if present)
            // $aiExtractedFilters = [];
            $search = $filters['search'] ?? null;
            // if (!empty($search)) {
            //     $aiSearchPrompt = $search;
            //     $geminiApiKey = config('services.gemini.api_key');
            //     if($isAiEnabled == 1 && !empty($geminiApiKey)){
            //         try {
            //             // Get available categories, facilities, and nearby places for AI processing
            //             $categories = Category::select('id', 'category as name')->with('translations')->get()->map(function($category) {
            //                 return [
            //                     'id' => $category->id,
            //                     'name' => $category->name,
            //                     'translated_name' => $category->translated_name,
            //                     'translations' => $category->translations->map(function($translation){
            //                         return [
            //                             'language_id' => $translation->language_id,
            //                             'value' => $translation->value,
            //                         ];
            //                     }),
            //                 ];
            //             })->toArray();
            //             $facilities = parameter::select('id', 'name', 'type_of_parameter', 'type_values')->with('translations')->get()->map(function($facility) {
            //                 return [
            //                     'id' => $facility->id,
            //                     'name' => $facility->name,
            //                     'type_of_parameter' => $facility->type_of_parameter,
            //                     'values' => $facility->type_values,
            //                     'translated_option_value' => $facility->translated_option_value,
            //                     'translated_name' => $facility->translated_name,
            //                     'translations' => $facility->translations->map(function($translation){
            //                         return [
            //                             'language_id' => $translation->language_id,
            //                             'value' => $translation->value,
            //                         ];
            //                     }),
            //                 ];
            //             })->toArray();
            //             $nearbyPlaces = OutdoorFacilities::select('id', 'name')->with('translations')->get()->map(function($nearbyPlace) {
            //                 return [
            //                     'id' => $nearbyPlace->id,
            //                     'name' => $nearbyPlace->name,
            //                     'translated_name' => $nearbyPlace->translated_name,
            //                     'translations' => $nearbyPlace->translations->map(function($translation){
            //                         return [
            //                             'language_id' => $translation->language_id,
            //                             'value' => $translation->value,
            //                         ];
            //                     }),
            //                 ];
            //             })->toArray();

            //             // Use GeminiService to extract search parameters
            //             $geminiService = new GeminiService();
            //             $aiResult = $geminiService->extractSearchParameters($aiSearchPrompt, $categories, $nearbyPlaces, $facilities);
            //             if ($aiResult['success'] && !empty($aiResult['data'])) {
            //                 $aiExtractedFilters = $aiResult['data'];

            //                 // Convert AI extracted parameters to match existing filter structure
            //                 if (!empty($aiExtractedFilters['nearbyplace'])) {
            //                     $aiExtractedFilters['nearby_places'] = $aiExtractedFilters['nearbyplace'];
            //                     unset($aiExtractedFilters['nearbyplace']);
            //                 }

            //                 if (!empty($aiExtractedFilters['facilities'])) {
            //                     $aiExtractedFilters['parameters'] = $aiExtractedFilters['facilities'];
            //                     unset($aiExtractedFilters['facilities']);
            //                 }
            //             }
            //         } catch (\Exception $e) {
            //             Log::error('AI Search Error: ' . $e->getMessage());
            //         }
            //     }
            // }

            // // Merge AI-extracted filters with existing filters
            // // AI filters take precedence over existing filters for the same keys
            // if (!empty($aiExtractedFilters)) {
            //     $filters = array_merge($filters, $aiExtractedFilters);
            // }

            $filterValidator = Validator::make(
                collect($filters)->toArray(),
                [
                    'property_type' => 'nullable|in:0,1',
                    'category_id' => 'nullable|exists:categories,id',
                    'category_slug_id' => 'nullable|exists:categories,slug_id',
                    'location.country' => 'nullable',
                    'location.state' => 'nullable',
                    'location.city' => 'nullable',
                    'location.place_id' => 'nullable',
                    'location.latitude' => 'nullable',
                    'location.longitude' => 'nullable',
                    'location.range' => 'nullable',
                    'price.min_price' => 'nullable|numeric',
                    'price.max_price' => 'nullable|numeric',
                    'posted_since' => 'nullable|in:0,1,2,3,4',
                    'flags.promoted' => 'nullable',
                    'flags.most_viewed' => 'nullable',
                    'flags.most_liked' => 'nullable',
                    'flags.get_all_premium_properties' => 'nullable',
                    'parameters' => 'nullable|array',
                    'parameters.*.id' => 'nullable|exists:parameters,id',
                    'parameters.*.value' => 'nullable',
                    'nearby_places' => 'nullable|array',
                    'nearby_places.*.id' => 'nullable|exists:outdoor_facilities,id',
                    'nearby_places.*.value' => 'nullable|integer',
                    'role_context' => 'nullable|in:user,agent',
                ],
                [
                    'property_type.in' => trans('Property type is not valid'),
                    'category_id.exists' => trans('Category id is not valid'),
                    'category_slug_id.exists' => trans('Category slug id is not valid'),
                    'location.country.exists' => trans('Country id is not valid'),
                    'location.state.exists' => trans('State id is not valid'),
                    'location.city.exists' => trans('City id is not valid'),
                    'location.place_id.string' => trans('Place id is not valid'),
                    'price.min_price.numeric' => trans('Min price is not valid'),
                    'price.max_price.numeric' => trans('Max price is not valid'),
                    'posted_since.in' => trans('Posted since is not valid'),
                    'parameters.array' => trans('Parameters is not valid'),
                    'parameters.*.id.exists' => trans('Parameter id is not valid'),
                    'nearby_places.array' => trans('Nearby place is not valid'),
                    'nearby_places.*.id.exists' => trans('Nearby place id is not valid'),
                    'nearby_places.*.value.integer' => trans('Nearby place value is not valid'),
                ]
            );
            if ($filterValidator->fails()) {
                ApiResponseService::validationError($filterValidator->errors()->first());
            }

            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Get Filters Variables
            $propertyType = isset($filters['property_type']) ? $filters['property_type'] : null;
            $categoryId = isset($filters['category_id']) ? $filters['category_id'] : null;
            $categorySlugId = isset($filters['category_slug_id']) ? $filters['category_slug_id'] : null;
            $country = isset($filters['location']['country']) ? $filters['location']['country'] : null;
            $state = isset($filters['location']['state']) ? $filters['location']['state'] : null;
            $city = isset($filters['location']['city']) ? $filters['location']['city'] : null;
            $placeId = isset($filters['location']['place_id']) ? $filters['location']['place_id'] : null;
            $latitude = isset($filters['location']['latitude']) ? $filters['location']['latitude'] : null;
            $longitude = isset($filters['location']['longitude']) ? $filters['location']['longitude'] : null;
            $minPrice = isset($filters['price']['min_price']) ? $filters['price']['min_price'] : null;
            $maxPrice = isset($filters['price']['max_price']) ? $filters['price']['max_price'] : null;
            $postedSince = $filters['posted_since'] ?? null;
            $range = isset($filters['location']['range']) ? $filters['location']['range'] : null;
            $promoted = isset($filters['flags']['promoted']) ? $filters['flags']['promoted'] : null;
            $getPremiumProperties = isset($filters['flags']['get_all_premium_properties']) ? $filters['flags']['get_all_premium_properties'] : null;
            $mostViewed = isset($filters['flags']['most_views']) ? $filters['flags']['most_views'] : null;
            $mostLiked = isset($filters['flags']['most_liked']) ? $filters['flags']['most_liked'] : null;
            $parameters = isset($filters['parameters']) ? $filters['parameters'] : null;
            $nearbyPlaces = isset($filters['nearby_places']) ? $filters['nearby_places'] : null;
            $title = isset($filters['title']) ? $filters['title'] : null;
            $addedAs = isset($filters['role_context']) ? $filters['role_context'] : null;

            // Create a property query
            $propertyQuery = Property::whereIn('propery_type', [0, 1])->where(function ($query) {
                return $query->onlyActive();
            })->when($addedAs, function ($query) use ($addedAs) {
                return $query->where('role_context', $addedAs);
            });

            // If Property Type Passed
            if (isset($propertyType) && (! empty($propertyType) || $propertyType == 0)) {
                $propertyQuery = $propertyQuery->where('propery_type', $propertyType);
            }

            // If Category Id is Passed
            if (isset($categoryId) && ! empty($categoryId)) {
                $propertyQuery = $propertyQuery->where('category_id', $categoryId);
            }

            // If Status is passed (0/1), allow filtering on status
            if ($isAiEnabled == 0 && isset($filters['search']) && $filters['search'] !== '') {
                $propertyQuery = $propertyQuery->where(function ($whereCondition) use ($search) {
                    $whereCondition->where('title', 'like', '%'.$search.'%')
                        ->orWhere('address', 'like', '%'.$search.'%')
                        ->orWhereHas('category', function ($query) use ($search) {
                            $query->where('category', 'like', '%'.$search.'%');
                        })
                        // Use the global scope to search in translations with language filtering
                        ->orWhere(function ($query) use ($search) {
                            $query->searchInAnyTranslation($search);
                        });
                });
            }

            // If parameter id passed
            if (isset($parameters) && ! empty($parameters)) {
                foreach ($parameters as $parameter) {
                    $parameterId = $parameter['id'];
                    $propertyQuery = $propertyQuery->whereHas('assignParameter', function ($query) use ($parameterId) {
                        $query->where('parameter_id', $parameterId)
                            ->where(function ($q) {
                                $q->whereNotNull('value')
                                    ->orWhere('value', '!=', '')
                                    ->orWhere('value', '!=', 'null');
                            });
                    });

                    // if((isset($parameter['value']) && !empty($parameter['value']) || (isset($parameter['values']) && !empty($parameter['values'])))){
                    //     $parameterValue = explode(",",$parameter['value'] ?? $parameter['values']);
                    //     if(!empty($parameterValue)){
                    //         $propertyQuery = $propertyQuery->whereHas('assignParameter',function($query) use($parameterId,$parameterValue){
                    //             $query->where('parameter_id',$parameterId)->whereIn('value',$parameterValue);
                    //         });
                    //     }
                    // }
                }
            }

            if (isset($nearbyPlaces) && ! empty($nearbyPlaces)) {
                foreach ($nearbyPlaces as $nearbyPlace) {
                    $nearbyPlaceId = $nearbyPlace['id'];
                    $nearbyPlaceValue = $nearbyPlace['value'];
                    if (isset($nearbyPlace['value']) && ! empty($nearbyPlace['value'])) {
                        $propertyQuery = $propertyQuery->whereHas('assignfacilities', function ($query) use ($nearbyPlaceId, $nearbyPlaceValue) {
                            $query->where('facility_id', $nearbyPlaceId)->where('distance', '<=', $nearbyPlaceValue);
                        });
                    } else {
                        $propertyQuery = $propertyQuery->whereHas('assignfacilities', function ($query) use ($nearbyPlaceId) {
                            $query->where('facility_id', $nearbyPlaceId);
                        });
                    }
                }
            }

            // If Title is passed
            if (isset($title) && ! empty($title)) {
                $propertyQuery = $propertyQuery->where('title', 'like', '%'.$title.'%');
            }

            // If Category Slug is Passed
            if (isset($categorySlugId) && ! empty($categorySlugId)) {
                $propertyQuery = $propertyQuery->whereHas('category', function ($query) use ($categorySlugId) {
                    $query->where('slug_id', $categorySlugId);
                });
            }

            // If Country is passed
            if (isset($country) && ! empty($country)) {
                $propertyQuery = $propertyQuery->where('country', 'like', '%'.$country.'%');
            }

            // If State is passed
            if (isset($state) && ! empty($state)) {
                $propertyQuery = $propertyQuery->where('state', 'like', '%'.$state.'%');
            }

            // If City is passed
            if (isset($city) && ! empty($city)) {
                $propertyQuery = $propertyQuery->where('city', 'like', '%'.$city.'%');
            }

            // If place ID is passed, resolve it to city name
            if (isset($placeId) && ! empty($placeId)) {
                $locationData = $this->resolvePlaceIdToLocation($placeId);
                if ($locationData) {
                    if ($locationData['city']) {
                        $propertyQuery = $propertyQuery->where('city', $locationData['city']);
                    }
                    if ($locationData['state']) {
                        $propertyQuery = $propertyQuery->where('state', $locationData['state']);
                    }
                    if ($locationData['country']) {
                        $propertyQuery = $propertyQuery->where('country', $locationData['country']);
                    }
                }
            }

            // If Max Price And Min Price passed
            if (isset($minPrice) && ! empty($minPrice)) {
                $propertyQuery = $propertyQuery->where('price', '>=', $minPrice);
            }

            if (isset($maxPrice) && ! empty($maxPrice)) {
                $propertyQuery = $propertyQuery->where('price', '<=', $maxPrice);
            }

            // If Posted Since is passed (can be 0)
            // if (isset($postedSince) && $postedSince !== '') {
            //     // 0 - Last Week (from today back to the same day last week)
            //     if ($postedSince == 0) {
            //         $oneWeekAgo = Carbon::now()->subWeek()->startOfDay();
            //         $today = Carbon::now()->endOfDay();
            //         $propertyQuery = $propertyQuery->whereBetween('created_at', [$oneWeekAgo, $today]);
            //     }
            //     // 1 - Yesterday
            //     if ($postedSince == 1) {
            //         $yesterdayDate = Carbon::yesterday();
            //         $propertyQuery = $propertyQuery->whereDate('created_at', $yesterdayDate);
            //     }

            //     // 2 - Last Month
            //     if ($postedSince == 2) {
            //         $lastMonthDate = Carbon::now()->subMonth();
            //         $today = Carbon::now()->endOfDay();
            //         $propertyQuery = $propertyQuery->whereBetween('created_at', [$lastMonthDate, $today]);
            //     }

            //     // 3 - Last 3 Months
            //     if ($postedSince == 3) {
            //         $lastThreeMonthsDate = Carbon::now()->subMonths(3);
            //         $today = Carbon::now()->endOfDay();
            //         $propertyQuery = $propertyQuery->whereBetween('created_at', [$lastThreeMonthsDate, $today]);
            //     }

            //     // 4 - Last 6 Months
            //     if ($postedSince == 4) {
            //         $lastSixMonthsDate = Carbon::now()->subMonths(6);
            //         $today = Carbon::now()->endOfDay();
            //         $propertyQuery = $propertyQuery->whereBetween('created_at', [$lastSixMonthsDate, $today]);
            //     }
            // }

            if (isset($postedSince) && $postedSince !== '') {

                $now = Carbon::now();

                switch ((int) $postedSince) {

                    case 0: // Last 7 days
                        $propertyQuery->where('created_at', '>=', $now->copy()->subDays(7));
                        break;

                    case 1: // Yesterday
                        $propertyQuery->whereDate('created_at', $now->copy()->subDay());
                        break;

                    case 2: // Last 1 month
                        $propertyQuery->where('created_at', '>=', $now->copy()->subMonth());
                        break;

                    case 3: // Last 3 months
                        $propertyQuery->where('created_at', '>=', $now->copy()->subMonths(3));
                        break;

                    case 4: // Last 6 months
                        $propertyQuery->where('created_at', '>=', $now->copy()->subMonths(6));
                        break;
                }
            }
            // IF Promoted Passed then show the data according to
            if (isset($promoted) && ! empty($promoted) && $promoted == 1) {
                $propertyQuery = $propertyQuery->whereHas('advertisement', function ($query) {
                    $query->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // If get_all_premium_properties is passed then show the data according to
            if (isset($getPremiumProperties) && ! empty($getPremiumProperties) && $getPremiumProperties == 1) {
                $propertyQuery = $propertyQuery->where('is_premium', 1);
            }

            // Add promoted_count and favourite_count for ordering
            $propertyQuery = $propertyQuery->withCount([
                'advertisement as promoted_count' => function ($query) {
                    $query->where('status', 0)
                        ->where('is_enable', 1)
                        ->where('for', 'property')
                        ->groupBy('property_id');
                },
            ])
                ->withCount('favourite');

            // Always group promoted properties first
            $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END');

            // Randomize promoted properties, order non-promoted by id descending
            // Using a large number minus id for non-promoted to achieve DESC order in ASC context
            if (isset($mostViewed) && ! empty($mostViewed) && $mostViewed == 1) {
                // For most viewed: randomize promoted, order non-promoted by total_click DESC
                $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - total_click) END');
            }
            // If Most Liked Passed then show the property data with promoted-first and Favourite Count Descending
            elseif (isset($mostLiked) && ! empty($mostLiked) && $mostLiked == 1) {
                // For most liked: randomize promoted, order non-promoted by favourite_count DESC
                $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - favourite_count) END');
            } else {
                // Default: randomize promoted, order non-promoted by id DESC
                $propertyQuery = $propertyQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - id) END');
            }

            // Latitude and Longitude
            if (isset($latitude) && ! empty($latitude) && isset($longitude) && ! empty($longitude) && $latitude != 'null' && $longitude != 'null') {
                if (isset($range) && ! empty($range) && $range != 'null') {
                    // Get the distance from the latitude and longitude
                    $propertyQuery = $propertyQuery->selectRaw("
                            (6371 * acos(cos(radians($latitude))
                            * cos(radians(latitude))
                            * cos(radians(longitude) - radians($longitude))
                            + sin(radians($latitude))
                            * sin(radians(latitude)))) AS distance")
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->having('distance', '<', $range);
                } else {
                    $propertyQuery = $propertyQuery->where('latitude', $latitude)->where('longitude', $longitude);
                }
            }

            // Get total properties
            $totalProperties = $propertyQuery->clone()->count();

            // Get properties list data
            $propertiesData = $propertyQuery
                ->with('category:id,category,image,slug_id', 'category.translations', 'translations')
                ->addSelect('id', 'slug_id', 'propery_type', 'title_image', 'category_id', 'title', 'price', 'city', 'state', 'country', 'rentduration', 'added_by', 'is_premium', 'latitude', 'longitude', 'total_click')
                ->withCount('favourite')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($property) {
                    $property->promoted = $property->is_promoted;
                    $property->is_premium = $property->is_premium == 1 ? true : false;
                    $property->property_type = $property->propery_type;
                    $property->assign_facilities = $property->assign_facilities;
                    $property->parameters = $property->parameters;
                    if ($property->category) {
                        $property->category->translated_name = $property->category->translated_name;
                    }
                    $property->translated_title = $property->translated_title;
                    $property->translated_description = $property->translated_description;
                    unset($property->propery_type);

                    return $property;
                });

            $response = [
                'error' => false,
                'total' => $totalProperties,
                'data' => $propertiesData,
                'message' => trans('Data Fetched Successfully'),
            ];

            return response()->json($response);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function post_property(Request $request)
    {

        $commonRules = [
            'latitude' => 'required',
            'longitude' => 'required',
            'rentduration' => 'required_if:property_type,==,1',
            'meta_title' => 'nullable|max:255',
            'meta_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description' => 'nullable|max:255',
            'meta_keywords' => 'nullable|max:255',
            'price' => ['required', 'numeric', 'min:1', 'max:9223372036854775807', function ($attribute, $value, $fail) {
                if ($value >= 9223372036854775807) {
                    $fail('The Price must not exceed more than 9223372036854775807.');
                }
            }],
            'video_type' => 'nullable|in:0,1,2',
            'video_link' => [
                'nullable',
                'required_if:video_type,1,2',

                function ($attribute, $value, $fail) use ($request) {

                    // Only validate URL for YouTube / Vimeo
                    if (in_array($request->video_type, [1, 2])) {

                        if (empty($value)) {
                            $fail('Video URL is required.');

                            return;
                        }

                        // YouTube validation
                        if ($request->video_type == 1) {
                            $pattern = '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/';

                            if (! preg_match($pattern, $value)) {
                                $fail('Invalid YouTube URL.');
                            }
                        }

                        // Vimeo validation
                        if ($request->video_type == 2) {
                            $pattern = '/^(https?:\/\/)?(www\.)?(vimeo\.com)\/.+$/';

                            if (! preg_match($pattern, $value)) {
                                $fail('Invalid Vimeo URL.');
                            }
                        }
                    }
                },
            ],
            'custom_video' => 'nullable|file|mimes:mp4,webm,ogg|max:20480|required_if:video_type,0',
        ];

        if ($request->has('id') && ! empty($request->id)) {
            // Rules for update (graduating)
            $rules = array_merge([
                'title' => 'required',
            ], $commonRules);
        } else {
            // Rules for creation
            $rules = array_merge([
                'title' => 'required',
                'description' => 'required',
                'category_id' => 'required',
                'property_type' => 'required',
                'address' => 'required',
                'title_image' => 'required|file|max:3000|mimes:jpeg,png,jpg,webp',
                'three_d_image' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:3000',
                'documents.*' => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
            ], $commonRules);
        }

        $validator = Validator::make($request->all(), $rules, [
            'documents.*' => 'document :position',
            'meta_title.max' => trans('The Meta Title must not exceed more than 255 characters.'),
            'meta_image.image' => trans('The Meta Image must be an image.'),
            'meta_image.mimes' => trans('The Meta Image must be a JPG, PNG, or JPEG file.'),
            'meta_image.max' => trans('The Meta Image must not exceed more than 5MB.'),
            'meta_description.max' => trans('The Meta Description must not exceed more than 255 characters.'),
            'meta_keywords.max' => trans('The Meta Keywords must not exceed more than 255 characters.'),
            'custom_video.max' => 'The custom video must not be greater than 20MB.',
            'custom_video.*.max' => 'The custom video must not be greater than 20MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $alertNewPropertyNotification = false;
            $isDraft = false;
            $isPayAsYouGo = false;

            if ($request->has('id') && ! empty($request->id)) {
                $saveProperty = Property::where('added_by', $loggedInUserId)
                    ->find($request->id);

                if (! $saveProperty) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Property not found',
                    ], 404);
                }

                $wasDraft = ($saveProperty->getRawOriginal('request_status') === 'draft');

                if ($wasDraft) {
                    $graduation = $this->graduateDraft($saveProperty, $request->user_active_role);
                    if ($graduation['success']) {
                        $isPayAsYouGo = $graduation['is_pay_as_you_go'];
                        $autoApproveStatus = $graduation['auto_approve'];
                        $alertNewPropertyNotification = ($autoApproveStatus == true);
                    } else {
                        // Stay as draft
                    }
                } else {
                    $autoApproveStatus = HelperService::getAutoApproveStatus($loggedInUserId, $request->user_active_role);
                }
            } else {
                // Check if limit is available without failing automatically
                $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), true, true, $request->user_active_role);
                if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
                    $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), false, true);
                    $isPayAsYouGo = ($limitResult === 'pay_as_you_go');
                } else {
                    $isDraft = true;
                }

                $saveProperty = new Property;
                $saveProperty->added_by = $loggedInUserId;

                if ($isDraft) {
                    $saveProperty->request_status = 'draft';
                    $saveProperty->status = 0;
                    $autoApproveStatus = false;
                } else {
                    $autoApproveStatus = HelperService::getAutoApproveStatus($loggedInUserId, $request->user_active_role);
                    if ($autoApproveStatus) {
                        $saveProperty->request_status = 'approved';
                        $alertNewPropertyNotification = true;
                        // if ($isPayAsYouGo) {
                        // $saveProperty->expiry_date = Carbon::now()->addDays(30);
                        // }
                    } else {
                        $saveProperty->request_status = 'pending';
                    }
                    $saveProperty->status = 1;
                }

                if ($autoApproveStatus) {
                    if ($isPayAsYouGo) {
                        $saveProperty->expiry_date = Carbon::now()->addDays(30);
                    } elseif (! $isDraft) {
                        $saveProperty->expiry_date = HelperService::calculateExpirationDate($loggedInUserId);
                    }
                }
            }

            if ($request->category_id) {
                $saveProperty->category_id = $request->category_id;
            }
            if ($request->title) {
                $saveProperty->title = $request->title;
                $slugData = (isset($request->slug_id) && ! empty($request->slug_id)) ? $request->slug_id : $request->title;
                $saveProperty->slug_id = generateUniqueSlug($slugData, 1, null, $saveProperty->id ?? null);
            }
            if ($request->description) {
                $saveProperty->description = $request->description;
            }
            if ($request->address) {
                $saveProperty->address = $request->address;
            }
            if ($request->has('client_address')) {
                $saveProperty->client_address = $request->client_address;
            }
            if ($request->property_type) {
                $saveProperty->propery_type = $request->property_type;
            }
            if ($request->price) {
                $saveProperty->price = $request->price;
            }
            if ($request->country) {
                $saveProperty->country = $request->country;
            }
            if ($request->state) {
                $saveProperty->state = $request->state;
            }
            if ($request->city) {
                $saveProperty->city = $request->city;
            }
            if ($request->latitude) {
                $saveProperty->latitude = $request->latitude;
            }
            if ($request->longitude) {
                $saveProperty->longitude = $request->longitude;
            }
            if ($request->rentduration) {
                $saveProperty->rentduration = $request->rentduration;
            }

            $videoType = $request->video_type;
            $videoLink = $request->video_link;
            $directUploadEnabled = HelperService::getSettingData('show_direct_video_upload');

            if ($videoType !== null) {
                if ((int) $videoType === Property::VIDEO_CUSTOM && (int) $directUploadEnabled !== 1) {
                    return response()->json(['error' => true, 'message' => 'Direct video upload is currently disabled by admin.']);
                }
                $saveProperty->video_type = $videoType;
                if ((int) $videoType === Property::VIDEO_CUSTOM && $request->hasFile('custom_video')) {
                    $path = config('global.PROPERTY_VIDEO_PATH');
                    if ($saveProperty->id && $saveProperty->getRawOriginal('video_link')) {
                        $saveProperty->video_link = FileService::compressAndReplace($request->file('custom_video'), $path, $saveProperty->getRawOriginal('video_link'));
                    } else {
                        $saveProperty->video_link = FileService::compressAndUpload($request->file('custom_video'), $path);
                    }
                } else {
                    $saveProperty->video_link = $videoLink;
                }
            }

            $saveProperty->package_id = $request->package_id;
            $saveProperty->post_type = 1;

            if ($request->has('meta_title')) {
                $saveProperty->meta_title = $request->meta_title;
            }
            if ($request->has('meta_description')) {
                $saveProperty->meta_description = $request->meta_description;
            }
            if ($request->has('meta_keywords')) {
                $saveProperty->meta_keywords = $request->meta_keywords;
            }

            // Title Image
            if ($request->hasFile('title_image')) {
                $path = config('global.PROPERTY_TITLE_IMG_PATH');
                if ($saveProperty->id && $saveProperty->getRawOriginal('title_image')) {
                    $saveProperty->title_image = FileService::compressAndReplace($request->file('title_image'), $path, $saveProperty->getRawOriginal('title_image'), true);
                } else {
                    $saveProperty->title_image = FileService::compressAndUpload($request->file('title_image'), $path, true);
                }
            }

            // Meta Image
            if ($request->hasFile('meta_image')) {
                $path = config('global.PROPERTY_SEO_IMG_PATH');
                if ($saveProperty->id && $saveProperty->getRawOriginal('meta_image')) {
                    $saveProperty->meta_image = FileService::compressAndReplace($request->file('meta_image'), $path, $saveProperty->getRawOriginal('meta_image'));
                } else {
                    $saveProperty->meta_image = FileService::compressAndUpload($request->file('meta_image'), $path);
                }
            }

            // three_d_image
            if ($request->hasFile('three_d_image')) {
                $path = config('global.3D_IMG_PATH');
                if ($saveProperty->id && $saveProperty->getRawOriginal('three_d_image')) {
                    $saveProperty->three_d_image = FileService::compressAndReplace($request->file('three_d_image'), $path, $saveProperty->getRawOriginal('three_d_image'));
                } else {
                    $saveProperty->three_d_image = FileService::compressAndUpload($request->file('three_d_image'), $path);
                }
            }

            $showPremiumToggle = system_setting('show_premium_toggle');
            if ($showPremiumToggle == 1) {
                if ($request->has('is_premium')) {
                    $saveProperty->is_premium = $request->is_premium;
                }
            } else {
                $saveProperty->is_premium = 0;
            }
            $saveProperty->save();

            // Link payment transaction to property (pay-as-you-go only)
            if ($isPayAsYouGo && HelperService::$lastConsumedPaymentTransactionId) {
                PaymentTransaction::where('id', HelperService::$lastConsumedPaymentTransactionId)
                    ->update(['property_id' => $saveProperty->id]);
            }

            if ($request->facilities) {
                foreach ($request->facilities as $key => $value) {
                    if (isset($value['facility_id']) && ! empty($value['facility_id']) && isset($value['distance']) && ! empty($value['distance'])) {
                        $facilities = new AssignedOutdoorFacilities;
                        $facilities->facility_id = $value['facility_id'];
                        $facilities->property_id = $saveProperty->id;
                        $facilities->distance = $value['distance'];
                        $facilities->save();
                    }
                }
            }
            if ($request->parameters) {
                foreach ($request->parameters as $key => $parameter) {
                    if (isset($parameter['value']) && $parameter['value'] !== null) {
                        $AssignParameters = new AssignParameters;
                        $AssignParameters->modal()->associate($saveProperty);
                        $AssignParameters->parameter_id = $parameter['parameter_id'];
                        if ($request->hasFile('parameters.'.$key.'.value')) {
                            $profile = $request->file('parameters.'.$key.'.value');
                            // Validate that the uploaded file is an image or a supported document type
                            $allowedMimeTypes = [
                                'image/jpeg',
                                'image/png',
                                'image/jpg',
                                'image/gif',
                                'image/webp',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'text/plain',
                            ];
                            $mimeType = $profile->getMimeType();
                            if (! in_array($mimeType, $allowedMimeTypes)) {
                                return ResponseService::validationError(trans('The parameter file must be an image (jpeg, png, jpg, gif, webp) or a document (pdf, doc, docx, txt).'));
                            }
                            $path = config('global.PARAMETER_IMG_PATH');
                            $AssignParameters->value = FileService::compressAndUpload($profile, $path);
                        } else {
                            $AssignParameters->value = $parameter['value'];
                        }
                        $AssignParameters->save();
                    }
                }
            }

            // / START :: UPLOAD GALLERY IMAGE
            if ($request->hasfile('gallery_images')) {
                $path = config('global.PROPERTY_GALLERY_IMG_PATH').$saveProperty->id.'/';
                $gallaryImageData = [];
                foreach ($request->file('gallery_images') as $file) {
                    $gallaryImageData[] = [
                        'propertys_id' => $saveProperty->id,
                        'image' => FileService::compressAndUpload($file, $path, true),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($gallaryImageData)) {
                    PropertyImages::insert($gallaryImageData);
                }
            }
            // / END :: UPLOAD GALLERY IMAGE

            // / START :: UPLOAD DOCUMENTS
            if ($request->hasfile('documents')) {
                $path = config('global.PROPERTY_DOCUMENT_PATH').$saveProperty->id.'/';
                $documentsData = [];
                foreach ($request->file('documents') as $file) {
                    $documentsData[] = [
                        'property_id' => $saveProperty->id,
                        'name' => FileService::compressAndUpload($file, $path),
                        'type' => $file->extension(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($documentsData)) {
                    PropertiesDocument::insert($documentsData);
                }
            }
            // / END :: UPLOAD DOCUMENTS

            // START :: ADD CITY DATA
            if (isset($request->city) && ! empty($request->city)) {
                CityImage::updateOrCreate(['city' => $request->city]);
            }
            // END :: ADD CITY DATA

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        if (isset($value['language_id']) && ! empty($value['language_id']) && isset($value['value']) && ! empty($value['value'])) {
                            $translationData[] = [
                                'id' => $value['translation_id'] ?? null,
                                'translatable_id' => $saveProperty->id,
                                'translatable_type' => 'App\Models\Property',
                                'language_id' => $value['language_id'],
                                'key' => $key,
                                'value' => $value['value'],
                            ];
                        }
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }

            $result = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $saveProperty->id)->first();
            $property_details = get_property_details($result, null, true);
            if ($alertNewPropertyNotification) {
                HelperService::AlertUserForNewListing($saveProperty->id);
            }

            $customerId = $result->customer->id ?? null;

            if ($customerId) {
                $meta = HelperService::getCustomerMeta($customerId);

                $result->is_agent = $meta['is_agent'] ?? false;
                $result->is_agent_verified = $meta['is_agent_verified'] ?? false;
                $result->is_user_verified = $meta['is_user_verified'] ?? false;
                $result->agent_verification_status = $meta['agent_verification_status'] ?? 'not_applied';
                $result->become_agent_status = $meta['become_agent_status'] ?? 'not_applied';
                $result->user_verification_status = $meta['user_verification_status'] ?? 'not_applied';
            }

            DB::commit();
            $response['error'] = false;
            $response['message'] = trans('Property Posted Successfully');
            $response['data'] = $result;
        } catch (Exception $e) {
            return ResponseService::errorResponse($e->getMessage());
            DB::rollback();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }

        return response()->json($response);
    }

    public function activateListing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'listing_type' => 'required|in:property,project',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $alertNewPropertyNotification = false;

            if ($request->listing_type == 'property') {
                $property = Property::where('id', $request->id)->first();
                if (! $property) {
                    ApiResponseService::errorResponse('Property not found');
                }
                if ($property->added_by != $loggedInUserId) {
                    ApiResponseService::errorResponse('You are not authorized to activate this listing');
                }
                if ($property->getRawOriginal('request_status') != 'draft') {
                    ApiResponseService::errorResponse('Only draft listings can be activated');
                }

                // check package limit avaible or not
                $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), true, true, $request->user_active_role);
                if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
                    $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), false, true);
                    $isPayAsYouGo = ($limitResult === 'pay_as_you_go');
                } else {
                    return ApiResponseService::errorResponse('Your package limit is exceeded. Please upgrade your package to activate this listing.');
                }

                // ckeck auto approve status
                $autoApproveStatus = HelperService::getAutoApproveStatus($loggedInUserId, $request->user_active_role);
                if ($autoApproveStatus) {
                    $property->request_status = 'approved';
                    $property->status = 1;
                    $alertNewPropertyNotification = true;
                } else {
                    $property->request_status = 'pending';
                    $property->status = 0;
                    $alertNewPropertyNotification = false;
                }
                if ($autoApproveStatus) {
                    if ($isPayAsYouGo) {
                        $property->expiry_date = Carbon::now()->addDays(30);
                    } else {
                        $property->expiry_date = HelperService::calculateExpirationDate($loggedInUserId);
                    }
                }
                $property->save();
                DB::commit();

                if ($alertNewPropertyNotification) {
                    HelperService::AlertUserForNewListing($property->id);
                }

                ApiResponseService::successResponse('Listing Activated Successfully');

            }

            if ($request->listing_type == 'project') {
                $project = Projects::where('id', $request->id)->first();
                if (! $project) {
                    ApiResponseService::errorResponse('Project not found');
                }
                if ($project->added_by != $loggedInUserId) {
                    ApiResponseService::errorResponse('You are not authorized to activate this listing');
                }
                if ($project->getRawOriginal('request_status') != 'draft') {
                    ApiResponseService::errorResponse('Only draft listings can be activated');
                }

                // check package limit avaible or not
                $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), true, true, $request->user_active_role);
                if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
                    $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), false, true);
                    $isPayAsYouGo = ($limitResult === 'pay_as_you_go');
                } else {
                    return ApiResponseService::errorResponse('Your package limit is exceeded. Please upgrade your package to activate this listing.');
                }

                // ckeck auto approve status
                $autoApproveStatus = HelperService::getAutoApproveStatus($loggedInUserId, $request->user_active_role);
                if ($autoApproveStatus) {
                    $project->request_status = 'approved';
                    $project->status = 1;
                    $alertNewPropertyNotification = true;
                } else {
                    $project->request_status = 'pending';
                    $project->status = 0;
                    $alertNewPropertyNotification = false;
                }
                if ($autoApproveStatus) {
                    if ($isPayAsYouGo) {
                        $project->expiry_date = Carbon::now()->addDays(30);
                    } else {
                        $project->expiry_date = HelperService::calculateExpirationDate($loggedInUserId);
                    }
                }
                $project->save();
                DB::commit();

                if ($alertNewPropertyNotification) {
                    HelperService::AlertUserForNewListing($project->id, 'project');
                }

                ApiResponseService::successResponse('Listing Activated Successfully');

            }

        } catch (Exception $e) {
            DB::rollback();

            return ResponseService::errorResponse($e->getMessage());
        }
    }

    public function update_post_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action_type' => 'required',
            'three_d_image' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:3000',
            'remove_three_d_image' => 'nullable|in:0,1',
            'documents.*' => 'nullable|mimes:pdf,doc,docx,txt|max:5120',
            'latitude' => 'required',
            'longitude' => 'required',
            'rentduration' => 'required_if:property_type,==,1',
            'meta_title' => 'nullable|max:255',
            'meta_image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description' => 'nullable|max:255',
            'meta_keywords' => 'nullable|max:255',
            'price' => ['required', 'numeric', 'min:1', 'max:9223372036854775807', function ($attribute, $value, $fail) {
                if ($value >= 9223372036854775807) {
                    $fail('The Price must not exceed more than 9223372036854775807.');
                }
            }],
            'video_type' => 'nullable|in:0,1,2',
            'video_link' => [
                'nullable',
                'required_if:video_type,1,2',
                new VideoUrlRule($request->video_type),
            ],
            'translations.*.title.translation_id' => 'nullable|exists:translations,id',
            'translations.*.title.language_id' => 'nullable|exists:languages,id',
            'translations.*.title.value' => 'nullable',
            'translations.*.description.translation_id' => 'nullable|exists:translations,id',
            'translations.*.description.language_id' => 'nullable|exists:languages,id',
            'translations.*.description.value' => 'nullable',

        ], [
            'documents.*' => 'document :position',
            'meta_title.max' => trans('The Meta Title must not exceed more than 255 characters.'),
            'meta_image.image' => trans('The Meta Image must be an image.'),
            'meta_image.mimes' => trans('The Meta Image must be a JPG, PNG, or JPEG file.'),
            'meta_image.max' => trans('The Meta Image must not exceed more than 5MB.'),
            'meta_description.max' => trans('The Meta Description must not exceed more than 255 characters.'),
            'meta_keywords.max' => trans('The Meta Keywords must not exceed more than 255 characters.'),
            'custom_video.max' => trans('The custom video must not be greater than 20MB.'),
            'custom_video.*.max' => trans('The custom video must not be greater than 20MB.'),
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            DB::beginTransaction();
            $loggedInUserId = Auth::user()->id;
            $id = $request->id;
            $action_type = $request->action_type;
            if ($request->slug_id) {
                $property = Property::where('added_by', $loggedInUserId)->where('slug_id', $request->slug_id)->first();
                if (! $property) {
                    $property = Property::where('added_by', $loggedInUserId)->find($id);
                }
            } else {
                $property = Property::where('added_by', $loggedInUserId)->find($id);
            }
            if (! $property) {
                ApiResponseService::validationError(trans('Property not found'));
            }
            // Validate active role matches property's role context
            if ($request->user_active_role !== ($property->role_context ?? 'user')) {
                ApiResponseService::validationError(trans('This property was created in :role mode. Please switch to :role mode to update it.', ['role' => $property->role_context ?? 'user']));
            }
            if ($property->getRawOriginal('propery_type') == '2') {
                ApiResponseService::validationError(trans('Sold property cannot be updated'));
            }
            if ($property->getRawOriginal('propery_type') == '3') {
                ApiResponseService::validationError(trans('Rented property cannot be updated'));
            }
            if (($property)) {
                // 0: Update 1: Delete
                if ($action_type == 0) {

                    if (isset($request->category_id)) {
                        $property->category_id = $request->category_id;
                    }

                    if (isset($request->title)) {
                        $property->title = $request->title;
                        $slugData = (isset($request->slug_id) && ! empty($request->slug_id)) ? $request->slug_id : $request->title;
                        $property->slug_id = generateUniqueSlug($slugData, 1, null, $id);
                    }

                    if (isset($request->slug_id) && ! empty($request->slug_id)) {
                        $property->slug_id = generateUniqueSlug($request->slug_id, 1, null, $id);
                    }

                    if (isset($request->description)) {
                        $property->description = $request->description;
                    }

                    if (isset($request->address)) {
                        $property->address = $request->address;
                    }

                    if (isset($request->client_address)) {
                        $property->client_address = $request->client_address;
                    }

                    if (isset($request->property_type)) {
                        $property->propery_type = $request->property_type;
                    }

                    if (isset($request->price)) {
                        $property->price = $request->price;
                    }
                    if (isset($request->country)) {
                        $property->country = $request->country;
                    }
                    if (isset($request->state)) {
                        $property->state = $request->state;
                    }
                    if (isset($request->city)) {
                        $property->city = $request->city;
                    }
                    if (isset($request->status)) {
                        $property->status = $request->status;
                    }
                    if (isset($request->latitude)) {
                        $property->latitude = $request->latitude;
                    }
                    if (isset($request->longitude)) {
                        $property->longitude = $request->longitude;
                    }
                    if (isset($request->rentduration)) {
                        $property->rentduration = $request->rentduration;
                    }
                    if ($request->has('remove_video') && $request->remove_video == 1) {
                        if ($property->video_type == Property::VIDEO_CUSTOM && ! empty($property->getRawOriginal('video_link'))) {
                            FileService::delete(config('global.PROPERTY_VIDEO_PATH'), $property->getRawOriginal('video_link'));
                        }
                        $property->video_type = null;
                        $property->video_link = null;
                    } else {
                        if (isset($request->video_type)) {
                            $property->video_type = $request->video_type;
                        }

                        if ((int) $property->video_type === Property::VIDEO_CUSTOM && $request->hasFile('custom_video')) {
                            $path = config('global.PROPERTY_VIDEO_PATH');
                            if ($property->getRawOriginal('video_link')) {
                                $property->video_link = FileService::compressAndReplace($request->file('custom_video'), $path, $property->getRawOriginal('video_link'));
                            } else {
                                $property->video_link = FileService::compressAndUpload($request->file('custom_video'), $path);
                            }
                        } else {
                            if (isset($request->video_link)) {
                                $property->video_link = $request->video_link;
                            }
                        }
                    }

                    $property->meta_title = isset($request->meta_title) && ! empty($request->meta_title) ? $request->meta_title : null;
                    $property->meta_description = isset($request->meta_description) && ! empty($request->meta_description) ? $request->meta_description : null;
                    $property->meta_keywords = isset($request->meta_keywords) && ! empty($request->meta_keywords) ? $request->meta_keywords : null;
                    $showPremiumToggle = system_setting('show_premium_toggle');
                    if ($showPremiumToggle == 1) {
                        $property->is_premium = ! empty($request->is_premium) && $request->is_premium == 'true' ? 1 : 0;
                    } else {
                        $property->is_premium = 0;
                    }

                    $wasDraft = ($property->getRawOriginal('request_status') === 'draft');

                    if ($wasDraft) {
                        $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), true, true, $request->user_active_role);
                        if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
                            $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), false, true);
                            $isPayAsYouGo = ($limitResult === 'pay_as_you_go');

                            $autoApproveStatus = HelperService::getAutoApproveStatus($loggedInUserId, $request->user_active_role);
                            if ($autoApproveStatus) {
                                $property->request_status = 'approved';
                            } else {
                                $property->request_status = 'pending';
                            }
                            $property->status = 1;

                            if ($autoApproveStatus) {
                                if ($isPayAsYouGo) {
                                    $property->expiry_date = Carbon::now()->addDays(30);
                                } else {
                                    $property->expiry_date = HelperService::calculateExpirationDate($loggedInUserId);
                                }
                            }
                        } else {
                            $property->request_status = 'draft';
                            $property->status = 0;
                        }
                    } else {
                        $autoApproveStatus = HelperService::getAutoApproveStatus($loggedInUserId, $request->user_active_role);
                        if (! $autoApproveStatus) {
                            if (HelperService::getSettingData('auto_approve_edited_listings') == 0) {
                                $property->request_status = 'pending';
                            }
                        }
                    }

                    if ($request->hasFile('title_image')) {
                        $path = config('global.PROPERTY_TITLE_IMG_PATH');
                        $profile = $request->file('title_image');
                        $rawImage = $property->getRawOriginal('title_image');
                        FileService::clearCachedBlurImageUrl('blur_property_title_image_'.$property->id);
                        $property->title_image = FileService::compressAndReplace($profile, $path, $rawImage, true);
                    }

                    if ($request->has('remove_meta_image') && $request->remove_meta_image == 1) {
                        if (! empty($property->meta_image)) {
                            $url = $property->meta_image;
                            $relativePath = parse_url($url, PHP_URL_PATH);
                            $path = config('global.PROPERTY_SEO_IMG_PATH');
                            FileService::delete($path, $relativePath);
                        }
                    }

                    if ($request->has('meta_image')) {
                        if ($request->meta_image != $property->meta_image) {
                            if (! empty($request->meta_image) && $request->hasFile('meta_image')) {
                                $path = config('global.PROPERTY_SEO_IMG_PATH');
                                $profile = $request->file('meta_image');
                                $rawImage = $property->getRawOriginal('meta_image');
                                $property->meta_image = FileService::compressAndReplace($profile, $path, $rawImage);
                            }
                        }
                    }

                    if ($request->has('remove_three_d_image') && $request->remove_three_d_image == 1) {
                        $threeDImage = $property->getRawOriginal('three_d_image');
                        $path = config('global.3D_IMG_PATH');
                        FileService::delete($path, $threeDImage);
                    }

                    if ($request->hasFile('three_d_image')) {
                        $path = config('global.3D_IMG_PATH');
                        $profile = $request->file('three_d_image');
                        $rawImage = $property->getRawOriginal('three_d_image');
                        $property->three_d_image = FileService::compressAndReplace($profile, $path, $rawImage);
                    }

                    if ($request->parameters) {
                        $path = config('global.PARAMETER_IMAGE_PATH');
                        foreach ($request->parameters as $key => $parameter) {
                            $AssignParameters = AssignParameters::where('modal_id', $property->id)->where('parameter_id', $parameter['parameter_id'])->pluck('id');
                            if (count($AssignParameters)) {
                                $update_data = AssignParameters::find($AssignParameters[0]);
                                if ($request->hasFile('parameters.'.$key.'.value')) {
                                    $profile = $request->file('parameters.'.$key.'.value');
                                    $allowedMimeTypes = [
                                        'image/jpeg',
                                        'image/png',
                                        'image/jpg',
                                        'image/gif',
                                        'image/webp',
                                        'application/pdf',
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'text/plain',
                                    ];
                                    $mimeType = $profile->getMimeType();
                                    if (! in_array($mimeType, $allowedMimeTypes)) {
                                        return ResponseService::validationError(trans('The parameter file must be an image (jpeg, png, jpg, gif, webp) or a document (pdf, doc, docx, txt).'));
                                    }
                                    $rawImage = $update_data->getRawOriginal('value');
                                    $update_data->value = FileService::compressAndReplace($profile, $path, $rawImage);
                                } else {
                                    $update_data->value = $parameter['value'];
                                }
                                $update_data->save();
                            } else {
                                $AssignParameters = new AssignParameters;
                                $AssignParameters->modal()->associate($property);
                                $AssignParameters->parameter_id = $parameter['parameter_id'];
                                if ($request->hasFile('parameters.'.$key.'.value')) {
                                    $profile = $request->file('parameters.'.$key.'.value');
                                    $AssignParameters->value = FileService::compressAndUpload($profile, $path);
                                } else {
                                    $AssignParameters->value = $parameter['value'];
                                }
                                $AssignParameters->save();
                            }
                            Cache::forget("property_parameters_{$property->id}");
                        }
                    }

                    if ($request->id) {
                        $prop_id = $request->id;
                        AssignedOutdoorFacilities::where('property_id', $request->id)->delete();
                    } else {
                        $prop = Property::where('slug_id', $request->slug_id)->first();
                        $prop_id = $prop->id;
                        AssignedOutdoorFacilities::where('property_id', $prop->id)->delete();
                    }
                    // AssignedOutdoorFacilities::where('property_id', $request->id)->delete();
                    if ($request->facilities) {
                        foreach ($request->facilities as $key => $value) {
                            if (isset($value['facility_id']) && ! empty($value['facility_id']) && isset($value['distance']) && ! empty($value['distance'])) {
                                $facilities = new AssignedOutdoorFacilities;
                                $facilities->facility_id = $value['facility_id'];
                                $facilities->property_id = $prop_id;
                                $facilities->distance = $value['distance'];
                                $facilities->save();
                                Cache::forget("property_assign_facilities_{$property->id}");
                            }
                        }
                    }

                    $property->save();
                    $update_property = Property::with('customer')->with('category:id,category,image')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->where('id', $request->id)->first();
                    $propertyId = $request->id;

                    // / START :: UPLOAD GALLERY IMAGE
                    if ($request->remove_gallery_images) {
                        $path = config('global.PROPERTY_GALLERY_IMG_PATH').$propertyId.'/';
                        foreach ($request->remove_gallery_images as $key => $value) {
                            $gallary_images = PropertyImages::find($value);
                            $rawImage = $gallary_images->getRawOriginal('image');
                            FileService::delete($path, $rawImage);
                            $gallary_images->delete();
                        }
                    }
                    if ($request->hasfile('gallery_images')) {
                        $path = config('global.PROPERTY_GALLERY_IMG_PATH').$propertyId.'/';
                        $galleryImagesData = [];
                        foreach ($request->file('gallery_images') as $file) {
                            $image = FileService::compressAndUpload($file, $path, true);
                            $galleryImagesData[] = [
                                'propertys_id' => $propertyId,
                                'image' => $image,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        if (collect($galleryImagesData)->isNotEmpty()) {
                            PropertyImages::insert($galleryImagesData);
                        }
                    }
                    // / END :: UPLOAD GALLERY IMAGE

                    // / START :: UPLOAD DOCUMENTS
                    if ($request->remove_documents) {
                        foreach ($request->remove_documents as $key => $value) {
                            $document = PropertiesDocument::find($value);
                            $rawImage = $document->getRawOriginal('name');
                            $path = config('global.PROPERTY_DOCUMENT_PATH').$document->propertys_id.'/';
                            FileService::delete($path, $rawImage);
                            $document->delete();
                        }
                    }

                    if ($request->hasfile('documents')) {
                        $path = config('global.PROPERTY_DOCUMENT_PATH').$propertyId.'/';
                        $documentsData = [];
                        foreach ($request->file('documents') as $file) {
                            $type = $file->extension();
                            $name = FileService::compressAndUpload($file, $path);
                            $documentsData[] = [
                                'property_id' => $propertyId,
                                'name' => $name,
                                'type' => $type,
                            ];
                        }

                        if (collect($documentsData)->isNotEmpty()) {
                            PropertiesDocument::insert($documentsData);
                        }
                    }
                    // / END :: UPLOAD DOCUMENTS

                    // START :: ADD CITY DATA
                    if (isset($request->city) && ! empty($request->city)) {
                        CityImage::updateOrCreate(['city' => $request->city]);
                    }
                    // END :: ADD CITY DATA

                    // START ::Add Translations
                    if (isset($request->translations) && ! empty($request->translations)) {
                        $translationData = [];
                        foreach ($request->translations as $translation) {
                            foreach ($translation as $key => $value) {
                                if (isset($value['language_id']) && ! empty($value['language_id']) && isset($value['value']) && ! empty($value['value'])) {
                                    $translationData[] = [
                                        'id' => $value['translation_id'] ?? null,
                                        'translatable_id' => $property->id,
                                        'translatable_type' => 'App\Models\Property',
                                        'language_id' => $value['language_id'],
                                        'key' => $key,
                                        'value' => $value['value'],
                                    ];
                                }
                            }
                        }
                        if (! empty($translationData)) {
                            HelperService::storeTranslations($translationData);
                        }
                    }

                    $current_user = Auth::user()->id;
                    $property_details = get_property_details($update_property, $current_user, true);
                    $customerId = $update_property->customer->id ?? null;

                    if ($customerId) {
                        $meta = HelperService::getCustomerMeta($customerId);

                        $update_property->is_agent = $meta['is_agent'] ?? false;
                        $update_property->is_agent_verified = $meta['is_agent_verified'] ?? false;
                        $update_property->is_user_verified = $meta['is_user_verified'] ?? false;
                        $update_property->agent_verification_status = $meta['agent_verification_status'] ?? 'not_applied';
                        $update_property->become_agent_status = $meta['become_agent_status'] ?? 'not_applied';
                        $update_property->user_verification_status = $meta['user_verification_status'] ?? 'not_applied';
                    }

                    $response['error'] = false;
                    $response['message'] = trans('Property Updated Successfully');
                    $response['data'] = $update_property;
                } elseif ($action_type == 1) {
                    if ($property->delete()) {
                        $response['error'] = false;
                        $response['message'] = trans('Data Deleted Successfully');
                    } else {
                        $response['error'] = true;
                        $response['message'] = trans('Something Went Wrong');
                    }
                }
            } else {
                $response['error'] = false;
                $response['message'] = trans('No Data Found');
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return response()->json($response, 500);
        }

        return response()->json($response);
    }

    public function delete_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:propertys,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            $property = Property::where('added_by', Auth::user()->id)->findOrFail($request->id);

            // Validate active role matches property's role context
            if ($request->user_active_role !== ($property->role_context ?? 'user')) {
                return response()->json([
                    'error' => true,
                    'message' => trans('This property was created in :role mode. Please switch to :role mode to delete it.', ['role' => $property->role_context ?? 'user']),
                ]);
            }

            $property->delete();

            return response()->json(['error' => false, 'message' => trans('Property Deleted Successfully')]);
        } catch (Exception $e) {
            DB::rollback();

            return response()->json(['error' => true, 'message' => trans('Something Went Wrong')], 500);
        }
    }

    public function update_property_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable',
            'property_id' => 'required|exists:propertys,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $loggedInUserId = Auth::user()->id;
            $property = Property::where('added_by', $loggedInUserId)->findOrFail($request->property_id);

            // Handle Draft Graduation
            if ($property->getRawOriginal('request_status') === 'draft') {
                $graduation = $this->graduateDraft($property, $request->user_active_role);
                if ($graduation['success']) {
                    return response()->json([
                        'error' => false,
                        'message' => trans('Property Published Successfully'),
                        'data' => $property,
                    ]);
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => $graduation['message'],
                    ]);
                }
            }

            // Original Sell/Sold/Rent status update logic
            if ($request->has('status') && in_array($request->status, [1, 2, 3])) {
                if ($property->getRawOriginal('propery_type') == 0 && $request->status != 2) {
                    return response()->json(['error' => true, 'message' => 'You can only change sell property to sold']);
                } elseif ($property->getRawOriginal('propery_type') == 1 && $request->status != 3) {
                    return response()->json(['error' => true, 'message' => 'You can only change rent property to rented']);
                } elseif ($property->getRawOriginal('propery_type') != 0 && $property->getRawOriginal('propery_type') != 1 && $property->getRawOriginal('propery_type') != 3) {
                    return response()->json(['error' => true, 'message' => 'You can only change status of sell, rent and rented properties']);
                }
                $property->propery_type = $request->status;
                $property->save();

                return response()->json([
                    'error' => false,
                    'message' => trans('Data Updated Successfully'),
                ]);
            }

            return response()->json(['error' => true, 'message' => 'Invalid status update requested.']);

        } catch (Exception $e) {
            return ResponseService::errorResponse($e);
        }
    }

    private function graduateDraft($property, $userActiveRole)
    {
        $loggedInUserId = Auth::user()->id;
        $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), true, true, $userActiveRole);
        if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
            $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROPERTY_LIST.TYPE'), false, true);
            $isPayAsYouGo = ($limitResult === 'pay_as_you_go');

            $autoApproveStatus = HelperService::getAutoApproveStatus($loggedInUserId, $userActiveRole);
            if ($autoApproveStatus) {
                $property->request_status = 'approved';
            } else {
                $property->request_status = 'pending';
            }
            $property->status = 1;

            if ($autoApproveStatus) {
                if ($isPayAsYouGo) {
                    $property->expiry_date = Carbon::now()->addDays(30);
                } else {
                    $property->expiry_date = HelperService::calculateExpirationDate($loggedInUserId);
                }
            }
            $property->save();

            if ($autoApproveStatus) {
                HelperService::AlertUserForNewListing($property->id);
            }

            return ['success' => true, 'is_pay_as_you_go' => $isPayAsYouGo, 'auto_approve' => $autoApproveStatus];
        } else {
            return ['success' => false, 'message' => trans('Please purchase a package to publish this property.')];
        }
    }

    public function changePropertyStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:propertys,id',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            // Get Query Data of property based on property id and ownership
            $propertyQueryData = Property::where('added_by', Auth::user()->id)->find($request->property_id);
            if (! $propertyQueryData) {
                ApiResponseService::validationError('Property not found');
            }
            // Validate active role matches property's role context
            if ($request->user_active_role !== ($propertyQueryData->role_context ?? 'user')) {
                ApiResponseService::validationError(trans('This property was created in :role mode. Please switch to :role mode.', ['role' => $propertyQueryData->role_context ?? 'user']));
            }
            if ($propertyQueryData->request_status != 'approved') {
                ApiResponseService::validationError('Property is not approved');
            }
            // update user status
            $propertyQueryData->status = $request->status == 1 ? 1 : 0;
            $propertyQueryData->save();
            ApiResponseService::successResponse('Data Updated Successfully');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAddedProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_type' => 'nullable|in:0,1,2,3',
            'request_status' => 'nullable|in:approved,rejected,pending,expired',
            'is_promoted' => 'nullable|in:1',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Get Logged In User data
            $loggedInUserData = Auth::user();
            // Get Current Logged In User ID
            $loggedInUserID = $loggedInUserData->id;

            if ($request->has('id') || $request->has('slug_id')) {

                // 2. Check if this property exists for this user specifically as an agent
                $isAgentProperty = Property::where('added_by', $loggedInUserID)
                    ->when($request->id, function ($q) use ($request) {
                        return $q->where('id', $request->id);
                    })
                    ->when($request->slug_id, function ($q) use ($request) {
                        return $q->where('slug_id', $request->slug_id);
                    })
                    ->where('role_context', 'agent') // Specifically look for the "hidden" agent property
                    ->exists();

                // 3. If it exists as an agent property, return the error before the main query runs
                if ($isAgentProperty && $request->user_active_role !== 'agent') {
                    return ApiResponseService::errorResponse('Unauthorized. Active role must be agent.', null, null, 403, null, [], config('constants.API_RESPONSE_KEY.REQUIRED_AGENT_ROLE'));
                }
            }

            // when is_promoted is passed then show only property who has been featured (advertised)
            if ($request->has('is_promoted') && $request->is_promoted == 1) {
                // Create Advertisement Query which has Property Data
                $advertisementQuery = Advertisement::whereHas('property', function ($query) use ($loggedInUserID) {
                    $query->where(['post_type' => 1, 'added_by' => $loggedInUserID]);
                })->where('role_context', $request->user_active_role)->with('property:id,category_id,slug_id,title,propery_type,city,state,country,price,title_image', 'property.category:id,category,image');

                // Get Total Advertisement Data
                $advertisementTotal = $advertisementQuery->clone()->count();

                // Get Advertisement Data with custom Data
                $advertisementData = $advertisementQuery->clone()->skip($offset)->take($limit)->orderBy('id', 'DESC')->get()->map(function ($advertisement) {
                    if (collect($advertisement->property)->isNotEmpty()) {
                        $otherData = [];
                        $otherData['id'] = $advertisement->property->id;
                        $otherData['slug_id'] = $advertisement->property->slug_id;
                        $otherData['property_type'] = $advertisement->property->propery_type;
                        $otherData['title'] = $advertisement->property->title;
                        $otherData['city'] = $advertisement->property->city;
                        $otherData['state'] = $advertisement->property->state;
                        $otherData['country'] = $advertisement->property->country;
                        $otherData['price'] = $advertisement->property->price;
                        $otherData['title_image'] = $advertisement->property->title_image;
                        $otherData['advertisement_id'] = $advertisement->id;
                        $otherData['advertisement_status'] = $advertisement->status;
                        $otherData['advertisement_type'] = $advertisement->type;
                        $otherData['category'] = $advertisement->property->category;
                        unset($advertisement); // remove the original data

                        return $otherData; // return custom created data
                    }
                });
                $response = [
                    'error' => false,
                    'data' => $advertisementData,
                    'total' => $advertisementTotal,
                    'message' => trans('Data Fetched Successfully'),
                ];
            } else {
                // Check the property's post is done by customer and added by logged in user
                $propertyQuery = Property::where(['post_type' => 1, 'added_by' => $loggedInUserID])
                    // When property type is passed in payload show data according property type that is sell or rent
                    ->when($request->filled('property_type'), function ($query) use ($request) {
                        return $query->where('propery_type', $request->property_type);
                    })
                    ->when($request->filled('id'), function ($query) use ($request) {
                        return $query->where('id', $request->id);
                    })
                    ->when($request->filled('slug_id'), function ($query) use ($request) {
                        return $query->where('slug_id', $request->slug_id);
                    })
                    ->when($request->filled('status'), function ($query) use ($request) {
                        // IF Status is passed and status has active (1) or deactive (0) or both
                        $statusData = explode(',', $request->status);

                        return $query->whereIn('status', $statusData)->where('request_status', 'approved');
                    })
                    ->when($request->filled('request_status'), function ($query) use ($request) {
                        // IF Request Status is passed and status has approved or rejected or pending or expired
                        if ($request->request_status == 'expired') {
                            return $query->whereNotNull('expiry_date')->where('expiry_date', '<', now());
                        }

                        return $query->where('request_status', $request->request_status);
                    })
                    ->where('role_context', $request->user_active_role)

                    // Pass the Property Data with Category and Advertisement Relation Data
                    ->with('category.translations', 'advertisement', 'interested_users:id,property_id,customer_id', 'interested_users.customer:id,name,profile', 'translations');

                // Get Total Views by Sum of total click of each property
                $totalViews = $propertyQuery->sum('total_click');

                // Get total properties
                $totalProperties = $propertyQuery->count();

                // Get the property data with extra data and changes :- is_premium, post_created and promoted
                $propertyData = $propertyQuery->skip($offset)->take($limit)->orderBy('id', 'DESC')->get()->map(function ($property) use ($loggedInUserData) {
                    // Add lastest Reject reason when request status is rejected
                    $property->reject_reason = (object) [];
                    if ($property->request_status == 'rejected') {
                        $property->reject_reason = $property->reject_reason()->latest()->first();
                    }
                    $property->is_premium = $property->is_premium == 1 ? true : false;
                    $property->property_type = $property->propery_type;
                    $property->post_created = $property->created_at->diffForHumans();
                    $property->promoted = $property->is_promoted;
                    $property->parameters = $property->parameters;
                    $property->assign_facilities = $property->assign_facilities;
                    $property->is_feature_available = $property->is_feature_available;
                    if ($property->category) {
                        $property->category->translated_name = $property->category->translated_name;
                    }
                    $property->translated_title = $property->translated_title;
                    $property->translated_description = $property->translated_description;
                    $property->translated_address = $property->translated_address;

                    // Interested Users
                    $interestedUsers = $property->interested_users;
                    unset($property->interested_users);
                    $property->interested_users = $interestedUsers->map(function ($interestedUser) {
                        unset($property->id);
                        unset($property->property_id);
                        unset($property->customer_id);

                        return $interestedUser->customer;
                    });

                    // Add User's Details
                    $property->customer_name = $loggedInUserData->name;
                    $property->email = $loggedInUserData->email;
                    $property->mobile = $loggedInUserData->mobile;
                    $property->profile = $loggedInUserData->profile;
                    $customerId = $loggedInUserData->id ?? null;

                    if ($customerId) {
                        $meta = HelperService::getCustomerMeta($customerId);

                        $property->is_agent = $meta['is_agent'] ?? false;
                        $property->is_agent_verified = $meta['is_agent_verified'] ?? false;
                        $property->is_user_verified = $meta['is_user_verified'] ?? false;
                        $property->agent_verification_status = $meta['agent_verification_status'] ?? 'not_applied';
                        $property->become_agent_status = $meta['become_agent_status'] ?? 'not_applied';
                        $property->user_verification_status = $meta['user_verification_status'] ?? 'not_applied';
                    }

                    // $propertyData = new CustomerResource($property);

                    return $property;
                });

                $response = [
                    'error' => false,
                    'data' => $propertyData,
                    'total' => $totalProperties,
                    'total_views' => $totalViews,
                    'message' => trans('Data Fetched Successfully'),
                ];

                $getSimilarProperties = [];
                if ($propertyData->isNotEmpty()) {
                    if ($request->has('id')) {
                        $getSimilarPropertiesQueryData = Property::where(['post_type' => 1, 'added_by' => $loggedInUserID, 'category_id' => $propertyData[0]['category_id']])->where('id', '!=', $request->id)->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image', 'is_premium', 'expiry_date')->orderBy('id', 'desc')->limit(10)->get();
                        $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $loggedInUserData, true);

                    } elseif ($request->has('slug_id')) {
                        $getSimilarPropertiesQueryData = Property::where(['post_type' => 1, 'added_by' => $loggedInUserID, 'category_id' => $propertyData[0]['category_id']])->where('slug_id', '!=', $request->slug_id)->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'created_at', 'title_image', 'is_premium', 'expiry_date')->orderBy('id', 'desc')->limit(10)->get();
                        $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $loggedInUserData, true);
                    }
                }
                $response['similiar_properties'] = $getSimilarProperties;
            }

            // dd($response);
            return response()->json($response);
            // return ApiResponseService::successResponse('Data Fetched Successfully', $response);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
            ];

            return ApiResponseService::errorResponse('Something Went Wrong', 500);
        }
    }

    public function remove_post_images(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if (! $validator->fails()) {
            $id = $request->id;
            $getImage = PropertyImages::where('id', $id)->first();
            $image = $getImage->image;
            $propertys_id = $getImage->propertys_id;

            if (PropertyImages::where('id', $id)->delete()) {
                $path = config('global.PROPERTY_GALLERY_IMG_PATH').$propertys_id.'/';
                FileService::delete($path, $image);
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }

            $countImage = PropertyImages::where('propertys_id', $propertys_id)->get();
            if ($countImage->count() == 0) {
                rmdir(storage_path('app/public').config('global.PROPERTY_GALLERY_IMG_PATH').$propertys_id);
            }

            $response['error'] = false;
            $response['message'] = trans('Property Image Removed Successfully');
        } else {
            $response['error'] = true;
            $response['message'] = trans('Please fill all data and Submit');
        }

        return response()->json($response);
    }

    //  public function getPropertiesOnMap(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'latitude' => 'nullable|numeric',
    //         'longitude' => 'nullable|numeric',
    //         'city' => 'nullable|string',
    //         'state' => 'nullable|string',
    //         'country' => 'nullable|string',
    //         'category_id' => 'nullable|integer',
    //         'property_type' => 'nullable|in:0,1,2,3',
    //     ]);
    //     if ($validator->fails()) {
    //         ApiResponseService::validationError($validator->errors()->first());
    //     }
    //     try {
    //         // Create reusable property mapper function
    //         $propertyMapper = function ($propertyData) {
    //             $propertyData->promoted = $propertyData->is_promoted;
    //             $propertyData->property_type = $propertyData->propery_type;
    //             $propertyData->parameters = $propertyData->parameters;
    //             $propertyData->is_premium = $propertyData->is_premium == 1;
    //             $propertyData->category->translated_name = $propertyData->category->translated_name;
    //             $propertyData->translated_title = $propertyData->translated_title;
    //             $propertyData->translated_description = $propertyData->translated_description;
    //             unset($propertyData->propery_type);
    //             return $propertyData;
    //         };

    //         // Base property query that will be reused
    //         $propertyQuery = Property::select(
    //             'id',
    //             'slug_id',
    //             'category_id',
    //             'city',
    //             'state',
    //             'country',
    //             'price',
    //             'propery_type',
    //             'title',
    //             'title_image',
    //             'is_premium',
    //             'rentduration',
    //             'latitude',
    //             'longitude',
    //             'role_context'
    //         )
    //             ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
    //             ->onlyActive()
    //             ->whereIn('propery_type', [0, 1])
    //             ->when($request->filled('role_context'), function ($query) use ($request) {
    //                 return $query->where('role_context', $request->role_context);
    //             });

    //         // If Property Type Passed
    //         $property_type = $request->property_type;  //0 : Sell 1:Rent
    //         if (isset($property_type) && (!empty($property_type) || $property_type == 0)) {
    //             $propertyQuery = $propertyQuery->clone()->where('propery_type', $property_type);
    //         }

    //         // If Category Id is Passed
    //         if ($request->has('category_id') && !empty($request->category_id)) {
    //             $propertyQuery = $propertyQuery->clone()->where('category_id', $request->category_id);
    //         }

    //         // If parameter id passed
    //         if ($request->has('parameter_id') && !empty($request->parameter_id)) {
    //             $parametersId = explode(",", $request->parameter_id);
    //             $propertyQuery = $propertyQuery->clone()->whereHas('assignParameter', function ($query) use ($parametersId) {
    //                 $query->whereIn('parameter_id', $parametersId)->whereNotNull('value');
    //             });
    //         }

    //         // If Category Slug is Passed
    //         if ($request->has('category_slug_id') && !empty($request->category_slug_id)) {
    //             $categorySlugId = $request->category_slug_id;
    //             $propertyQuery = $propertyQuery->clone()->whereHas('category', function ($query) use ($categorySlugId) {
    //                 $query->where('slug_id', $categorySlugId);
    //             });
    //         }

    //         // If Country is passed
    //         if ($request->has('country') && !empty($request->country)) {
    //             $propertyQuery = $propertyQuery->clone()->where('country', $request->country);
    //         }

    //         // If State is passed
    //         if ($request->has('state') && !empty($request->state)) {
    //             $propertyQuery = $propertyQuery->clone()->where('state', $request->state);
    //         }

    //         // If City is passed
    //         if ($request->has('city') && !empty($request->city)) {
    //             $propertyQuery = $propertyQuery->clone()->where('city', $request->city);
    //         }

    //         // If place ID is passed, resolve it to city name
    //         if ($request->has('place_id') && !empty($request->place_id)) {
    //             $locationData = $this->resolvePlaceIdToLocation($request->place_id);
    //             if ($locationData) {
    //                 if ($locationData['city']) {
    //                     $propertyQuery = $propertyQuery->clone()->where('city', $locationData['city']);
    //                 }
    //                 if ($locationData['state']) {
    //                     $propertyQuery = $propertyQuery->clone()->where('state', $locationData['state']);
    //                 }
    //                 if ($locationData['country']) {
    //                     $propertyQuery = $propertyQuery->clone()->where('country', $locationData['country']);
    //                 }
    //             }
    //         } else {
    //             // Latitude and Longitude
    //             if ($request->has('latitude') && !empty($request->latitude) && $request->has('longitude') && !empty($request->longitude)) {
    //                 $propertyQuery = $propertyQuery->clone()->where('latitude', $request->latitude)->where('longitude', $request->longitude);
    //             }
    //         }

    //         // If Max Price And Min Price passed
    //         if ($request->has('min_price') && !empty($request->min_price)) {
    //             $minPrice = $request->min_price;
    //             $propertyQuery = $propertyQuery->clone()->where('price', '>=', $minPrice);
    //         }

    //         if (isset($request->max_price) && !empty($request->max_price)) {
    //             $maxPrice = $request->max_price;
    //             $propertyQuery = $propertyQuery->clone()->where('price', '<=', $maxPrice);
    //         }

    //         // If Posted Since 0 or 1 is passed
    //         if ($request->has('posted_since')) {
    //             $posted_since = $request->posted_since;

    //             // 0 - Last Week (from today back to the same day last week)
    //             if ($posted_since == 0) {
    //                 $oneWeekAgo = Carbon::now()->subWeek()->startOfDay();
    //                 $today = Carbon::now()->endOfDay();
    //                 $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$oneWeekAgo, $today]);
    //             }
    //             // 1 - Yesterday
    //             if ($posted_since == 1) {
    //                 $yesterdayDate = Carbon::yesterday();
    //                 $propertyQuery =  $propertyQuery->clone()->whereDate('created_at', $yesterdayDate);
    //             }

    //             // 2 - Last Month
    //             if ($posted_since == 2) {
    //                 $lastMonthDate = Carbon::now()->subMonth();
    //                 $today = Carbon::now()->endOfDay();
    //                 $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$lastMonthDate, $today]);
    //             }

    //             // 3 - Last 3 Months
    //             if ($posted_since == 3) {
    //                 $lastThreeMonthsDate = Carbon::now()->subMonths(3);
    //                 $today = Carbon::now()->endOfDay();
    //                 $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$lastThreeMonthsDate, $today]);
    //             }

    //             // 4 - Last 6 Months
    //             if ($posted_since == 4) {
    //                 $lastSixMonthsDate = Carbon::now()->subMonths(6);
    //                 $today = Carbon::now()->endOfDay();
    //                 $propertyQuery = $propertyQuery->clone()->whereBetween('created_at', [$lastSixMonthsDate, $today]);
    //             }
    //         }

    //         // Search the property
    //         if ($request->has('search') && !empty($request->search)) {
    //             $search = $request->search;
    //             $propertyQuery = $propertyQuery->clone()->where(function ($query) use ($search) {
    //                 $query->where('title', 'LIKE', "%$search%")
    //                     ->orWhere('address', 'LIKE', "%$search%")
    //                     ->orWhereHas('category', function ($query1) use ($search) {
    //                         $query1->where('category', 'LIKE', "%$search%");
    //                     });
    //             });
    //         }

    //         // IF Promoted Passed then show the data according to
    //         if ($request->has('promoted') && $request->promoted == 1) {
    //             $propertyQuery = $propertyQuery->clone()->whereHas('advertisement', function ($query) {
    //                 $query->where(['status' => 0, 'is_enable' => 1]);
    //             });
    //         }

    //         // If get_all_premium_properties is passed then show the data according to
    //         if ($request->has('get_all_premium_properties') && $request->get_all_premium_properties == 1) {
    //             $propertyQuery = $propertyQuery->clone()->where('is_premium', 1);
    //         }

    //         // Get total properties
    //         $totalProperties = $propertyQuery->clone()->count();

    //         // If Most Viewed Passed then show the property data with Order by on Total Click Descending
    //         if ($request->has('most_viewed') && $request->most_viewed == 1) {
    //             $propertyQuery = $propertyQuery->clone()->orderBy('total_click', 'DESC');
    //         }
    //         // If Most Liked Passed then show the property data with Order by on Total Click Descending
    //         else if ($request->has('most_liked') && $request->most_liked == 1) {
    //             $propertyQuery = $propertyQuery->clone()->orderBy('favourite_count', 'DESC');
    //         } else {
    //             // If No Most Viewed or Most Liked Passed then show the property data with Order by on Id Descending
    //             $propertyQuery = $propertyQuery->clone()->orderBy('id', 'DESC');
    //         }

    //         // Check the city and state params and query the params according to it
    //         if (isset($request->city) || isset($request->state)) {
    //             $propertyQuery->where(function ($query) use ($request) {
    //                 $query->where('state', 'LIKE', "%{$request->state}%")
    //                     ->orWhere('city', 'LIKE', "%{$request->city}%");
    //             });
    //         }

    //         // Check the type params and query the params according to it
    //         if (isset($request->type)) {
    //             $propertyQuery->where('propery_type', $request->type);
    //         }

    //         // If place ID is passed, resolve it to city, state, and country
    //         if ($request->has('place_id') && !empty($request->place_id)) {
    //             $locationData = $this->resolvePlaceIdToLocation($request->place_id);
    //             if ($locationData) {
    //                 if ($locationData['city']) {
    //                     $propertyQuery = $propertyQuery->clone()->where('city', $locationData['city']);
    //                 }
    //                 if ($locationData['state']) {
    //                     $propertyQuery = $propertyQuery->clone()->where('state', $locationData['state']);
    //                 }
    //                 if ($locationData['country']) {
    //                     $propertyQuery = $propertyQuery->clone()->where('country', $locationData['country']);
    //                 }
    //             }
    //         }

    //         // Get Final Data
    //         $propertiesData = $propertyQuery->get()->map(function ($property) use ($propertyMapper) {
    //             $property = $propertyMapper($property);
    //             return new CustomerResource($property, ['is_agent', 'is_user_verified', 'is_agent_verified', 'agent_verification_status', 'user_verification_status', 'become_agent_status']);
    //         });

    //         // Pass data as json
    //         if ($propertiesData->isNotEmpty()) {
    //             ApiResponseService::successResponse("Data Fetched Successfully", $propertiesData);
    //         } else {
    //             ApiResponseService::successResponse("No Data Found", array());
    //         }
    //     } catch (Exception $e) {
    //         ApiResponseService::errorResponse($e->getMessage());
    //     }
    // }

    public function getPropertiesOnMap(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'category_id' => 'nullable|integer',
            'property_type' => 'nullable|in:0,1,2,3',
        ]);

        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {

            // ✅ Property Mapper
            $propertyMapper = function ($propertyData) {
                $propertyData->promoted = $propertyData->is_promoted ?? false;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->parameters = $propertyData->parameters ?? [];
                $propertyData->is_premium = $propertyData->is_premium == 1;

                if ($propertyData->category) {
                    $propertyData->category->translated_name = $propertyData->category->translated_name ?? '';
                }

                $propertyData->translated_title = $propertyData->translated_title ?? '';
                $propertyData->translated_description = $propertyData->translated_description ?? '';

                unset($propertyData->propery_type);

                return $propertyData;
            };

            // ✅ Base Query
            $propertyQuery = Property::select(
                'id',
                'slug_id',
                'category_id',
                'city',
                'state',
                'country',
                'price',
                'propery_type',
                'title',
                'title_image',
                'is_premium',
                'rentduration',
                'latitude',
                'longitude',
                'role_context',
                'created_at',
                'added_by'
            )
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'translations',

                    // 🔥 IMPORTANT: customer relations
                    'customer.verifyCustomer',
                    'customer.verifyAgent',
                    'customer.becomeAgent',
                    'customer.property',
                    'customer.agent_availabilities',
                ])
                ->onlyActive()
                ->whereIn('propery_type', [0, 1]);

            // ✅ Filters

            if ($request->filled('role_context')) {
                $propertyQuery->where('role_context', $request->role_context);
            }

            if ($request->has('property_type') && $request->property_type !== '') {
                $propertyQuery->where('propery_type', $request->property_type);
            }

            if ($request->filled('category_id')) {
                $propertyQuery->where('category_id', $request->category_id);
            }

            if ($request->filled('parameter_id')) {
                $ids = explode(',', $request->parameter_id);

                $propertyQuery->whereHas('assignParameter', function ($q) use ($ids) {
                    $q->whereIn('parameter_id', $ids)->whereNotNull('value');
                });
            }

            if ($request->filled('category_slug_id')) {
                $propertyQuery->whereHas('category', function ($q) use ($request) {
                    $q->where('slug_id', $request->category_slug_id);
                });
            }

            if ($request->filled('country')) {
                $propertyQuery->where('country', $request->country);
            }

            if ($request->filled('state')) {
                $propertyQuery->where('state', $request->state);
            }

            if ($request->filled('city')) {
                $propertyQuery->where('city', $request->city);
            }

            // ✅ Place ID
            if ($request->filled('place_id')) {
                $location = $this->resolvePlaceIdToLocation($request->place_id);

                if ($location) {
                    if ($location['city']) {
                        $propertyQuery->where('city', $location['city']);
                    }
                    if ($location['state']) {
                        $propertyQuery->where('state', $location['state']);
                    }
                    if ($location['country']) {
                        $propertyQuery->where('country', $location['country']);
                    }
                }
            } else {
                if ($request->filled('latitude') && $request->filled('longitude')) {
                    $propertyQuery->where('latitude', $request->latitude)
                        ->where('longitude', $request->longitude);
                }
            }

            // ✅ Price
            if ($request->filled('min_price')) {
                $propertyQuery->where('price', '>=', $request->min_price);
            }

            if ($request->filled('max_price')) {
                $propertyQuery->where('price', '<=', $request->max_price);
            }

            // ✅ Posted Since
            if ($request->has('posted_since')) {

                switch ($request->posted_since) {
                    case 0:
                        $propertyQuery->whereBetween('created_at', [Carbon::now()->subWeek(), now()]);
                        break;

                    case 1:
                        $propertyQuery->whereDate('created_at', Carbon::yesterday());
                        break;

                    case 2:
                        $propertyQuery->whereBetween('created_at', [Carbon::now()->subMonth(), now()]);
                        break;

                    case 3:
                        $propertyQuery->whereBetween('created_at', [Carbon::now()->subMonths(3), now()]);
                        break;

                    case 4:
                        $propertyQuery->whereBetween('created_at', [Carbon::now()->subMonths(6), now()]);
                        break;
                }
            }

            // ✅ Search
            if ($request->filled('search')) {
                $search = $request->search;

                $propertyQuery->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%$search%")
                        ->orWhere('address', 'LIKE', "%$search%")
                        ->orWhereHas('category', function ($q1) use ($search) {
                            $q1->where('category', 'LIKE', "%$search%");
                        });
                });
            }

            // ✅ Promoted
            if ($request->promoted == 1) {
                $propertyQuery->whereHas('advertisement', function ($q) {
                    $q->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // ✅ Premium
            if ($request->get_all_premium_properties == 1) {
                $propertyQuery->where('is_premium', 1);
            }

            // ✅ Sorting
            if ($request->most_viewed == 1) {
                $propertyQuery->orderBy('total_click', 'DESC');
            } elseif ($request->most_liked == 1) {
                $propertyQuery->orderBy('favourite_count', 'DESC');
            } else {
                $propertyQuery->orderBy('id', 'DESC');
            }

            // ✅ Final Data
            $properties = $propertyQuery->get()->map(function ($property) use ($propertyMapper) {

                $property = $propertyMapper($property);
                $customer = $property->customer;

                // 🔥 ADD YOUR 6 PARAMS
                $property->is_agent = $customer?->is_agent ?? false;

                $property->is_user_verified =
                    $customer?->verifyCustomer?->status === 'approved';

                $property->become_agent_status =
                    $customer?->becomeAgent?->status ?? 'not_applied';

                $property->agent_verification_status =
                    $customer?->verifyAgent?->status ?? 'not_applied';

                $property->user_verification_status =
                    $customer?->verifyCustomer?->status ?? 'not_applied';

                $property->is_appointment_available =
                    $customer?->verifyCustomer?->status === 'approved' &&
                    $customer?->properties?->where('status', 1)
                        ->where('request_status', 'approved')
                        ->whereIn('propery_type', [0, 1])
                        ->isNotEmpty() &&
                    $customer?->agent_availabilities?->where('is_active', 1)
                        ->isNotEmpty();

                $property->added_by = $property->getRawOriginal('added_by');

                return $property;
            });

            if ($properties->isNotEmpty()) {
                return ApiResponseService::successResponse('Data Fetched Successfully', $properties);
            }

            return ApiResponseService::successResponse('No Data Found', []);

        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function compareProperties(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'source_property_id' => 'required|exists:propertys,id',
                'target_property_id' => 'required|exists:propertys,id',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $sourcePropertyId = $request->source_property_id;
            $targetPropertyId = $request->target_property_id;

            $propertyBaseQuery = Property::where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
            })->select('id', 'category_id', 'title', 'city', 'state', 'country', 'address', 'price', 'propery_type', 'total_click', 'rentduration', 'is_premium', 'title_image')->with('category:id,slug_id,image,category', 'category.translations', 'translations');
            $sourceProperty = $propertyBaseQuery->clone()->where('id', $sourcePropertyId)->first();
            $targetProperty = $propertyBaseQuery->clone()->where('id', $targetPropertyId)->first();
            if (empty($sourceProperty)) {
                return ApiResponseService::errorResponse('Source property not found');
            }
            if (empty($targetProperty)) {
                return ApiResponseService::errorResponse('Target property not found');
            }

            if ($sourceProperty->category_id != $targetProperty->category_id) {
                return ApiResponseService::errorResponse('Properties are not in the same category');
            }
            if ($sourceProperty->id == $targetProperty->id) {
                return ApiResponseService::errorResponse('Source and target property cannot be the same');
            }
            if ($sourceProperty->is_premium == 1) {
                if (collect(Auth::guard('sanctum')->user())->isEmpty()) {
                    return ApiResponseService::errorResponse('Source property is a premium property');
                } else {
                    $data = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true, true, $request->user_active_role);
                    if (($data['package_available'] == false || $data['feature_available'] == false) && $data['limit_available'] == false) {
                        ApiResponseService::validationError('Source property is a premium property', $data);
                    }
                }
            }
            if ($targetProperty->is_premium == 1) {
                if (collect(Auth::guard('sanctum')->user())->isEmpty()) {
                    return ApiResponseService::errorResponse('Target property is a premium property');
                } else {
                    $data = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true, true, $request->user_active_role);
                    if (($data['package_available'] == false || $data['feature_available'] == false) && $data['limit_available'] == false) {
                        ApiResponseService::validationError('Target property is a premium property', $data);
                    }
                }
            }

            if (! $sourceProperty || ! $targetProperty) {
                return ApiResponseService::errorResponse('One or both properties not found');
            }

            $sourcePropertyData = $this->getPropertyData($sourceProperty);
            $targetPropertyData = $this->getPropertyData($targetProperty);

            // 👇 get current user once
            $currentUser = Auth::guard('sanctum')->user();

            // 👇 function to append params
            $appendParams = function ($property) use ($currentUser) {
                $property['is_agent'] = $currentUser?->is_agent ?? 0;
                $property['is_user_verified'] = $currentUser?->is_user_verified ?? 0;
                $property['is_agent_verified'] = $currentUser?->is_agent_verified ?? 0;

                $property['become_agent_status'] = AgentVerification::where('customer_id', $currentUser?->id)
                    ->where('form_type', 'become_agent')
                    ->first()?->status ?? '';

                $property['agent_verification_status'] = AgentVerification::where('customer_id', $currentUser?->id)
                    ->where('form_type', 'verify_agent')
                    ->first()?->status ?? '';

                $property['user_verification_status'] = VerifyCustomer::where('user_id', $currentUser?->id)
                    ->first()?->status ?? '';

                return $property;
            };

            // 👇 apply to both
            $sourcePropertyData = $appendParams($sourcePropertyData);
            $targetPropertyData = $appendParams($targetPropertyData);
            $data = [
                'source_property' => $sourcePropertyData,
                'target_property' => $targetPropertyData,
            ];

            return ApiResponseService::successResponse('Properties compared successfully', $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getAllSimilarProperties(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'property_id' => 'required|exists:propertys,id',
                'search' => 'nullable|string',
                'offset' => 'nullable|integer',
                'limit' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 20;
            $getRequestProperty = Property::findOrFail($request->property_id);

            $getAllSimilarProperties = Property::where('id', '!=', $request->property_id)
                ->whereIn('propery_type', [0, 1])
                ->onlyActive()
                ->where('category_id', $getRequestProperty->category_id)
                ->where('role_context', $getRequestProperty->role_context ?? 'user')
                ->select(
                    'id',
                    'slug_id',
                    'category_id',
                    'city',
                    'state',
                    'country',
                    'price',
                    'propery_type',
                    'title',
                    'title_image',
                    'is_premium',
                    'address',
                    'rentduration',
                    'latitude',
                    'longitude',
                    'role_context'
                )
                ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                ->when($request->has('search'), function ($query) use ($request) {
                    $query->where('title', 'like', '%'.$request->search.'%');
                })
                ->when($request->has('offset'), function ($query) use ($offset) {
                    $query->offset($offset);
                })
                ->when($request->has('limit'), function ($query) use ($limit) {
                    $query->limit($limit);
                })
                ->get()
                ->map(function ($propertyData) {

                    // existing logic
                    if ($propertyData->category) {
                        $propertyData->category->translated_name = $propertyData->category->translated_name;
                    }

                    $propertyData->translated_title = $propertyData->translated_title;
                    $propertyData->translated_description = $propertyData->translated_description;
                    $propertyData->promoted = $propertyData->is_promoted;
                    $propertyData->property_type = $propertyData->propery_type;
                    $propertyData->parameters = $propertyData->parameters;
                    $propertyData->is_premium = $propertyData->is_premium == 1;

                    // 👇 ADD THIS BLOCK
                    $currentUser = Auth::guard('sanctum')->user();

                    $propertyData->is_agent = $currentUser?->is_agent ?? 0;
                    $propertyData->is_user_verified = $currentUser?->is_user_verified ?? 0;
                    $propertyData->is_agent_verified = $currentUser?->is_agent_verified ?? 0;

                    $propertyData->become_agent_status = AgentVerification::where('customer_id', $currentUser?->id)
                        ->where('form_type', 'become_agent')
                        ->first()?->status ?? '';

                    $propertyData->agent_verification_status = AgentVerification::where('customer_id', $currentUser?->id)
                        ->where('form_type', 'verify_agent')
                        ->first()?->status ?? '';

                    $propertyData->user_verification_status = VerifyCustomer::where('user_id', $currentUser?->id)
                        ->first()?->status ?? '';

                    return $propertyData;
                });

            return ApiResponseService::successResponse('Similar properties fetched successfully', $getAllSimilarProperties);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function interested_users(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required',
            'type' => 'required',

        ]);
        if (! $validator->fails()) {
            $current_user = Auth::user()->id;

            $interested_user = InterestedUser::where('customer_id', $current_user)->where('property_id', $request->property_id);

            if ($request->type == 1) {

                if (count($interested_user->get()) > 0) {
                    $response['error'] = false;
                    $response['message'] = trans('Already Added To Interested Users');
                } else {
                    $interested_user = new InterestedUser;
                    $interested_user->property_id = $request->property_id;
                    $interested_user->customer_id = $current_user;
                    $interested_user->save();
                    $response['error'] = false;
                    $response['message'] = trans('Interested Users Added Successfully');
                }
            }
            if ($request->type == 0) {

                if (count($interested_user->get()) == 0) {
                    $response['error'] = false;
                    $response['message'] = trans('No Data Found To Delete');
                } else {
                    $interested_user->delete();

                    $response['error'] = false;
                    $response['message'] = trans('Interested Users Removed Successfully');
                }
            }
        } else {
            $response['error'] = true;
            $response['message'] = $validator->errors()->first();
        }

        return response()->json($response);
    }

    public function getInterestedUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'property_id' => 'required_without:slug_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->errors()->first(),
                ]);
            }

            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            if (isset($request->slug_id)) {
                $property = Property::where('slug_id', $request->slug_id)->first();
                $property_id = $property->id;
            } else {
                $property_id = $request->property_id;
            }

            $interestedUserQuery = InterestedUser::has('customer')->with('customer:id,name,profile,email,mobile')->where('property_id', $property_id);
            $totalData = $interestedUserQuery->clone()->count();
            $interestedData = $interestedUserQuery->take($limit)->skip($offset)->get()->map(function ($interestedData) {
                if (env('DEMO_MODE') && Auth::check() != false && Auth::user()->email != 'superadmin@gmail.com') {
                    $interestedData->customer->email = '****************************';
                }

                return $interestedData;
            });
            if (collect($interestedData)->isNotEmpty()) {
                $data = $interestedData->pluck('customer');
                ApiResponseService::successResponse('Data Fetched Successfully', $data, ['total' => $totalData]);
            } else {
                ApiResponseService::successResponse('No Data Found');
            }
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function user_interested_property(Request $request)
    {

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 25;

        $current_user = Auth::user()->id;

        $favourite = InterestedUser::where('customer_id', $current_user)->select('property_id')->get();
        $arr = [];
        foreach ($favourite as $p) {
            $arr[] = $p->property_id;
        }
        $property_details = Property::whereIn('id', $arr)->with('category:id,category')->with('parameters');
        $result = $property_details->orderBy('id', 'ASC')->skip($offset)->take($limit)->get();

        $total = $result->count();

        if (! $result->isEmpty()) {
            foreach ($property_details as $row) {
                if (filter_var($row->image, FILTER_VALIDATE_URL) === false) {
                    $row->image = ($row->image != '') ? url('').config('global.IMG_PATH').config('global.PROPERTY_TITLE_IMG_PATH').$row->image : '';
                } else {
                    $row->image = $row->image;
                }
            }
            $response['error'] = false;
            $response['message'] = trans('Data Fetched Successfully');
            $response['data'] = $result;
            $response['total'] = $total;
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return response()->json($response);
    }

    public function get_user_recommendation(Request $request)
    {
        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;
        $current_user = Auth::user()->id;

        $user_interest = UserInterest::where('user_id', $current_user)->first();
        if (collect($user_interest)->isNotEmpty()) {

            $property = Property::with('customer')->with('user')->with('category:id,category,image', 'category.translations')->with('assignfacilities.outdoorfacilities')->with('favourite')->with('parameters')->with('interested_users')->onlyActive();

            $property_type = $request->property_type;
            if ($user_interest->category_ids != '') {

                $category_ids = explode(',', $user_interest->category_ids);

                $property = $property->whereIn('category_id', $category_ids);
            }

            if ($user_interest->price_range != '') {

                $max_price = explode(',', $user_interest->price_range)[1];

                $min_price = explode(',', $user_interest->price_range)[0];

                if (isset($max_price) && isset($min_price)) {
                    $min_price = floatval($min_price);
                    $max_price = floatval($max_price);

                    $property = $property->where(function ($query) use ($min_price, $max_price) {
                        $query->whereRaw('CAST(price AS DECIMAL(10, 2)) >= ?', [$min_price])
                            ->whereRaw('CAST(price AS DECIMAL(10, 2)) <= ?', [$max_price]);
                    });
                }
            }

            if ($user_interest->city != '') {
                $city = $user_interest->city;
                $property = $property->where('city', $city);
            }
            if ($user_interest->property_type != '') {
                $property_type = explode(',', $user_interest->property_type);
            }
            if ($user_interest->outdoor_facilitiy_ids != '') {

                $outdoor_facilitiy_ids = explode(',', $user_interest->outdoor_facilitiy_ids);
                $property = $property->whereHas('assignfacilities.outdoorfacilities', function ($q) use ($outdoor_facilitiy_ids) {
                    $q->whereIn('id', $outdoor_facilitiy_ids);
                });
            }

            if (isset($property_type)) {
                if (count($property_type) == 2) {
                    $property_type = $property->where(function ($query) use ($property_type) {
                        $query->where('propery_type', $property_type[0])->orWhere('propery_type', $property_type[1]);
                    });
                } else {
                    if (isset($property_type[0]) && $property_type[0] == 0) {

                        $property = $property->where('propery_type', $property_type[0]);
                    }
                    if (isset($property_type[0]) && $property_type[0] == 1) {

                        $property = $property->where('propery_type', $property_type[0]);
                    }
                }
            }

            $total = $property->get()->count();

            $result = $property->skip($offset)->take($limit)->get()->map(function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }

                return $item;
            });
            $property_details = get_property_details($result, $current_user, true);

            if (! empty($result)) {
                $response['error'] = false;
                $response['message'] = trans('Data Fetched Successfully');
                $response['total'] = $total;
                $response['data'] = $property_details;
            } else {

                $response['error'] = false;
                $response['message'] = trans('No Data Found');
                $response['data'] = [];
            }
        } else {
            $response['error'] = false;
            $response['message'] = trans('No Data Found');
            $response['data'] = [];
        }

        return $response;
    }

    public function propertyAdvanceFilterData()
    {
        try {
            $cacheKey = 'propertyAdvanceFilterData';
            $cachedData = Cache::get($cacheKey);
            if (! is_null($cachedData)) {
                return ApiResponseService::successResponse('Data Fetched Successfully', $cachedData);
            }

            // Get property ids
            $propertyQuery = Property::where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
            })->whereIn('propery_type', [0, 1]);
            $propertyIds = $propertyQuery->pluck('id');

            // Get nearby facilities ids
            $nearbyFacilitiesId = AssignedOutdoorFacilities::whereIn('property_id', $propertyIds)->pluck('facility_id');

            // Get nearby facilities
            $nearbyFacilities = OutdoorFacilities::whereIn('id', $nearbyFacilitiesId)->with('translations')->get()->map(function ($nearbyFacility) {
                $nearbyFacility->translated_name = $nearbyFacility->translated_name;

                return $nearbyFacility;
            });

            // Get parameter ids of all categories
            $categoryIds = $propertyQuery->pluck('category_id');
            $facilititesIds = [];
            $facilitiesOfCategory = Category::whereIn('id', $categoryIds)->where('status', 1)->get()->pluck('parameter_types');
            foreach ($facilitiesOfCategory as $facility) {
                $facility = explode(',', $facility);
                $facility = array_filter($facility);
                $facility = array_unique($facility);
                $facility = array_values($facility);
                $facilititesIds = array_merge($facilititesIds, $facility);
            }

            // Get parameters
            $parameters = parameter::whereIn('id', $facilititesIds)->with('translations')->get()->map(function ($parameter) {
                $parameter->translated_name = $parameter->translated_name;
                $parameter->translated_option_value = $parameter->translated_option_value;

                return $parameter;
            });

            // Array of parameters and nearby facilities
            $propertyAdvanceFilterData = [
                'parameters' => $parameters,
                'nearby_facilities' => $nearbyFacilities,
            ];

            // Cache the data
            Cache::put($cacheKey, $propertyAdvanceFilterData, now()->addMinute(10));
            ApiResponseService::successResponse('Data Fetched Successfully', $propertyAdvanceFilterData);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function add_reports(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason_id' => 'required',
            'property_id' => 'required',
        ]);
        $current_user = Auth::user()->id;
        if (! $validator->fails()) {
            $report_count = user_reports::where('property_id', $request->property_id)->where('customer_id', $current_user)->get();
            if (! count($report_count)) {
                $report_reason = new user_reports;
                $report_reason->reason_id = $request->reason_id ? $request->reason_id : 0;
                $report_reason->property_id = $request->property_id;
                $report_reason->customer_id = $current_user;
                $report_reason->other_message = $request->other_message ? $request->other_message : '';

                $report_reason->save();

                $response['error'] = false;
                $response['message'] = trans('Report Submitted Successfully');
            } else {
                $response['error'] = false;
                $response['message'] = trans('Already Reported');
            }
        } else {
            $response['error'] = true;
            $response['message'] = trans('Please Fill All Data And Submit');
        }

        return response()->json($response);
    }

    private function getPropertyData($property)
    {
        $propertyData = [
            'id' => $property->id,
            'title' => $property->title,
            'city' => $property->city,
            'state' => $property->state,
            'country' => $property->country,
            'is_premium' => $property->is_premium,
            'promoted' => $property->is_promoted,
            'home_promoted' => $property->home_promoted,
            'list_promoted' => $property->list_promoted,
            'title_image' => $property->title_image,
            'address' => $property->address,
            'created_at' => $property->created_at,
            'price' => $property->price,
            'rentduration' => ! empty($property->rentduration) ? $property->rentduration : null,
            'property_type' => $property->propery_type,
            'total_likes' => $property->favourite()->count(),
            'total_views' => $property->total_click,
            'facilities' => $property->parameters,
            'near_by_places' => $property->assign_facilities,
            'category' => [
                'id' => $property->category->id,
                'name' => $property->category->name,
                'image' => $property->category->image,
                'translated_name' => $property->category->translated_name,
            ],
            'translated_title' => $property->translated_title,
            'translated_description' => $property->translated_description,
        ];

        return $propertyData;
    }

    private function resolvePlaceIdToLocation($placeId)
    {
        $googleApiKey = env('PLACE_API_KEY');
        $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'address_components',
            'key' => $googleApiKey,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $location = [
                'city' => null,
                'state' => null,
                'country' => null,
            ];

            if (isset($data['result']) && ! empty($data['result'])) {
                foreach ($data['result']['address_components'] as $component) {
                    $types = $component['types'];

                    // Extract city (locality)
                    if (in_array('locality', $types)) {
                        $location['city'] = $component['long_name'];
                    }
                    // Extract state (administrative_area_level_1)
                    elseif (in_array('administrative_area_level_1', $types)) {
                        $location['state'] = $component['long_name'];
                    }
                    // Extract country
                    elseif (in_array('country', $types)) {
                        $location['country'] = $component['long_name'];
                    }
                }
            } else {
                Log::error($data);
            }

            return $location;
        }

        return null;
    }
}
