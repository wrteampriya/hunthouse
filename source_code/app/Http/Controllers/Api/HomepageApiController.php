<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdBanner;
use App\Models\Article;
use App\Models\Category;
use App\Models\CityImage;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\HomepageSection;
use App\Models\Projects;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\User;
use App\Models\UserInterest;
use App\Services\ApiResponseService;
use App\Services\HelperService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HomepageApiController extends Controller
{
    public function getHomepagePropertiesByCity()
    {
        try {
            $cityImageStyle = Setting::where('type', 'city_image_style')->first();
            $cityImageStyle = $cityImageStyle->data ?? 'style_1';
            $withImage = $cityImageStyle == 'style_1' ? true : false;
            $propertiesByCitiesSection = config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TYPE');
            $homepageSectionData = HomepageSection::where('section_type', $propertiesByCitiesSection)->where('is_active', 1)->first();
            if ($homepageSectionData) {
                $citiesData = CityImage::where('status', 1)->withCount(['property' => function ($query) {
                    $query->whereIn('propery_type', [0, 1])->onlyActive();
                }])->having('property_count', '>', 0)->orderBy('property_count', 'DESC')->limit(12)->get();
                $propertiesByCities = [];
                foreach ($citiesData as $city) {
                    if ($withImage) {
                        if (! empty($city->getRawOriginal('image'))) {
                            $url = $city->image;
                            $relativePath = parse_url($url, PHP_URL_PATH);
                            if (file_exists(public_path().$relativePath)) {
                                array_push($propertiesByCities, ['City' => $city->city, 'Count' => $city->property_count, 'image' => $city->image]);

                                continue;
                            }
                        }
                        $resultArray = $this->getUnsplashData($city);
                        array_push($propertiesByCities, $resultArray);
                    } else {
                        array_push($propertiesByCities, ['City' => $city->city, 'Count' => $city->property_count, 'image' => '']);
                    }
                }
                $data = [
                    'section_id' => $homepageSectionData->id,
                    'section_title' => $homepageSectionData->title,
                    'translated_title' => $homepageSectionData->translated_title,
                    'section_type' => $propertiesByCitiesSection,
                    'with_image' => $withImage,
                    'data' => $propertiesByCities ?? [],
                ];
            }
            ApiResponseService::successResponse(trans('Data Fetched Successfully'), $data ?? []);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getHomepagePropertiesOnMap(Request $request)
    {
        try {
            $latitude = $request->has('latitude') ? $request->latitude : null;
            $longitude = $request->has('longitude') ? $request->longitude : null;
            $radius = $request->has('radius') ? $request->radius : null;
            $locationBasedDataAvailable = false;

            $propertiesOnMapSection = config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TYPE');
            $homepageSectionData = HomepageSection::where('section_type', $propertiesOnMapSection)->where('is_active', 1)->first();

            if ($homepageSectionData) {
                $propertyBaseQuery = Property::select(
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
                    'added_by',
                    'role_context',
                    'description'
                )
                    ->with(['category:id,slug_id,image,category', 'category.translations', 'translations'])
                    ->onlyActive()
                    ->whereIn('propery_type', [0, 1])
                    ->when($request->filled('role_context'), function ($query) use ($request) {
                        return $query->where('role_context', $request->role_context);
                    });

                $locationBasedPropertyQuery = clone $propertyBaseQuery;
                if ($latitude && $longitude) {
                    if ($radius) {
                        $locationBasedPropertyQuery->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$latitude, $longitude, $latitude])
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->having('distance', '<', $radius);
                    } else {
                        $locationBasedPropertyQuery->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    }

                    if ($locationBasedPropertyQuery->exists()) {
                        $locationBasedDataAvailable = true;
                    } else {
                        $locationBasedPropertyQuery = clone $propertyBaseQuery;
                    }
                }
                $propertyMapper = function ($propertyData) {
                    $propertyData->promoted = $propertyData->is_promoted;
                    $propertyData->property_type = $propertyData->propery_type;
                    $propertyData->is_premium = $propertyData->is_premium == 1;
                    $propertyData->parameters = $propertyData->parameters;
                    $propertyData->category->translated_name = $propertyData->category->translated_name;
                    $propertyData->translated_title = $propertyData->translated_title;
                    $propertyData->translated_description = $propertyData->translated_description;

                    return $propertyData;
                };
                $propertiesOnMap = $locationBasedPropertyQuery->clone()->get()->map($propertyMapper);
                $data = [
                    'section_id' => $homepageSectionData->id,
                    'section_type' => $propertiesOnMapSection,
                    'section_title' => $homepageSectionData->title,
                    'translated_title' => $homepageSectionData->translated_title,
                    'data' => $propertiesOnMap ?? [],
                ];
            }
            ApiResponseService::successResponse(trans('Data Fetched Successfully'), $data ?? [], ['location_based_data_available' => $locationBasedDataAvailable]);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAdBanners(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'page' => 'required|in:homepage,property_listing,property_detail',
                'platform' => 'required|in:app,web',
            ],
            [
                'page.required' => trans('The page field is required.'),
                'page.in' => trans('The page field must be a valid page.'),
                'platform.required' => trans('The platform field is required.'),
                'platform.in' => trans('The platform field must be a valid platform.'),
            ]
        );
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $page = $request->page;
            $platform = $request->platform;
            $now = now();
            $now = now();

            $adBanners = AdBanner::where('is_active', 1)
                ->where(['page' => $page, 'platform' => $platform])
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->with('property:id,title,slug_id')
                ->inRandomOrder()
                ->get()
                ->groupBy('placement')
                ->map(fn ($group) => $group->first())
                ->values(); // removes keys, returns flat array

            if (empty($adBanners)) {
                return ApiResponseService::validationError(trans('No ad banners found.'));
            }
            ApiResponseService::successResponse(trans('Data Fetched Successfully'), $adBanners);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepageSectionsData(Request $request)
    {
        try {
            $sections = HomepageSection::where('is_active', 1)
                ->orderBy('sort_order')
                ->with('translations')
                ->get()
                ->map(function ($section) {
                    // dd(gettype($section->sort_order));
                    return [
                        'id' => $section->id,
                        'type' => $section->section_type,
                        'title' => $section->title,
                        'translated_title' => $section->translated_title,
                        'sort_order' => $section->sort_order,
                        'is_active' => $section->is_active,
                    ];
                });

            ApiResponseService::successResponse('Homepage Sections Data Fetched Successfully', $sections);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepagePropertySections(Request $request)
    {
        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10;

            $nearbyPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TYPE');
            $featuredPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TYPE');
            $mostViewedPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TYPE');
            $mostLikedPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TYPE');
            $premiumPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TYPE');

            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $nearbyPropertyHomepageSection,
                    $featuredPropertyHomepageSection,
                    $mostViewedPropertyHomepageSection,
                    $mostLikedPropertyHomepageSection,
                    $premiumPropertyHomepageSection,
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order,
                        ],
                    ];
                });

            // Build optimized property base query once
            $propertyBaseQuery = Property::select(
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
                'added_by',
                'role_context',
                'description',
                'total_click'
            )
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'translations',
                ])
                ->onlyActive()
                ->whereIn('propery_type', [0, 1])
                ->when($request->filled('role_context'), function ($query) use ($request) {
                    return $query->where('role_context', $request->role_context);
                });

            // Apply location filter once
            $locationBasedPropertyQuery = HelperService::applyLocationFilterToListings($propertyBaseQuery, $latitude, $longitude, $radius);

            $propertyMapper = function ($propertyData) {
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->is_premium = $propertyData->is_premium == 1;
                $propertyData->parameters = $propertyData->parameters;
                if ($propertyData->category) {
                    $propertyData->category->translated_name = $propertyData->category->translated_name;
                }
                $propertyData->translated_title = $propertyData->translated_title;
                $propertyData->translated_description = $propertyData->translated_description;

                return $propertyData;
            };

            $data = [];

            // Nearby Properties
            $data['nearby_properties'] = [
                'section_id' => $homepageData[$nearbyPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->inRandomOrder()->limit(12)->get()->map($propertyMapper),
            ];

            // Featured Properties
            $data['featured_properties'] = [
                'section_id' => $homepageData[$featuredPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->whereHas('advertisement', function ($subQuery) {
                    $subQuery->where(['is_enable' => 1, 'status' => 0])
                        ->whereNot('type', 'Slider');
                })->inRandomOrder()->limit(4)->get()->map($propertyMapper),
            ];

            // Most Viewed Properties
            $data['most_viewed_properties'] = [
                'section_id' => $homepageData[$mostViewedPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->orderBy('total_click', 'DESC')->limit(12)->get()->map($propertyMapper),
            ];

            // Most Liked Properties
            $data['most_liked_properties'] = [
                'section_id' => $homepageData[$mostLikedPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->withCount('favourite')->orderBy('favourite_count', 'DESC')->limit(12)->get()->map($propertyMapper),
            ];

            // Premium Properties
            $data['premium_properties'] = [
                'section_id' => $homepageData[$premiumPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->where('is_premium', 1)->inRandomOrder()->limit(12)->get()->map($propertyMapper),
            ];

            // Compute location flag and fallback to global if all property sections are empty for given location
            $locationBasedDataProperties = false;
            if ($latitude && $longitude) {
                $hasNearby = isset($data['nearby_properties']['data']) && $data['nearby_properties']['data']->count() > 0;
                $hasFeatured = isset($data['featured_properties']['data']) && $data['featured_properties']['data']->count() > 0;
                $hasMostViewed = isset($data['most_viewed_properties']['data']) && $data['most_viewed_properties']['data']->count() > 0;
                $hasMostLiked = isset($data['most_liked_properties']['data']) && $data['most_liked_properties']['data']->count() > 0;
                $hasPremium = isset($data['premium_properties']['data']) && $data['premium_properties']['data']->count() > 0;

                if ($hasNearby || $hasFeatured || $hasMostViewed || $hasMostLiked || $hasPremium) {
                    $locationBasedDataProperties = true;
                } else {
                    // Rebuild all property sections from global (no location filter)
                    $globalPropertyQuery = $propertyBaseQuery->clone();

                    $data['nearby_properties'] = [
                        'section_id' => $homepageData[$nearbyPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->inRandomOrder()->limit(12)->get()->map($propertyMapper),
                    ];

                    $data['featured_properties'] = [
                        'section_id' => $homepageData[$featuredPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->whereHas('advertisement', function ($subQuery) {
                            $subQuery->where(['is_enable' => 1, 'status' => 0])
                                ->whereNot('type', 'Slider');
                        })->inRandomOrder()->limit(4)->get()->map($propertyMapper),
                    ];

                    $data['most_viewed_properties'] = [
                        'section_id' => $homepageData[$mostViewedPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->orderBy('total_click', 'DESC')->limit(12)->get()->map($propertyMapper),
                    ];

                    $data['most_liked_properties'] = [
                        'section_id' => $homepageData[$mostLikedPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->withCount('favourite')->orderBy('favourite_count', 'DESC')->limit(12)->get()->map($propertyMapper),
                    ];

                    $data['premium_properties'] = [
                        'section_id' => $homepageData[$premiumPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->where('is_premium', 1)->inRandomOrder()->limit(12)->get()->map($propertyMapper),
                    ];
                }
            }

            $data['location_based_data'] = $locationBasedDataProperties;

            ApiResponseService::successResponse('Property Sections Fetched Successfully', $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepageProjectSections(Request $request)
    {
        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10;

            $projectsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TYPE');
            $featuredProjectsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TYPE');
            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $projectsHomepageSection,
                    $featuredProjectsHomepageSection,
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order,
                        ],
                    ];
                });

            // Build optimized projects base query once
            $projectsBaseQuery = Projects::select(
                'id',
                'slug_id',
                'city',
                'state',
                'country',
                'title',
                'type',
                'image',
                'location',
                'category_id',
                'added_by',
                'role_context',
                'latitude',
                'longitude'
            )
                ->onlyActive()
                ->when($request->filled('role_context'), function ($query) use ($request) {
                    return $query->where('role_context', $request->role_context);
                })
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'gallary_images:id,project_id,name',
                    'customer:id,name,profile,email,mobile',
                    'translations',
                ]);

            // Apply location filter once
            $locationBasedProjectsQuery = HelperService::applyLocationFilterToListings($projectsBaseQuery, $latitude, $longitude, $radius);

            $projectMapper = function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                $item->translated_title = $item->translated_title;
                $item->translated_description = $item->translated_description;

                return $item;
            };

            $data = [];

            // Regular Projects
            $data['projects'] = [
                'section_id' => $homepageData[$projectsHomepageSection]['id'] ?? null,
                'data' => $locationBasedProjectsQuery->clone()->inRandomOrder()->limit(12)->get()->map($projectMapper),
            ];

            // Featured Projects
            $data['featured_projects'] = [
                'section_id' => $homepageData[$featuredProjectsHomepageSection]['id'] ?? null,
                'data' => $locationBasedProjectsQuery->clone()
                    ->whereHas('advertisement', function ($query) {
                        $query->where(['is_enable' => 1, 'status' => 0]);
                    })->inRandomOrder()->limit(12)->get()->map($projectMapper),
            ];

            // Compute location flag and fallback to global if all project sections are empty for given location
            $locationBasedDataProjects = false;
            if ($latitude && $longitude) {
                $hasProjects = isset($data['projects']['data']) && $data['projects']['data']->count() > 0;
                $hasFeaturedProjects = isset($data['featured_projects']['data']) && $data['featured_projects']['data']->count() > 0;

                if ($hasProjects || $hasFeaturedProjects) {
                    $locationBasedDataProjects = true;
                } else {
                    // Rebuild projects sections from global (no location filter)
                    $globalProjectsQuery = $projectsBaseQuery->clone();

                    $data['projects'] = [
                        'section_id' => $homepageData[$projectsHomepageSection]['id'] ?? null,
                        'data' => $globalProjectsQuery->clone()->inRandomOrder()->limit(12)->get()->map($projectMapper),
                    ];

                    $data['featured_projects'] = [
                        'section_id' => $homepageData[$featuredProjectsHomepageSection]['id'] ?? null,
                        'data' => $globalProjectsQuery->clone()->whereHas('advertisement', function ($query) {
                            $query->where(['is_enable' => 1, 'status' => 0]);
                        })->inRandomOrder()->limit(12)->get()->map($projectMapper),
                    ];
                }
            }

            $data['location_based_data'] = $locationBasedDataProjects;

            ApiResponseService::successResponse('Project Sections Fetched Successfully', $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepageOtherSections(Request $request)
    {
        try {
            $latitude = $request->latitude != 'null' ? $request->latitude : null;
            $longitude = $request->longitude != 'null' ? $request->longitude : null;
            $radius = $request->radius != 'null' ? $request->radius : null;
            $locationBasedData = false;

            $categoriesHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TYPE');
            $agentsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TYPE');
            $articlesHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TYPE');
            $userRecommendationsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TYPE');
            $faqsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TYPE');
            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $categoriesHomepageSection,
                    $agentsHomepageSection,
                    $articlesHomepageSection,
                    $userRecommendationsHomepageSection,
                    $faqsHomepageSection,
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order,
                        ],
                    ];
                });

            $data = [];

            // Categories Section
            $categoriesQuery = Category::select('id', 'category', 'image', 'slug_id')->where('status', 1);

            // Add whereHas condition for location filtering
            if ($latitude && $longitude) {
                if ($radius && ! empty($radius)) {
                    $categoriesQuery->whereExists(function ($query) use ($latitude, $longitude, $radius) {
                        $query->select(DB::raw(1))
                            ->from('propertys')
                            ->whereRaw('categories.id = propertys.category_id')
                            ->where(['status' => 1, 'request_status' => 'approved'])
                            ->where(function ($q) {
                                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                            })
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->whereRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?', [$latitude, $longitude, $latitude, $radius]);
                    });
                } else {
                    $categoriesQuery->whereHas('properties', function ($query) use ($latitude, $longitude, $radius) {
                        $query->where(['status' => 1, 'request_status' => 'approved'])
                            ->where(function ($q) {
                                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                            })
                            ->where(['latitude' => $latitude, 'longitude' => $longitude]);
                        $query->whereExists(function ($subQuery) use ($latitude, $longitude, $radius) {
                            $subQuery->select(DB::raw(1))
                                ->from('propertys')
                                ->whereRaw('categories.id = propertys.category_id')
                                ->where(['status' => 1, 'request_status' => 'approved'])
                                ->where(function ($q) {
                                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                                })
                                ->where('latitude', '!=', 0)
                                ->where('longitude', '!=', 0)
                                ->whereRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?', [$latitude, $longitude, $latitude, $radius]);
                        });
                    });
                }
            } else {
                $categoriesQuery->whereHas('properties', function ($query) {
                    $query->onlyActive();
                });
            }

            // Add properties count with location filtering
            if ($latitude && $longitude) {
                if ($radius && ! empty($radius)) {
                    $categoriesQuery->selectRaw('(SELECT COUNT(*) FROM propertys WHERE categories.id = propertys.category_id AND status = 1 AND request_status = "approved" AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as properties_count', [$latitude, $longitude, $latitude, $radius]);
                } else {
                    $categoriesQuery->selectRaw('(SELECT COUNT(*) FROM propertys WHERE categories.id = propertys.category_id AND status = 1 AND request_status = "approved" AND latitude = ? AND longitude = ?) as properties_count', [$latitude, $longitude]);
                }
            } else {
                $categoriesQuery->withCount(['properties' => function ($query) {
                    $query->where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                        $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                    });
                }]);
            }

            $categoriesQuery->with('translations');

            $data['categories'] = [
                'section_id' => $homepageData[$categoriesHomepageSection]['id'] ?? null,
                'data' => $categoriesQuery->limit(12)->get()->map(function ($item) {
                    $item->translated_name = $item->translated_name;

                    return $item;
                }),
            ];

            // Agents Section
            $agentsQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'is_agent_verified')
                ->with('agent_profile')
                ->where('is_agent', 1)
                ->where('isActive', 1);

            // Add counts with location filtering
            if ($latitude && $longitude) {
                if ($radius && ! empty($radius)) {
                    $agentsQuery->selectRaw('(SELECT COUNT(*) FROM projects WHERE customers.id = projects.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as projects_count', [$latitude, $longitude, $latitude, $radius])
                        ->selectRaw('(SELECT COUNT(*) FROM propertys WHERE customers.id = propertys.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as property_count', [$latitude, $longitude, $latitude, $radius]);
                } else {
                    $agentsQuery->selectRaw('(SELECT COUNT(*) FROM projects WHERE customers.id = projects.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude = ? AND longitude = ?) as projects_count', [$latitude, $longitude])
                        ->selectRaw('(SELECT COUNT(*) FROM propertys WHERE customers.id = propertys.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude = ? AND longitude = ?) as property_count', [$latitude, $longitude]);
                }
            } else {
                $agentsQuery->withCount([
                    'projects' => function ($query) {
                        $query->onlyActive()->where('role_context', 'agent');
                    },
                    'property' => function ($query) {
                        $query->onlyActive()->where('role_context', 'agent');
                    },
                ]);
            }

            $agents = $agentsQuery->get()
                ->map(function ($customer) {
                    $resolved = $customer->resolved_agent_profile;
                    $customer->name = $resolved['agent_name'];
                    $customer->email = $resolved['agent_email'];
                    $customer->profile = $resolved['agent_profile_photo'];
                    $customer->agent_profile = $resolved;
                    $customer->is_agent_verified = $customer->is_agent_verified ?? false;
                    $customer->total_count = $customer->projects_count + $customer->property_count;
                    $customer->is_admin = false;

                    return $customer;
                })
                ->filter(function ($customer) {
                    return $customer->projects_count > 0 || $customer->property_count > 0;
                })
                ->sortByDesc(function ($customer) {
                    return [$customer->is_verified, $customer->total_count];
                })
                ->values()
                ->take(12);

            // Add admin user if they have properties or projects
            $adminEmail = system_setting('company_email');
            $adminPropertyQuery = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])
                ->when($latitude && $longitude, function ($query) use ($latitude, $longitude, $radius) {
                    if ($radius && ! empty($radius)) {
                        $query->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$latitude, $longitude, $latitude])
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->having('distance', '<', $radius);
                    } else {
                        $query->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    }
                });
            $adminProjectQuery = Projects::where(['is_admin_listing' => 1, 'status' => 1])
                ->when($latitude && $longitude, function ($query) use ($latitude, $longitude, $radius) {
                    if ($radius && ! empty($radius)) {
                        $query->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$latitude, $longitude, $latitude])
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->having('distance', '<', $radius);
                    } else {
                        $query->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    }
                });

            $adminPropertiesCount = $adminPropertyQuery->count();
            $adminProjectsCount = $adminProjectQuery->count();

            if ($adminPropertiesCount > 0 || $adminProjectsCount > 0) {
                $adminQuery = User::where('type', 0)->select('id', 'slug_id', 'profile')->first();
                if ($adminQuery) {
                    $adminData = [
                        'id' => $adminQuery->id,
                        'name' => 'Admin',
                        'slug_id' => $adminQuery->slug_id,
                        'email' => ! empty($adminEmail) ? $adminEmail : '',
                        'property_count' => $adminPropertiesCount,
                        'projects_count' => $adminProjectsCount,
                        'total_count' => $adminPropertiesCount + $adminProjectsCount,
                        // 'is_verified' => true,
                        // 'is_verified_user' => true,
                        'is_agent_verified' => true,
                        'profile' => ! empty($adminQuery->getRawOriginal('profile')) ? $adminQuery->profile : url('assets/images/faces/2.jpg'),
                        'is_admin' => true,
                    ];
                    $agents->prepend((object) $adminData);
                }
            }

            $data['agents'] =
                [
                    'section_id' => $homepageData[$agentsHomepageSection]['id'] ?? null,
                    'data' => $agents,
                ];

            // Decide location based data availability and apply fallback to global data if none
            if ($latitude && $longitude) {
                $categoriesHasData = isset($data['categories']['data']) && $data['categories']['data']->count() > 0;
                $agentsHasData = isset($data['agents']['data']) && $data['agents']['data']->count() > 0;

                if ($categoriesHasData || $agentsHasData) {
                    $locationBasedData = true;
                } else {
                    // Fallback: rebuild categories without location filters
                    $fallbackCategoriesQuery = Category::select('id', 'category', 'image', 'slug_id')
                        ->where('status', 1)
                        ->whereHas('properties', function ($query) {
                            $query->onlyActive();
                        })
                        ->withCount(['properties' => function ($query) {
                            $query->onlyActive();
                        }])
                        ->with('translations');

                    $data['categories'] = [
                        'section_id' => $homepageData[$categoriesHomepageSection]['id'] ?? null,
                        'data' => $fallbackCategoriesQuery->limit(12)->get()->map(function ($item) {
                            $item->translated_name = $item->translated_name;

                            return $item;
                        }),
                    ];

                    // Fallback: rebuild agents without location filters
                    $fallbackAgentsQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id')
                        ->with('agent_profile')
                        ->where('is_agent', 1)
                        ->where('isActive', 1)
                        ->withCount([
                            'projects' => function ($query) {
                                $query->onlyActive()->where('role_context', 'agent');
                            },
                            'property' => function ($query) {
                                $query->onlyActive()->where('role_context', 'agent');
                            },
                        ]);

                    $fallbackAgents = $fallbackAgentsQuery->get()
                        ->map(function ($customer) {
                            $resolved = $customer->resolved_agent_profile;
                            $customer->name = $resolved['agent_name'];
                            $customer->email = $resolved['agent_email'];
                            $customer->profile = $resolved['agent_profile_photo'];
                            $customer->agent_profile = $resolved;
                            $customer->is_agent_verified = $customer->is_agent_verified ?? false;
                            $customer->total_count = $customer->projects_count + $customer->property_count;
                            $customer->is_admin = false;

                            return $customer;
                        })
                        ->filter(function ($customer) {
                            return $customer->projects_count > 0 || $customer->property_count > 0;
                        })
                        ->sortByDesc(function ($customer) {
                            return [$customer->is_verified, $customer->total_count];
                        })
                        ->values()
                        ->take(12);

                    // Add admin user if they have properties or projects (global, no location filter)
                    $adminEmailGlobal = system_setting('company_email');
                    $adminPropertiesCountGlobal = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->count();
                    $adminProjectsCountGlobal = Projects::where(['is_admin_listing' => 1, 'status' => 1])->count();

                    if ($adminPropertiesCountGlobal > 0 || $adminProjectsCountGlobal > 0) {
                        $adminQueryGlobal = User::where('type', 0)->select('id', 'slug_id', 'profile')->first();
                        if ($adminQueryGlobal) {
                            $adminDataGlobal = [
                                'id' => $adminQueryGlobal->id,
                                'name' => 'Admin',
                                'slug_id' => $adminQueryGlobal->slug_id,
                                'email' => ! empty($adminEmailGlobal) ? $adminEmailGlobal : '',
                                'property_count' => $adminPropertiesCountGlobal,
                                'projects_count' => $adminProjectsCountGlobal,
                                'total_count' => $adminPropertiesCountGlobal + $adminProjectsCountGlobal,
                                // 'is_verified' => true,
                                // 'is_verified_user' => true,
                                'is_agent_verified' => true,
                                'profile' => ! empty($adminQueryGlobal->getRawOriginal('profile')) ? $adminQueryGlobal->profile : url('assets/images/faces/2.jpg'),
                                'is_admin' => true,
                            ];
                            $fallbackAgents->prepend((object) $adminDataGlobal);
                        }
                    }

                    $data['agents'] = [
                        'section_id' => $homepageData[$agentsHomepageSection]['id'] ?? null,
                        'data' => $fallbackAgents,
                    ];
                }
            }

            // Articles Section
            $data['articles'] = [
                'section_id' => $homepageData[$articlesHomepageSection]['id'] ?? null,
                'data' => Article::select('id', 'slug_id', 'category_id', 'title', 'description', 'image', 'created_at')
                    ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                    ->limit(5)
                    ->get()
                    ->map(function ($item) {
                        if ($item->category) {
                            $item->category->translated_name = $item->category->translated_name;
                        }
                        $item->translated_title = $item->translated_title;
                        $item->translated_description = $item->translated_description;

                        return $item;
                    }),
            ];

            // User Recommendations Section
            if (Auth::guard('sanctum')->check()) {
                $loggedInUser = Auth::guard('sanctum')->user();
                $userInterestData = UserInterest::where('user_id', $loggedInUser->id)->first();

                if ($userInterestData) {
                    $userRecommendationQuery = Property::select(
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
                        'added_by',
                        'description'
                    )
                        ->with(['category:id,slug_id,image,category', 'category.translations', 'translations'])
                        ->onlyActive()
                        ->whereIn('propery_type', [0, 1]);

                    // Apply user interest filters
                    if (! empty($userInterestData->category_ids)) {
                        $categoryIds = explode(',', $userInterestData->category_ids);
                        $userRecommendationQuery->whereIn('category_id', $categoryIds);
                    }

                    if (! empty($userInterestData->price_range)) {
                        $priceRange = explode(',', $userInterestData->price_range);
                        if (count($priceRange) >= 2) {
                            $minPrice = floatval($priceRange[0]);
                            $maxPrice = floatval($priceRange[1]);
                            $userRecommendationQuery->whereRaw('CAST(price AS DECIMAL(10, 2)) BETWEEN ? AND ?', [$minPrice, $maxPrice]);
                        }
                    }

                    if (! empty($userInterestData->city)) {
                        $userRecommendationQuery->where('city', $userInterestData->city);
                    }

                    if (! empty($userInterestData->property_type) || $userInterestData->property_type == '0') {
                        $propertyType = explode(',', $userInterestData->property_type);
                        $userRecommendationQuery->whereIn('propery_type', $propertyType);
                    }

                    if (! empty($userInterestData->outdoor_facilitiy_ids)) {
                        $outdoorFacilityIds = explode(',', $userInterestData->outdoor_facilitiy_ids);
                        $userRecommendationQuery->whereHas('assignfacilities.outdoorfacilities', function ($q) use ($outdoorFacilityIds) {
                            $q->whereIn('id', $outdoorFacilityIds);
                        });
                    }

                    $data['user_recommendations'] = [
                        'section_id' => $homepageData[$userRecommendationsHomepageSection]['id'] ?? null,
                        'data' => $userRecommendationQuery
                            ->inRandomOrder()
                            ->limit(12)
                            ->get()
                            ->map(function ($property) {
                                $property->promoted = $property->is_promoted;
                                $property->property_type = $property->propery_type;
                                $property->is_premium = $property->is_premium == 1;
                                $property->parameters = $property->parameters;
                                if ($property->category) {
                                    $property->category->translated_name = $property->category->translated_name;
                                }
                                $property->translated_title = $property->translated_title;
                                $property->translated_description = $property->translated_description;

                                return $property;
                            }),
                    ];
                } else {
                    $data['user_recommendations'] = [
                        'section_id' => $homepageData[$userRecommendationsHomepageSection]['id'] ?? null,
                        'data' => [],
                    ];
                }
            } else {
                $data['user_recommendations'] = [
                    'section_id' => $homepageData[$userRecommendationsHomepageSection]['id'] ?? null,
                    'data' => [],
                ];
            }

            // FAQ Section
            $data['faqs'] = [
                'section_id' => $homepageData[$faqsHomepageSection]['id'] ?? null,
                'data' => Faq::select('id', 'question', 'answer')
                    ->where('status', 1)
                    ->with('translations')
                    ->orderBy('id', 'DESC')
                    ->limit(5)
                    ->get()
                    ->map(function ($faq) {
                        $faq->translated_question = $faq->translated_question;
                        $faq->translated_answer = $faq->translated_answer;

                        return $faq;
                    }),
            ];

            // Slider Section
            $lat = $request->latitude;
            $lng = $request->longitude;
            $rad = $request->radius;

            $slider = Slider::select(
                'id',
                'type',
                'image',
                'web_image',
                'category_id',
                'propertys_id',
                'show_property_details',
                'link'
            )
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'property' => fn ($p) => $p->select('id', 'slug_id', 'propery_type', 'title_image', 'title', 'price', 'city', 'state', 'country', 'rentduration', 'added_by', 'is_premium', 'latitude', 'longitude', 'total_click')
                        ->when(
                            $lat && $lng && $rad && $lat != 'null' && $lng != 'null' && $rad != 'null',
                            fn ($q) => $p->whereNotNull('latitude')->whereNotNull('longitude')
                                ->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude))
                            * cos(radians(longitude) - radians(?)) + sin(radians(?))
                            * sin(radians(latitude)))) AS distance', [$lat, $lng, $lat])
                                ->havingRaw('distance < ?', [$rad])
                        )
                        ->with('translations'),
                ])
                ->when(
                    $lat && $lng && $rad && $lat != 'null' && $lng != 'null' && $rad != 'null',
                    fn ($q) => $q->where(
                        fn ($sliderQuery) => $sliderQuery->where(function ($query) {
                            $query->where('type', 3)->has('property');
                        })->orWhere('type', '!=', 3)
                    )
                )
                ->get()
                ->map(function ($slider) {
                    $type = $slider->getRawOriginal('type');
                    $slider->slider_type = $type;
                    if ($slider->category) {
                        $slider->category->translated_name = $slider->category->translated_name;
                    }
                    if ($slider->getRawOriginal('type') == 3) {
                        if ($slider->property) {
                            $slider->property->parameters = $slider->property->parameters;
                            $slider->property->translated_title = $slider->property->translated_title;
                            $slider->property->translated_description = $slider->property->translated_description;
                            $slider->property->property_type = $slider->property->propery_type;
                            $slider->property->is_premium = $slider->property->is_premium == 1 ? true : false;

                            return $slider;
                        }
                    } else {
                        return $slider;
                    }
                })->filter()->values();
            $data['slider'] = [
                'section_id' => null,
                'data' => $slider,
            ];

            // Expose location flag in response
            $data['location_based_data'] = $locationBasedData;

            ApiResponseService::successResponse('Other Sections Fetched Successfully', $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    private function getUnsplashData($cityData)
    {
        $apiKey = env('UNSPLASH_API_KEY');
        $query = $cityData->city;
        $apiUrl = "https://api.unsplash.com/search/photos/?query=$query";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID '.$apiKey,
        ]);
        $unsplashResponse = curl_exec($ch);
        curl_close($ch);

        $unsplashData = json_decode($unsplashResponse, true);
        if (isset($unsplashData['results'])) {
            $results = $unsplashData['results'];
            $imageUrl = '';
            foreach ($results as $result) {
                $imageUrl = $result['urls']['regular'];
                break;
            }
            if ($imageUrl != '') {
                return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => $imageUrl];
            }
        }

        return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => ''];
    }
}
