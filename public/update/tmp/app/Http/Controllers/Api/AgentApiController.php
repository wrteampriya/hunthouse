<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\AgentBookingPreference;
use App\Models\AgentFollow;
use App\Models\AgentProfile;
use App\Models\AgentSocialMediaValue;
use App\Models\AgentVerification;
use App\Models\AgentVerificationForm;
use App\Models\AgentVerificationValue;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\Chats;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Projects;
use App\Models\ProjectView;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\HeaderParameter;

class AgentApiController extends Controller
{
    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'filters' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            // Decode filters (base64 → JSON), same convention as getPropertyList /
            // getAgentProperties — invalid/missing filters silently resolve to no filters
            // applied, matching every other base64-filter endpoint in the codebase.
            $filters = $request->filters;
            if (! empty($filters)) {
                $filters = base64_decode($filters);
                $filters = json_validate($filters) ? json_decode($filters, true) : [];
            } else {
                $filters = [];
            }

            $search = $filters['search'] ?? null;
            $serviceArea = $filters['service_area'] ?? null;
            $language = $filters['language'] ?? null;
            $isVerified = array_key_exists('is_verified', $filters) ? $filters['is_verified'] : null;
            $minExperience = $filters['min_experience'] ?? null;

            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) && ! empty($request->limit) ? $request->limit : 10;

            $latitude = $request->has('latitude') ? $request->latitude : null;
            $longitude = $request->has('longitude') ? $request->longitude : null;
            $propertyType = isset($filters['property_type']) ? $filters['property_type'] : null;
            $categoryId = isset($filters['category_id']) ? $filters['category_id'] : null;

            if (! empty($request->limit)) {
                $agentsListQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'is_agent', 'is_agent_verified')
                    ->with('agent_profile')
                    ->withAppointmentAvailabilityChecks()
                    ->where(function ($query) {
                        $query->where('isActive', 1);
                    })
                    // Include customers who are either user-verified or agents
                    ->where(function ($query) {
                        $query->whereHas('becomeAgent', function ($q) {
                            $q->where('status', 'approved');
                        });
                    })
                    ->where(function ($query) use ($latitude, $longitude, $request, $propertyType, $categoryId) {
                        $query->whereHas('projects', function ($query) use ($latitude, $longitude, $request, $categoryId) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            })->when($request->filled('country_id'), function ($query) use ($request) {
                                $query->where('country_id', $request->country_id);
                            })->when($categoryId, function ($query) use ($categoryId) {
                                $query->whereIn('category_id', (array) $categoryId);
                            });
                        })->orWhereHas('property', function ($query) use ($latitude, $longitude, $request, $propertyType, $categoryId) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            })->when($request->filled('country_id'), function ($query) use ($request) {
                                $query->where('country_id', $request->country_id);
                            })->when($propertyType !== null, function ($query) use ($propertyType) {
                                $query->where('propery_type', $propertyType);
                            })->when($categoryId, function ($query) use ($categoryId) {
                                $query->whereIn('category_id', (array) $categoryId);
                            });
                        });
                    })
                    // Name search — resolved agent-branded name if set, else raw account name.
                    ->when(! empty($search), function ($query) use ($search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%')
                                ->orWhereHas('agent_profile', function ($q2) use ($search) {
                                    $q2->where('agent_name', 'like', '%'.$search.'%');
                                });
                        });
                    })
                    ->when($isVerified !== null, function ($query) use ($isVerified) {
                        $query->where('is_agent_verified', (bool) $isVerified);
                    })
                    // Service Area / Language / Years of Experience live in the Become Agent
                    // form's Default-section storage (agent_verification_values), not as real
                    // Customer columns — same EAV pattern as agent_profile_extras, but filtered
                    // via whereHas here instead of read via accessor.
                    ->when(! empty($serviceArea), function ($query) use ($serviceArea) {
                        HelperService::filterCustomersByVerificationField($query, 'Service Areas', $serviceArea);
                    })
                    ->when(! empty($language), function ($query) use ($language) {
                        HelperService::filterCustomersByVerificationField($query, 'Languages Spoken', $language);
                    })
                    ->when($minExperience !== null, function ($query) use ($minExperience) {
                        HelperService::filterCustomersByVerificationField($query, 'Years of Experience', (int) $minExperience, '>=');
                    })
                    ->withCount([
                        'projects as total_projects' => function ($query) use ($latitude, $longitude, $request, $categoryId) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            })->when($request->filled('country_id'), function ($query) use ($request) {
                                $query->where('country_id', $request->country_id);
                            })->when($categoryId, function ($query) use ($categoryId) {
                                $query->whereIn('category_id', (array) $categoryId);
                            });
                        },
                        'property as total_properties' => function ($query) use ($latitude, $longitude, $request, $propertyType, $categoryId) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            })->when($request->filled('country_id'), function ($query) use ($request) {
                                $query->where('country_id', $request->country_id);
                            })->when($propertyType !== null, function ($query) use ($propertyType) {
                                $query->where('propery_type', $propertyType);
                            })->when($categoryId, function ($query) use ($categoryId) {
                                $query->whereIn('category_id', (array) $categoryId);
                            });
                        },
                    ])
                    ->withCount('followers')
                    ->withExists(['stories as has_active_story' => fn ($q) => $q->active()]);

                // $agentData = AgentProfile::where('user_id', Auth::user()->id);

                $agentListCount = $agentsListQuery->clone()->count();

                $agentListData = $agentsListQuery->clone()
                    ->get()
                    ->map(function ($customer) {
                        $resolved = $customer->applyResolvedAgentProfile();
                        // Customer::getTotalProjectsAttribute()/getTotalPropertiesAttribute()
                        // are accessors that always win over the withCount()-computed value
                        // above on every read — grab the real (filtered) count via
                        // getRawOriginal() before it gets shadowed, then bake it back in
                        // as a plain array so nothing re-triggers the accessor downstream.
                        $totalProjects = (int) $customer->getRawOriginal('total_projects');
                        $totalProperties = (int) $customer->getRawOriginal('total_properties');
                        $isFollowing = $customer->is_following;
                        $followersCount = $customer->followers_count;
                        $isAppointmentAvailable = $customer->resolveAppointmentAvailability();

                        $customer = $customer->toArray();
                        unset($customer['has_qualifying_property_for_appointment'], $customer['has_active_availability_for_appointment']);
                        $customer['name'] = $resolved['agent_name'];
                        $customer['email'] = $resolved['agent_email'];
                        $customer['profile'] = $resolved['agent_profile_photo'];
                        $customer['agent_address'] = $resolved['agent_address'];
                        $customer['total_projects'] = $totalProjects;
                        $customer['total_properties'] = $totalProperties;
                        $customer['total_count'] = $totalProjects + $totalProperties;
                        $customer['is_admin'] = false;
                        $customer['has_active_story'] = (bool) $customer['has_active_story'];
                        $customer['is_following'] = $isFollowing;
                        $customer['followers_count'] = $followersCount;
                        $customer['is_appointment_available'] = $isAppointmentAvailable;

                        return $customer;
                    })
                    ->filter(function ($customer) {
                        return $customer['total_projects'] > 0 || $customer['total_properties'] > 0;
                    })
                    ->sortByDesc(function ($customer) {
                        return [$customer['is_verified'] ?? null, $customer['total_count']];
                    })
                    ->skip($offset)
                    ->take($limit)
                    ->values(); // This line resets the array keys

                // dd($agentListData);
                // Get admin List

                // Admin has no Become Agent profile (no service areas/languages/years of
                // experience — those live in agent_verification_values, which admin has no
                // row in at all) — if any of those filters are set, admin can never match, so
                // skip the admin entry entirely rather than always showing it regardless.
                $adminEligible = empty($serviceArea) && empty($language) && $minExperience === null;
                // Admin's own "verified" status is hardcoded true below — if the caller
                // explicitly asked for unverified agents only, admin shouldn't show either.
                if ($isVerified !== null && ! $isVerified) {
                    $adminEligible = false;
                }
                // Admin's name doesn't go through the DB query above, so the search filter
                // has to be applied here by hand.
                if ($adminEligible && ! empty($search)) {
                    $adminEligible = str_contains(strtolower(User::where('type', 0)->value('name') ?? ''), strtolower($search));
                }

                $adminEmail = system_setting('company_email');
                $adminData = [];
                $adminPropertiesCount = $adminEligible ? Property::onlyActive()->where(['added_by' => 0])->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                    $query->where('latitude', $latitude)->where('longitude', $longitude);
                })->when($request->filled('country_id'), function ($query) use ($request) {
                    $query->where('country_id', $request->country_id);
                })->when($propertyType !== null, function ($query) use ($propertyType) {
                    $query->where('propery_type', $propertyType);
                })->when($categoryId, function ($query) use ($categoryId) {
                    $query->whereIn('category_id', (array) $categoryId);
                })->count() : 0;
                $adminProjectsCount = $adminEligible ? Projects::onlyActive()->where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                    $query->where('latitude', $latitude)->where('longitude', $longitude);
                })->when($request->filled('country_id'), function ($query) use ($request) {
                    $query->where('country_id', $request->country_id);
                })->when($categoryId, function ($query) use ($categoryId) {
                    $query->whereIn('category_id', (array) $categoryId);
                })->count() : 0;
                $totalCount = $adminPropertiesCount + $adminProjectsCount;

                $adminQuery = $adminEligible ? User::where('type', 0)->select('id', 'slug_id', 'name', 'profile', 'type')->first() : null;
                if ($adminQuery && ($adminPropertiesCount > 0 || $adminProjectsCount > 0)) {
                    $adminData = [
                        'id' => 0,
                        'name' => $adminQuery->name,
                        'slug_id' => $adminQuery->slug_id,
                        'email' => ! empty($adminEmail) ? $adminEmail : '',
                        'total_properties' => $adminPropertiesCount,
                        'total_projects' => $adminProjectsCount,
                        'total_count' => $totalCount,
                        // 'is_verified' => true,
                        // 'is_verified_user' => true,
                        'is_agent_verified' => true,
                        'profile' => ! empty($adminQuery->getRawOriginal('profile')) ? $adminQuery->profile : url('assets/images/faces/2.jpg'),
                        'is_admin' => true,
                        'followers_count' => AgentFollow::where('agent_id', 0)->count(),
                        'is_following' => Auth::check() ? AgentFollow::where('user_id', Auth::id())->where('agent_id', 0)->exists() : false,
                        'is_appointment_available' => (bool) $adminQuery->is_appointment_available,
                    ];
                    if ($offset == 0) {
                        $agentListData->prepend((object) $adminData);
                    }
                }
            }
            $response = [
                'error' => false,
                'total' => $agentListCount ?? 0,
                'data' => $agentListData ?? [],
                'message' => trans('Data Fetched Successfully'),
            ];

            return response()->json($response);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
                'details' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    /**
     * Top 10 most-used Service Area / Language values across approved agents, so the
     * frontend can offer them as selectable chips for the agent-list filters instead of
     * a blind text box. Public — same as the existing property-advance-filter-data
     * endpoint, this is just static filter-UI discovery data, not user-specific.
     */
    public function getAgentFilterOptions(Request $request)
    {
        try {
            // Neither `field` nor `offset`/`limit` — keep the original
            // top-10-both-fields shape for existing callers (e.g. the
            // quick-filter chips row).
            if (! $request->filled('field') && ! $request->filled('offset') && ! $request->filled('limit')) {
                $response = [
                    'error' => false,
                    'message' => trans('Data Fetched Successfully'),
                    'data' => [
                        'service_areas' => HelperService::getTopAgentVerificationValues('Service Areas'),
                        'languages' => HelperService::getTopAgentVerificationValues('Languages Spoken'),
                    ],
                ];

                return response()->json($response);
            }

            $validator = Validator::make($request->all(), [
                'field' => 'nullable|in:service_areas,languages,property_categories',
                'search' => 'nullable|string',
                'offset' => 'nullable|integer|min:0',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $offset = (int) ($request->offset ?? 0);
            $limit = (int) ($request->limit ?? 20);

            // `field` given — single-section search/pagination (Filters panel
            // section expanded/searched individually).
            if ($request->filled('field')) {
                $result = $this->resolveAgentFilterOptions($request->field, $request->search, $offset, $limit);

                return response()->json([
                    'error' => false,
                    'message' => trans('Data Fetched Successfully'),
                    'data' => $result,
                ]);
            }

            // No `field`, but offset/limit given — combined mode: all three
            // sections at once, same offset/limit applied to each, no search
            // (search stays per-section via the `field` mode above).
            $response = [
                'error' => false,
                'message' => trans('Data Fetched Successfully'),
                'data' => [
                    'service_areas' => $this->resolveAgentFilterOptions('service_areas', null, $offset, $limit),
                    'languages' => $this->resolveAgentFilterOptions('languages', null, $offset, $limit),
                    'property_categories' => $this->resolveAgentFilterOptions('property_categories', null, $offset, $limit),
                ],
            ];

            return response()->json($response);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
                'details' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    /**
     * Resolves one Filters-panel section's `{options, total}` — shared by
     * getAgentFilterOptions()'s single-field and combined (offset/limit-only)
     * modes so both stay backed by the exact same logic per field.
     */
    private function resolveAgentFilterOptions(string $field, ?string $search, int $offset, int $limit): array
    {
        if ($field === 'property_categories') {
            $categoriesQuery = Category::where('status', 1)
                ->when(! empty($search), function ($query) use ($search) {
                    $query->where('category', 'like', '%'.$search.'%');
                });

            $total = $categoriesQuery->count();
            $options = $categoriesQuery->select('id', 'category')
                ->skip($offset)->take($limit)->get()
                ->map(fn ($category) => ['id' => $category->id, 'name' => $category->translated_name]);

            return ['options' => $options, 'total' => $total];
        }

        $formName = $field === 'service_areas' ? 'Service Areas' : 'Languages Spoken';
        $result = HelperService::getAgentVerificationValueOptions($formName, $search, $offset, $limit);

        return ['options' => $result['data'], 'total' => $result['total']];
    }

    /**
     * Resolve the target of a follow/unfollow request to an agent_follows.agent_id
     * value. agent_id=0 means the platform admin (same sentinel convention as
     * stories.agent_id / propertys.added_by) — safe to trust directly since
     * customers.id auto-increments from 1 and can never be 0, so there's no
     * collision risk the way there would be trusting admin's real users.id.
     */
    private function resolveFollowTargetAgentId(Request $request, $currentUser)
    {
        $requestedAgentId = (int) $request->agent_id;

        // Agent list/homepage endpoints expose the admin's real users.id in
        // their "id" field (not the 0 sentinel), so the frontend follows the
        // admin using that id — treat it the same as the 0 sentinel.
        $adminUserId = (int) (User::where('type', 0)->value('id'));

        if ($requestedAgentId === 0 || ($adminUserId && $requestedAgentId === $adminUserId)) {
            return 0;
        }

        if ($requestedAgentId === (int) $currentUser->id) {
            return ApiResponseService::validationError(trans('You cannot follow yourself'));
        }

        $targetAgent = Customer::where('id', $requestedAgentId)->where('is_agent', 1)->first();
        if (! $targetAgent) {
            return ApiResponseService::validationError(trans('This user is not an agent and cannot be followed'));
        }

        return $targetAgent->id;
    }

    public function toggleFollowAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $currentUser = Auth::user();
            $targetAgentId = $this->resolveFollowTargetAgentId($request, $currentUser);
            if (! is_int($targetAgentId)) {
                return $targetAgentId; // already an error response
            }

            $existingFollow = AgentFollow::where('user_id', $currentUser->id)->where('agent_id', $targetAgentId)->first();

            if ($existingFollow) {
                $existingFollow->delete();

                return ApiResponseService::successResponse(trans('Agent unfollowed successfully'), ['is_following' => false]);
            }

            AgentFollow::create([
                'user_id' => $currentUser->id,
                'agent_id' => $targetAgentId,
            ]);

            return ApiResponseService::successResponse(trans('Agent followed successfully'), ['is_following' => true]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getFollowingAgents(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $currentUser = Auth::user();
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;

            $followingAgentIdsQuery = AgentFollow::where('user_id', $currentUser->id);
            $total = $followingAgentIdsQuery->clone()->count();
            $isFollowingAdmin = $followingAgentIdsQuery->clone()->where('agent_id', 0)->exists();
            $agentIds = $followingAgentIdsQuery->clone()->where('agent_id', '!=', 0)->orderBy('id', 'desc')->skip($offset)->take($limit)->pluck('agent_id');

            $agents = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'is_agent', 'is_agent_verified')
                ->with('agent_profile')
                ->whereIn('id', $agentIds)
                ->withCount(['projects as total_projects' => fn ($q) => $q->onlyActive()->where('role_context', 'agent'), 'property as total_properties' => fn ($q) => $q->onlyActive()->where('role_context', 'agent'), 'followers'])
                ->withExists(['stories as has_active_story' => fn ($q) => $q->active()])
                ->get()
                ->sortBy(fn ($agent) => array_search($agent->id, $agentIds->all()))
                ->values()
                ->map(function ($customer) {
                    $resolved = $customer->applyResolvedAgentProfile();
                    // Customer::getTotalProjectsAttribute()/getTotalPropertiesAttribute()
                    // are accessors that always win over the withCount()-computed value
                    // above on every read — grab the real (filtered) count via
                    // getRawOriginal() before it gets shadowed, then bake it back in
                    // as a plain array so nothing re-triggers the accessor downstream.
                    $totalProjects = (int) $customer->getRawOriginal('total_projects');
                    $totalProperties = (int) $customer->getRawOriginal('total_properties');
                    $hasActiveStory = (bool) $customer->has_active_story;
                    $followersCount = $customer->followers_count;

                    $customer = $customer->toArray();
                    $customer['name'] = $resolved['agent_name'];
                    $customer['email'] = $resolved['agent_email'];
                    $customer['profile'] = $resolved['agent_profile_photo'];
                    $customer['total_projects'] = $totalProjects;
                    $customer['total_properties'] = $totalProperties;
                    $customer['total_count'] = $totalProjects + $totalProperties;
                    $customer['has_active_story'] = $hasActiveStory;
                    $customer['followers_count'] = $followersCount;

                    // Watermark settings are agent-private (used when they post
                    // listings) — not relevant to someone browsing who they follow.
                    if (is_array($customer['agent_profile'] ?? null)) {
                        unset(
                            $customer['agent_profile']['watermark_enabled'],
                            $customer['agent_profile']['watermark_image'],
                            $customer['agent_profile']['watermark_opacity'],
                            $customer['agent_profile']['watermark_size'],
                            $customer['agent_profile']['watermark_style'],
                            $customer['agent_profile']['watermark_position'],
                            $customer['agent_profile']['watermark_rotation']
                        );
                    }

                    return $customer;
                });

            if ($isFollowingAdmin && $offset == 0) {
                $adminQuery = User::where('type', 0)->select('id', 'slug_id', 'name', 'profile')->first();
                if ($adminQuery) {
                    $adminPropertiesCount = Property::onlyActive()->where(['added_by' => 0])->count();
                    $adminProjectsCount = Projects::onlyActive()->where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->count();
                    $agents->prepend((object) [
                        'id' => 0,
                        'name' => $adminQuery->name,
                        'slug_id' => $adminQuery->slug_id,
                        'total_properties' => $adminPropertiesCount,
                        'total_projects' => $adminProjectsCount,
                        'total_count' => $adminPropertiesCount + $adminProjectsCount,
                        'is_agent_verified' => true,
                        'is_admin' => true,
                        'followers_count' => AgentFollow::where('agent_id', 0)->count(),
                        'profile' => ! empty($adminQuery->getRawOriginal('profile')) ? $adminQuery->profile : url('assets/images/faces/2.jpg'),
                    ]);
                }
            }

            return ApiResponseService::successResponse(trans('Data Fetched Successfully'), $agents, ['total' => $total]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardFollowersData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $loggedInAgent = Auth::user();
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;

            $followerIdsQuery = AgentFollow::where('agent_id', $loggedInAgent->id);
            $total = $followerIdsQuery->clone()->count();
            $followerIds = $followerIdsQuery->clone()->orderBy('id', 'desc')->skip($offset)->take($limit)->pluck('user_id');

            $followers = Customer::select('id', 'name', 'email', 'profile', 'slug_id')
                ->whereIn('id', $followerIds)
                ->get()
                ->sortBy(fn ($follower) => array_search($follower->id, $followerIds->all()))
                ->values();

            return ApiResponseService::successResponse(trans('Followers fetched successfully'), $followers, ['total' => $total]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentProfile(Request $request)
    {
        try {
            $customer = Auth::user();

            return response()->json([
                'error' => false,
                'data' => $this->buildAgentProfileData($customer->id),
                'message' => trans('Data Fetched Successfully'),
            ]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    /**
     * Shared response shape for both getAgentProfile and updateAgentProfile, so an update
     * response always reflects every field that can be changed there — not just the raw
     * AgentProfile columns updateAgentProfile used to return, which silently omitted
     * social_media_links/service_areas/etc. (those live in other tables).
     */
    private function buildAgentProfileData($customerId): array
    {
        $customerData = Customer::with('agent_profile')
            ->where('id', $customerId)
            ->first();
        // Add agent_verification_reject_reason if exist
        $rejectReason = AgentVerification::with('rejectReason')->where('customer_id', $customerId)->where('form_type', 'verify_agent')->first()?->rejectReason?->reason ?? '';

        // Step 1: Pass MODEL to resource (important)
        $resource = new CustomerResource(
            $customerData,
            ['become_agent_status', 'agent_verification_status']
        );

        // Step 2: Resolve resource to array
        $data = $resource->resolve();

        // Step 3: Override only required fields
        $resolvedProfile = $customerData->resolved_agent_profile;
        $profileExtras = $customerData->agent_profile_extras;

        return [
            'id' => $customerData->id,
            'agent_name' => $resolvedProfile['agent_name'] ?? '',
            'agent_email' => $resolvedProfile['agent_email'] ?? '',
            'agent_profile_photo' => $resolvedProfile['agent_profile_photo'] ?? '',
            'agent_address' => $resolvedProfile['agent_address'] ?? '',
            'agent_mobile' => $resolvedProfile['agent_mobile'] ?? '',
            'agent_country_code' => $resolvedProfile['agent_country_code'] ?? '',
            'agent_phone' => $customerData->phone ?? '',
            'agent_full_mobile' => $customerData->full_mobile ?? '',
            'country_code' => $customerData->country_code ?? '',
            'default_language' => $customerData->default_language ?? '',
            'about_me' => $resolvedProfile['about_me'] ?? '',
            'agent_banner' => $resolvedProfile['agent_banner'] ?? '',
            'is_admin_added' => $customerData->is_admin_added ?? false,
            'isActive' => $customerData->isActive ?? false,
            'is_demo_user' => $customerData->is_demo_user ?? false,
            'is_appointment_available' => $customerData->is_appointment_available ?? false,
            'become_agent_status' => $data['become_agent_status'] ?? 'not_applied',
            'agent_verification_status' => $data['agent_verification_status'] ?? 'not_applied',
            'agent_verification_reject_reason' => $rejectReason,
            'social_media_links' => $customerData->social_media_links,
            'service_areas' => $profileExtras['service_areas'],
            'languages_spoken' => $profileExtras['languages_spoken'],
            'years_of_experience' => $profileExtras['years_of_experience'],
            'working_hours_start' => $profileExtras['working_hours_start'],
            'working_hours_end' => $profileExtras['working_hours_end'],
            'working_hours_total_per_day' => $profileExtras['working_hours_total_per_day'],
            'watermark_enabled' => $resolvedProfile['watermark_enabled'] ?? false,
            'watermark_image' => $resolvedProfile['watermark_image'] ?? '',
            'watermark_opacity' => $resolvedProfile['watermark_opacity'] ?? 25,
            'watermark_size' => $resolvedProfile['watermark_size'] ?? 10,
            'watermark_style' => $resolvedProfile['watermark_style'] ?? 'tile',
            'watermark_position' => $resolvedProfile['watermark_position'] ?? 'center',
            'watermark_rotation'  => $resolvedProfile['watermark_rotation'] ?? 30,
            'has_active_story'    => Story::where('agent_id', $customerData->id)->active()->exists(),
            'followers_count'     => AgentFollow::where('agent_id', $customerData->id)->count(),
        ];
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentWatermarkSettings(Request $request)
    {
        try {
            $customer = Auth::user();
            $agentProfile = AgentProfile::firstOrNew(['customer_id' => $customer->id]);
            $feature = HelperService::checkAgentWatermarkFeature($customer->id);

            return ApiResponseService::successResponse(trans('Data Fetched Successfully'), $this->formatAgentWatermarkSettings($agentProfile, $feature));
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function updateAgentWatermarkSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'watermark_enabled' => 'nullable|in:0,1,true,false',
                'watermark_image' => 'nullable|file|max:3000|mimes:jpeg,png,jpg,webp',
                'watermark_opacity' => 'nullable|required_if:watermark_enabled,1|required_if:watermark_enabled,true|numeric|min:0|max:100',
                'watermark_size' => 'nullable|required_if:watermark_enabled,1|required_if:watermark_enabled,true|numeric|min:1|max:100',
                'watermark_style' => 'nullable|required_if:watermark_enabled,1|required_if:watermark_enabled,true|in:tile,single,center',
                'watermark_position' => 'nullable|required_if:watermark_style,single|in:top-left,top-right,bottom-left,bottom-right,center',
                'watermark_rotation' => 'nullable|numeric|min:0|max:360',
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $customer = Auth::user();
            $feature = HelperService::checkAgentWatermarkFeature($customer->id);
            if (empty($feature['feature_available']) || empty($feature['limit_available'])) {
                return ApiResponseService::validationError(trans('Agent Watermark feature is not available in your active package'), null, $feature);
            }

            DB::beginTransaction();
            $agentProfile = AgentProfile::firstOrNew(['customer_id' => $customer->id]);

            if ($request->has('watermark_enabled')) {
                $agentProfile->watermark_enabled = filter_var($request->watermark_enabled, FILTER_VALIDATE_BOOLEAN);
            }

            if ($request->has('watermark_opacity')) {
                $agentProfile->watermark_opacity = $request->watermark_opacity;
            }

            if ($request->has('watermark_size')) {
                $agentProfile->watermark_size = $request->watermark_size;
            }

            if ($request->has('watermark_style')) {
                $agentProfile->watermark_style = $request->watermark_style;
            }

            if ($request->has('watermark_position')) {
                $agentProfile->watermark_position = $request->watermark_position;
            }

            if ($request->has('watermark_rotation')) {
                $agentProfile->watermark_rotation = $request->watermark_rotation;
            }

            if ($request->hasFile('watermark_image')) {
                $rawImage = $agentProfile->getRawOriginal('watermark_image');
                $agentProfile->watermark_image = FileService::compressAndReplace(
                    $request->file('watermark_image'),
                    config('global.AGENT_WATERMARK_IMG_PATH'),
                    $rawImage
                );
            }

            $agentProfile->save();
            DB::commit();

            return ApiResponseService::successResponse(trans('Watermark Settings Updated Successfully'), $this->formatAgentWatermarkSettings($agentProfile->fresh(), $feature));
        } catch (Exception $e) {
            DB::rollback();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    private function formatAgentWatermarkSettings(AgentProfile $agentProfile, array $feature = []): array
    {
        return [
            'feature_available' => $feature['feature_available'] ?? false,
            'package_available' => $feature['package_available'] ?? false,
            'limit_available' => $feature['limit_available'] ?? false,
            'watermark_enabled' => $agentProfile->watermark_enabled ?? false,
            'watermark_image' => $agentProfile->watermark_image,
            'watermark_opacity' => $agentProfile->watermark_opacity ?? 25,
            'watermark_size' => $agentProfile->watermark_size ?? 10,
            'watermark_style' => $agentProfile->watermark_style ?? 'tile',
            'watermark_position' => $agentProfile->watermark_position ?? 'center',
            'watermark_rotation' => $agentProfile->watermark_rotation ?? 30,
        ];
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function updateAgentProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_name' => 'nullable|string|max:255',
                'agent_email' => 'nullable|email|max:255',
                'agent_profile_photo' => 'nullable|file|max:3000|mimes:jpeg,png,jpg,webp',
                'about_me' => 'nullable|string|max:1000',
                'agent_banner' => 'nullable|file|max:3000|mimes:jpeg,png,jpg,webp',
                'agent_address' => 'nullable|string|max:500',
                'agent_mobile' => 'nullable|string|max:20',
                'agent_country_code' => 'nullable|string|max:10',
                'social_media_links' => 'nullable|array',
                'social_media_links.*.id' => 'required_with:social_media_links|exists:social_media_links,id',
                'social_media_links.*.url' => 'nullable|url|max:255',
                'service_areas' => 'nullable|string|max:3072',
                'languages_spoken' => 'nullable|string|max:3072',
                'years_of_experience' => 'nullable|numeric|min:0|max:100',
                'working_hours_start' => ['nullable', function ($attribute, $value, $fail) {
                    if ($this->normalizeTimeInput($value) === null) {
                        $fail('The '.$attribute.' must be a valid time, e.g. "09:00" or "9:00 AM".');
                    }
                }],
                'working_hours_end' => ['nullable', function ($attribute, $value, $fail) {
                    if ($this->normalizeTimeInput($value) === null) {
                        $fail('The '.$attribute.' must be a valid time, e.g. "18:00" or "6:00 PM".');
                    }
                }],
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            $customer = Auth::user();

            $agentProfile = AgentProfile::firstOrNew(['customer_id' => $customer->id]);

            if ($request->has('agent_name')) {
                $agentProfile->agent_name = $request->agent_name;
            }
            if ($request->has('agent_email')) {
                $agentProfile->agent_email = $request->agent_email;
            }

            if ($request->has('about_me')) {
                $agentProfile->about_me = $request->about_me;
            }

            if ($request->has('social_media_links')) {
                foreach ($request->social_media_links as $link) {
                    AgentSocialMediaValue::updateOrCreate(
                        ['customer_id' => $customer->id, 'social_media_link_id' => $link['id']],
                        ['url' => $link['url'] ?? null]
                    );
                }
            }

            $this->saveAgentProfileExtras($request, $customer->id);

            if ($request->has('agent_address')) {
                $agentProfile->agent_address = $request->agent_address;
            }

            if ($request->has('agent_mobile')) {
                $agentProfile->agent_mobile = $request->agent_mobile;
            }

            if ($request->has('agent_country_code')) {
                $agentProfile->agent_country_code = $request->agent_country_code;
            }

            if ($request->hasFile('agent_banner')) {
                $rawBanner = $agentProfile->getRawOriginal('agent_banner');
                $agentProfile->agent_banner = FileService::compressAndReplace(
                    $request->file('agent_banner'),
                    config('global.AGENT_PROFILE_BANNER_PATH'),
                    $rawBanner
                );
            }

            if ($request->hasFile('agent_profile_photo')) {
                $rawImage = $agentProfile->getRawOriginal('agent_profile_photo');
                $agentProfile->agent_profile_photo = FileService::compressAndReplace(
                    $request->file('agent_profile_photo'),
                    config('global.AGENT_PROFILE_IMG_PATH'),
                    $rawImage
                );
            }

            $agentProfile->save();
            DB::commit();

            return response()->json([
                'error' => false,
                'data' => $this->buildAgentProfileData($customer->id),
                'message' => trans('Agent profile updated successfully'),
            ]);
        } catch (Exception $e) {
            DB::rollback();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    /**
     * service_areas/languages_spoken/years_of_experience/working_hours_start/working_hours_end
     * reuse the Become Agent form's Default-section storage (AgentVerificationValue) instead of
     * duplicating it as agent_profiles columns — see agent_profile_extras on Customer. Safe to
     * firstOrCreate the AgentVerification row here: this method only runs behind the `agent`
     * middleware, which requires is_agent = true, and is_agent is only ever set true by admin
     * approving an existing become_agent AgentVerification row — so that row is always already
     * present (status already 'approved') by the time this can be called; firstOrCreate never
     * actually creates one in practice.
     */
    private function saveAgentProfileExtras(Request $request, $customerId)
    {
        $fieldParams = [
            'service_areas' => 'Service Areas',
            'languages_spoken' => 'Languages Spoken',
            'years_of_experience' => 'Years of Experience',
            'working_hours_start' => 'Working Hours - Start',
            'working_hours_end' => 'Working Hours - End',
        ];

        $submitted = array_filter($fieldParams, function ($paramKey) use ($request) {
            return $request->has($paramKey);
        }, ARRAY_FILTER_USE_KEY);

        if (empty($submitted)) {
            return;
        }

        $verification = AgentVerification::firstOrCreate([
            'customer_id' => $customerId,
            'form_type' => 'become_agent',
        ]);

        $formIds = AgentVerificationForm::whereIn('name', array_values($submitted))
            ->whereHas('agent_verification_form_section', function ($query) {
                $query->where('is_default', true);
            })
            ->pluck('id', 'name');

        foreach ($submitted as $paramKey => $fieldName) {
            $formId = $formIds->get($fieldName);
            if (! $formId) {
                continue;
            }

            $value = $request->input($paramKey);
            if (in_array($paramKey, ['working_hours_start', 'working_hours_end'])) {
                $value = $this->normalizeTimeInput($value);
            }

            AgentVerificationValue::updateOrCreate(
                ['agent_verification_id' => $verification->id, 'agent_verification_form_id' => $formId],
                ['value' => $value]
            );
        }
    }

    /**
     * Front-end sends both 24-hour ("09:00") and 12-hour with AM/PM ("9:00 AM", "6:05 AM")
     * for working hours — normalize either to 24-hour "H:i" for storage, since that's the
     * format the working-hours-total calculation (Carbon::createFromFormat('H:i', ...)) and
     * existing stored data both expect. Returns null when the value is neither.
     */
    private function normalizeTimeInput(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $value;
        }

        try {
            return \Carbon\Carbon::createFromFormat('g:i A', trim($value))->format('H:i');
        } catch (Exception) {
            return null;
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentProfileCompletion(Request $request)
    {
        try {
            $customer = Auth::user();
            $agentProfile = AgentProfile::where('customer_id', $customer->id)->first();

            $fields = [
                'name' => ! empty($customer->name),
                'email' => ! empty($customer->email),
                'mobile' => ! empty($customer->getRawOriginal('mobile')),
                'profile_photo' => ! empty($customer->getRawOriginal('profile')),
                'address' => ! empty($customer->address),
                'about_me' => ! empty($customer->about_me),
                'agent_name' => ! empty($agentProfile?->getRawOriginal('agent_name')),
                'agent_email' => ! empty($agentProfile?->agent_email),
                'agent_profile_photo' => ! empty($agentProfile?->getRawOriginal('agent_profile_photo')),
                'agent_banner' => ! empty($agentProfile?->getRawOriginal('agent_banner')),
            ];

            $completedFields = array_keys(array_filter($fields));
            $missingFields = array_keys(array_filter($fields, fn ($v) => ! $v));
            $totalFields = count($fields);
            $completedCount = count($completedFields);
            $percentage = $totalFields > 0 ? round(($completedCount / $totalFields) * 100) : 0;

            return response()->json([
                'error' => false,
                'data' => [
                    'completion_percentage' => $percentage,
                    'total_fields' => $totalFields,
                    'completed_fields' => $completedCount,
                    'fields' => $fields,
                    'missing_fields' => $missingFields,
                ],
                'message' => trans('Data Fetched Successfully'),
            ]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardSummeryData(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            $last12Months = now()->subMonths(12);
            // Properties Query (only sell and rent properties)
            $propertiesQuery = Property::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => $request->user_active_role])->where(function ($q) {
                $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
            })->whereIn('propery_type', [0, 1]);

            // Projects Query
            $projectsQuery = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => $request->user_active_role])->where(function ($q) {
                $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
            });

            // Property Views Query
            $propertyViewsQuery = PropertyView::whereHas('property', function ($query) use ($loggedInUser, $request) {
                $query->where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => $request->user_active_role])->where(function ($q) {
                    $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
                });
            })->orderBy('views', 'DESC');

            // Appointment Query
            $appointmentQuery = Appointment::where(function ($query) use ($loggedInUser) {
                $query->where('agent_id', $loggedInUser->id)->orWhere('user_id', $loggedInUser->id);
            });

            // Last 12 Months Property Query
            $last12MonthsPropertyQuery = $propertiesQuery->clone()->where('created_at', '>=', $last12Months);

            // Last 12 Months Project Query
            $last12MonthsProjectQuery = $projectsQuery->clone()->where('created_at', '>=', $last12Months);

            // Last 12 Months Property Views Query
            $last12MonthsPropertyViewsQuery = $propertyViewsQuery->clone()->where('date', '>=', $last12Months);

            // Last 12 Months Appointment Query
            $last12MonthsAppointmentQuery = $appointmentQuery->clone()->where('start_at', '>=', $last12Months);

            // Get last 12 months total, total Properties Count, month wise total property listed counts and total properties count of this month
            $last12MonthTotalPropertiesCount = $last12MonthsPropertyQuery->clone()->count();
            $currentMonthPropertyCount = $last12MonthsPropertyQuery->clone()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $monthlyPropertyCounts = $this->getLast12MonthsPropertiesCountData($last12MonthsPropertyQuery);

            // Get last 12 months total, total Projects Count, month wise total project listed counts and total projects count of this month
            $last12MonthTotalProjectsCount = $last12MonthsProjectQuery->clone()->count();
            $currentMonthProjectCount = $last12MonthsProjectQuery->clone()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $monthlyProjectCounts = $this->getLast12MonthsProjectsCountData($last12MonthsProjectQuery);

            // Get last 12 months total, total Properties Views, month wise total property views and total properties views of this month
            $last12MonthsPropertyViews = $last12MonthsPropertyViewsQuery->clone()->sum('views');
            $currentMonthPropertyViews = $last12MonthsPropertyViewsQuery->clone()->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $monthlyPropertyViews = $this->getLast12MonthsPropertyViewsData($last12MonthsPropertyViewsQuery);

            // Get Current Month Total, Today Total, month wise total appointment counts and total appointments count of this month
            $currentMonthAppointmentCount = $appointmentQuery->clone()->whereBetween('start_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
            $todayAppointmentCount = $appointmentQuery->clone()->whereBetween('start_at', [now()->startOfDay(), now()->endOfDay()])->count();
            $monthlyAppointmentCounts = $this->getLast12MonthsAppointmentCounts($last12MonthsAppointmentQuery);

            // Get Chats Data
            $chats = Chats::with(['sender', 'receiver'])->with('property.translations')
                ->select(
                    'id',
                    'sender_id',
                    'receiver_id',
                    'property_id',
                    'created_at',
                    'message',
                    DB::raw('LEAST(sender_id, receiver_id) as user1_id'),
                    DB::raw('GREATEST(sender_id, receiver_id) as user2_id')
                )
                ->selectRaw('COUNT(CASE WHEN receiver_id = ? AND receiver_role_context = ? AND is_read = 0 THEN 1 END) AS unread_count', [$loggedInUser->id, $request->user_active_role])
                ->where(function ($query) use ($loggedInUser, $request) {
                    $query->where(function ($q) use ($loggedInUser, $request) {
                        $q->where('sender_id', $loggedInUser->id)->where('sender_role_context', $request->user_active_role);
                    })->orWhere(function ($q) use ($loggedInUser, $request) {
                        $q->where('receiver_id', $loggedInUser->id)->where('receiver_role_context', $request->user_active_role);
                    });
                })
                ->orderBy('id', 'desc')
                ->groupBy('user1_id', 'user2_id', 'property_id')
                ->limit(5)
                ->get()->map(function ($chat) use ($loggedInUser) {
                    if ($chat->property && $chat->property->category) {
                        $chat->property->translated_title = $chat->property->translated_title;
                        $chat->property->category->setAttribute(
                            'translated_name',
                            $chat->property->category->translated_name ?? ''
                        );
                    }

                    // Get Admin or Other user data not the logged in user
                    $otherUserID = $chat->sender_id == $loggedInUser->id ? $chat->receiver_id : $chat->sender_id;
                    if ($otherUserID == 0) {
                        $otherUser = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id')->first();
                    } else {
                        $otherUser = $chat->sender_id == $loggedInUser->id ? $chat->receiver : $chat->sender;
                    }
                    $chat->other_user = [
                        'id' => $otherUserID ?? null,
                        'name' => $otherUser->name ?? null,
                        'email' => $otherUser->email ?? null,
                        'profile' => $otherUser->profile ?? null,
                        'slug_id' => $otherUser->slug_id ?? null,
                    ];

                    $chat->last_message = $chat->message;
                    $chat->last_message_time = $chat->created_at;
                    unset($chat->sender);
                    unset($chat->receiver);
                    unset($chat->message);

                    return $chat;
                });

            $data = [
                'properties' => [
                    'total_properties' => $last12MonthTotalPropertiesCount,
                    'current_month_property_count' => $currentMonthPropertyCount,
                    'monthly_property_counts' => $monthlyPropertyCounts,
                ],
                'projects' => [
                    'total_projects' => $last12MonthTotalProjectsCount,
                    'current_month_project_count' => $currentMonthProjectCount,
                    'monthly_project_counts' => $monthlyProjectCounts,
                ],
                'properties_views' => [
                    'total_views' => $last12MonthsPropertyViews,
                    'current_month_property_views' => $currentMonthPropertyViews,
                    'monthly_property_views' => $monthlyPropertyViews,
                ],
                'appointments' => [
                    'current_month_appointment_count' => $currentMonthAppointmentCount,
                    'today_appointment_count' => $todayAppointmentCount,
                    'monthly_appointment_counts' => $monthlyAppointmentCounts,
                ],
                'chats' => $chats,
            ];

            return ApiResponseService::successResponse(trans('Agent dashboard summery data fetched successfully'), $data);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse('Something went wrong', $e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardListingsData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
                'range' => 'required|in:weekly,monthly,yearly',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $loggedInUser = Auth::user();
            $type = $request->type;
            $range = $request->range;

            // Range Type
            if ($range == 'weekly') {
                $rangeType = now()->startOfWeek();
            } elseif ($range == 'monthly') {
                $rangeType = now()->startOfMonth();
            } elseif ($range == 'yearly') {
                $rangeType = now()->subMonths(12);
            } else {
                ApiResponseService::validationError(trans('Invalid range'));
            }

            if ($type == 'property') {
                // Properties Query (only sell and rent properties)
                $propertiesQuery = Property::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => 'agent'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
                })->whereIn('propery_type', [0, 1]);

                // Range Property Query
                $rangePropertyQuery = $propertiesQuery->clone()->whereBetween('created_at', [$rangeType, now()]);

                // Property Listed Counts, Sell and rent Counts, Weekly Listed Counts
                $ovrerAllPropertyCounts = $this->getPropertyCounts($rangePropertyQuery);

                // Week Wise Data Property Counts
                $rangeWiseDataPropertyCounts = [];
                if ($range == 'weekly') {
                    $rangeWiseDataPropertyCounts = $this->currentWeekPropertiesCountData($rangePropertyQuery);
                } elseif ($range == 'monthly') {
                    $rangeWiseDataPropertyCounts = $this->currentMonthPropertyCountData($rangePropertyQuery);
                } elseif ($range == 'yearly') {
                    $rangeWiseDataPropertyCounts = $this->last12MonthsPropertyCountData($rangePropertyQuery);
                }

                $data = [
                    'overall' => $ovrerAllPropertyCounts,
                    'range_wise' => $rangeWiseDataPropertyCounts,
                ];

                return ApiResponseService::successResponse(trans('Listings data fetched successfully'), $data);
            } elseif ($type == 'project') {
                // Projects Query
                $projectsQuery = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => 'agent']);

                // Range Project Query
                $rangeProjectQuery = $projectsQuery->clone()->whereBetween('created_at', [$rangeType, now()]);
                $ovrerAllProjectCounts = $this->getProjectCounts($rangeProjectQuery);
                $rangeWiseDataProjectCounts = [];
                if ($range == 'weekly') {
                    $rangeWiseDataProjectCounts = $this->currentWeekProjectsCountData($rangeProjectQuery);
                } elseif ($range == 'monthly') {
                    $rangeWiseDataProjectCounts = $this->currentMonthProjectsCountData($rangeProjectQuery);
                } elseif ($range == 'yearly') {
                    $rangeWiseDataProjectCounts = $this->last12MonthsProjectsCountData($rangeProjectQuery);
                }

                // Get Last Week Projects Counts, Under Construction and Upcoming Counts, Weekly Listed Counts
                $data = [
                    'overall' => $ovrerAllProjectCounts,
                    'range_wise' => $rangeWiseDataProjectCounts,
                ];

                return ApiResponseService::successResponse(trans('Listings data fetched successfully'), $data);
            } else {
                ApiResponseService::errorResponse(trans('Invalid type'));
            }
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Recently Added Listings Data
    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardRecentlyAddedListingsData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            // Property Mapper
            $propertyMapper = function ($propertyData) {
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->parameters = $propertyData->parameters;
                $propertyData->is_premium = $propertyData->is_premium == 1;
                $propertyData->translated_title = $propertyData->translated_title;
                $propertyData->category->translated_name = $propertyData->category->translated_name;
                unset($propertyData->propery_type);

                return $propertyData;
            };

            $loggedInUser = Auth::user();
            $type = $request->type;
            if ($type == 'property') {
                // Get Recently Added Properties
                $recentlyListedData = Property::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => 'agent'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
                })->whereIn('propery_type', [0, 1])->clone()->with('category.translations', 'advertisement', 'interested_users:id,property_id,customer_id', 'interested_users.customer:id,name,profile', 'translations')->latest()->limit(5)->get()->map($propertyMapper);
            } elseif ($type == 'project') {
                // Get Recently Added Projects
                $recentlyListedData = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => 'agent'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
                })->clone()->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile', 'category.translations', 'translations')->latest()->limit(5)->get()->map($propertyMapper);
            } else {
                return ApiResponseService::errorResponse(trans('Invalid type'));
            }

            return ApiResponseService::successResponse(trans('Recently added listings data fetched successfully'), $recentlyListedData);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    // Get Agent Dashboard Packages Data
    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardActivePackagesData(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            $activePackageIds = HelperService::getAllActivePackageIds($loggedInUser->id, $request->user_active_role);

            if (empty($activePackageIds)) {
                ApiResponseService::successResponse(trans('No active packages found'), []);
            }

            $packages = Package::withTrashed()
                ->whereIn('id', $activePackageIds)
                ->with([
                    'package_features.feature.translations',
                    'package_features.user_package_limits' => function ($query) use ($loggedInUser) {
                        $query->whereHas('user_package', function ($userQuery) use ($loggedInUser) {
                            $userQuery->where('user_id', $loggedInUser->id)->orderBy('id', 'desc');
                        });
                    },
                    'user_packages' => function ($query) use ($loggedInUser) {
                        $query->where('user_id', $loggedInUser->id)->orderBy('id', 'desc');
                    },
                    'translations',
                ])
                ->get()
                ->map(function ($package) {
                    $userPackage = $package->user_packages->first();
                    if (! $userPackage) {
                        return null;
                    }

                    return [
                        'id' => $package->id,
                        'name' => $package->name,
                        'package_type' => $package->package_type,
                        'price' => $package->price,
                        'duration' => $package->duration,
                        'start_date' => $userPackage->start_date,
                        'end_date' => $userPackage->end_date,
                        'created_at' => $package->created_at,
                        'package_status' => $package->package_payment_status,
                        'translated_name' => $package->translated_name,
                        'features' => $package->package_features->map(function ($package_feature) {
                            $userPackageLimit = $package_feature->user_package_limits->first();

                            return [
                                'id' => $package_feature->feature->id,
                                'name' => $package_feature->feature->name,
                                'translated_name' => $package_feature->feature->translated_name,
                                'limit_type' => $package_feature->limit_type,
                                'limit' => $package_feature->limit,
                                'used_limit' => $userPackageLimit ? $userPackageLimit->used_limit : null,
                                'total_limit' => $userPackageLimit ? $userPackageLimit->total_limit : null,
                            ];
                        }),
                        'is_active' => 1,
                    ];
                })
                ->filter() // Remove null values
                ->values(); // Re-index array

            return ApiResponseService::successResponse(trans('Packages data fetched successfully'), $packages);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardMostViewedListingData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:property,project',
                'range' => 'required|in:weekly,monthly,last_three_months',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $type = $request->type;
            $range = $request->range;

            // Define range start
            if ($range == 'weekly') {
                $rangeStart = now()->startOfWeek();
            } elseif ($range === 'monthly') {
                $rangeStart = now()->startOfMonth();
            } elseif ($range === 'last_three_months') {
                $rangeStart = now()->subMonths(3);
            } else {
                return ApiResponseService::validationError(trans('Invalid range'));
            }

            if ($type === 'property') {
                $mostViewedData = PropertyView::selectRaw('property_id, SUM(views) as total_views')
                    ->whereBetween('created_at', [$rangeStart, now()])
                    ->whereHas('property', function ($query) use ($loggedInUser) {
                        $query->where([
                            'added_by' => $loggedInUser->id,
                            'status' => 1,
                            'request_status' => 'approved',
                            'role_context' => 'agent',
                        ]);
                    })
                    ->groupBy('property_id')
                    ->orderByDesc('total_views')
                    ->with('property.translations')
                    ->limit(5)
                    ->get()
                    ->map(function ($view) {
                        return [
                            'id' => $view->property_id,
                            'title' => $view->property->translated_title ?? $view->property->title,
                            'views' => (int) $view->total_views,
                        ];
                    });
            } else { // project
                $mostViewedData = ProjectView::selectRaw('project_id, SUM(views) as total_views')
                    ->whereBetween('created_at', [$rangeStart, now()])
                    ->whereHas('project', function ($query) use ($loggedInUser) {
                        $query->where([
                            'added_by' => $loggedInUser->id,
                            'status' => 1,
                            'request_status' => 'approved',
                            'role_context' => 'agent',
                        ]);
                    })
                    ->groupBy('project_id')
                    ->orderByDesc('total_views')
                    ->with('project.translations')
                    ->limit(5)
                    ->get()
                    ->map(function ($view) {
                        return [
                            'id' => $view->project_id,
                            'title' => $view->project->translated_title ?? $view->project->title,
                            'views' => (int) $view->total_views,
                        ];
                    });
            }

            return ApiResponseService::successResponse(trans('Most viewed data fetched successfully'), $mostViewedData);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardMostViewedCategoryData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'range' => 'required|in:weekly,monthly,yearly',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $range = $request->range;

            // Define range start
            if ($range === 'weekly') {
                $rangeStart = now()->startOfWeek();
            } elseif ($range === 'monthly') {
                $rangeStart = now()->startOfMonth();
            } elseif ($range === 'yearly') {
                $rangeStart = now()->startOfYear();
            } else {
                return ApiResponseService::validationError(trans('Invalid range'));
            }

            // Get property category views
            $propertyViews = PropertyView::whereBetween('created_at', [$rangeStart, now()])
                ->whereHas('property', function ($query) use ($loggedInUser) {
                    $query->where([
                        'added_by' => $loggedInUser->id,
                        'status' => 1,
                        'request_status' => 'approved',
                        'role_context' => 'agent',
                    ]);
                })
                ->with('property:id,category_id', 'property.category.translations')
                ->get()
                ->groupBy('property.category_id')
                ->map(fn ($group) => $group->sum('views'));

            // Get project category views
            $projectViews = ProjectView::whereBetween('created_at', [$rangeStart, now()])
                ->whereHas('project', function ($query) use ($loggedInUser) {
                    $query->where([
                        'added_by' => $loggedInUser->id,
                        'status' => 1,
                        'request_status' => 'approved',
                        'role_context' => 'agent',
                    ]);
                })
                ->with('project:id,category_id', 'project.category.translations')
                ->get()
                ->groupBy('project.category_id')
                ->map(fn ($group) => $group->sum('views'));

            // Combine property and project views by category
            $combinedViews = collect();

            // Add property views
            foreach ($propertyViews as $categoryId => $views) {
                $combinedViews->put($categoryId, ($combinedViews->get($categoryId, 0) + $views));
            }

            // Add project views
            foreach ($projectViews as $categoryId => $views) {
                $combinedViews->put($categoryId, ($combinedViews->get($categoryId, 0) + $views));
            }

            // Sort
            $mostViewedCategories = $combinedViews
                ->sortDesc()
                ->map(function ($totalViews, $categoryId) {
                    $category = Category::find($categoryId);

                    return [
                        'id' => $categoryId,
                        'title' => $category?->translated_name ?? $category?->name,
                        'views' => (int) $totalViews,
                    ];
                })
                ->values();

            return ApiResponseService::successResponse(
                trans('Most viewed categories fetched successfully'),
                $mostViewedCategories
            );
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentDashboardAppointmentData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer',
                'offset' => 'nullable|integer',
                'range' => 'required|in:weekly,monthly,yearly',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $loggedInUser = Auth::user();
            $range = $request->range;

            // Define range start
            if ($range == 'weekly') {
                $rangeStart = now()->startOfWeek();
                $rangeEnd = now()->endOfWeek();
            } elseif ($range === 'monthly') {
                $rangeStart = now()->startOfMonth();
                $rangeEnd = now()->endOfMonth();
            } elseif ($range === 'yearly') {
                $rangeStart = now()->startOfYear();
                $rangeEnd = now()->endOfYear();
            } else {
                return ApiResponseService::errorResponse(trans('Invalid range'));
            }

            // Appointments Data
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 5;
            $adminTimezone = HelperService::getSettingData('timezone');
            $agentTimezone = AgentBookingPreference::where('agent_id', $loggedInUser->id)->first()?->timezone ?? ($adminTimezone ?? 'UTC');

            // Appointment Query
            $appointmentQuery = Appointment::where(function ($query) use ($loggedInUser) {
                $query->where('agent_id', $loggedInUser->id)->orWhere('user_id', $loggedInUser->id);
            })->whereBetween('start_at', [$rangeStart, $rangeEnd]);
            // Total Appointments
            $totalAppointments = $appointmentQuery->clone()->count();
            // Appointments Data
            $appointmentData = $appointmentQuery->clone()->latest()->offset($offset)->limit($limit)->with('property:id,title,added_by,title_image', 'property.translations', 'agent:id,name')->get()->map(function ($appointment) use ($agentTimezone) {
                $appointment->date = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('d M Y');
                $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($agentTimezone)->format('H:i');
                $appointment->property->translated_title = $appointment->property->translated_title;

                return $appointment;
            });

            return ApiResponseService::successResponse(trans('Appointments fetched successfully'), $appointmentData, ['total' => $totalAppointments]);
        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function getAgentProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug_id' => 'required_without_all:id,is_admin',
            'is_projects' => 'nullable|in:1',
            'is_admin' => 'nullable|in:1',
            'search' => 'nullable|string',
            'filters' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
            // First decode filters from base64
            $filters = $request->filters ?? null;
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
            $radius = isset($filters['location']['radius']) ? $filters['location']['radius'] : null;
            $minPrice = isset($filters['price']['min_price']) ? $filters['price']['min_price'] : null;
            $maxPrice = isset($filters['price']['max_price']) ? $filters['price']['max_price'] : null;
            $postedSince = $filters['posted_since'] ?? null;
            $parameters = isset($filters['parameters']) ? $filters['parameters'] : null;
            $nearbyPlaces = isset($filters['nearby_places']) ? $filters['nearby_places'] : null;
            $search = isset($filters['search']) ? $filters['search'] : $request->search;
            $projectType = isset($filters['project_type']) ? $filters['project_type'] : null;

            // Get Flags from filters
            $promoted = isset($filters['flags']['promoted']) ? $filters['flags']['promoted'] : null;
            $getPremiumProperties = isset($filters['flags']['get_all_premium_properties']) ? $filters['flags']['get_all_premium_properties'] : null;
            $mostViewed = isset($filters['flags']['most_views']) ? $filters['flags']['most_views'] : null;
            $mostLiked = isset($filters['flags']['most_liked']) ? $filters['flags']['most_liked'] : null;

            $response = [
                'package_available' => false,
                'feature_available' => false,
                'limit_available' => false,
            ];
            // Get Limit Status of premium properties feature
            if (Auth::guard('sanctum')->check()) {
                $response = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), true, true, $request->user_active_role);
            }

            // dd($response);
            // Get Offset and Limit from payload request
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;
            $isAdminListing = false;

            if ($request->has('is_admin') && $request->is_admin == 1) {
                $addedBy = 0;
                $isAdminListing = true;
                $settings = HelperService::getMultipleSettingData(['company_email', 'company_tel1', 'company_address']);
                $adminEmail = $settings['company_email'];
                $adminCompanyTel1 = $settings['company_tel1'];
                $adminAddress = $settings['company_address'];
                $customerData = [];
                $adminPropertiesCount = Property::onlyActive()->where(['added_by' => 0])->when($request->filled('country_id'), function ($query) use ($request) {
                    $query->where('country_id', $request->country_id);
                })->count();
                $adminProjectsCount = Projects::onlyActive()->where(['is_admin_listing' => 1])->when($request->filled('country_id'), function ($query) use ($request) {
                    $query->where('country_id', $request->country_id);
                })->count();
                $totalCount = $adminPropertiesCount + $adminProjectsCount;

                $adminData = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id', 'type')->first();
                $adminData['is_agent'] = $adminData->is_agent;
                $adminData['is_appointment_available'] = $adminData->is_appointment_available;
                if ($adminData) {
                    $customerData = [
                        // Admin is represented as agent_id/added_by = 0 everywhere else
                        // (AgentFollow.agent_id, Property.added_by, ...) — normalize id to
                        // that convention here too, rather than the admin's real user id,
                        // so callers can consistently key off id === 0 for the admin entry.
                        'id' => 0,
                        'name' => $adminData->name,
                        'slug_id' => $adminData->slug_id,
                        'email' => ! empty($adminEmail) ? $adminEmail : '',
                        'mobile' => ! empty($adminCompanyTel1) ? $adminCompanyTel1 : '',
                        'address' => ! empty($adminAddress) ? $adminAddress : '',
                        'total_properties' => $adminPropertiesCount,
                        'total_projects' => $adminProjectsCount,
                        'total_count' => $totalCount,
                        // 'is_verify' => true,
                        // 'is_verified_user' => true,
                        'is_agent_verified' => true,
                        'profile' => ! empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg'),
                        'is_agent' => $adminData->is_agent,
                        'is_appointment_available' => $adminData->is_appointment_available,
                        'followers_count' => AgentFollow::where('agent_id', 0)->count(),
                        'is_following' => Auth::check() ? AgentFollow::where('user_id', Auth::id())->where('agent_id', 0)->exists() : false,
                        'is_admin' => true,
                    ];
                }
            } else {
                // Customer Query
                $customerQuery = Customer::select('id', 'slug_id', 'name', 'profile', 'mobile', 'email', 'address', 'city', 'country', 'country_id', 'state', 'latitude', 'longitude', 'is_agent', 'is_agent_verified')
                    ->with('agent_profile')
                    ->where(function ($query) {
                        $query->where('isActive', 1);
                    })->withCount(['projects as total_projects' => function ($query) use ($request) {
                        $query->onlyActive()->where('role_context', 'agent')
                            ->when($request->filled('country_id'), function ($q) use ($request) {
                                $q->where('country_id', $request->country_id);
                            });
                    }, 'property as total_properties' => function ($query) use ($request) {
                        $query->onlyActive()->where('role_context', 'agent')
                            ->when($request->filled('country_id'), function ($q) use ($request) {
                                $q->where('country_id', $request->country_id);
                            });
                    }, 'followers']);
                // Check if id exists or slug id on the basis of get agent id
                // $addedBy defaults to null so a request with neither a valid id nor slug_id
                // (e.g. id=0 — never a real customer id, PHP's empty() treats it as "not sent"
                // so it falls through both branches below) doesn't crash on an undefined
                // variable later — it just falls through to an empty "not found" response
                // (the same way admin's own properties are already served separately, via
                // is_admin=1, not id=0).
                $addedBy = null;
                $customerData = null;
                if ($request->filled('id')) {
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('id', $request->id)->first();
                    $addedBy = ! empty($customerData) ? $customerData->id : null;
                } elseif ($request->has('slug_id')) {
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('slug_id', $request->slug_id)->first();
                    $addedBy = ! empty($customerData) ? $customerData->id : null;
                }

                // Add Is User Verified Status in Customer Data
                // is_verified_user = true means the user has submitted a verification request and admin has approved it
                // !empty($customerData) ? $customerData->is_verify = $customerData->is_user_verified : "";
                // !empty($customerData) ? $customerData->is_verified_user = $customerData->is_user_verified : "";
                if (! empty($customerData)) {
                    $resolved = $customerData->applyResolvedAgentProfile();
                    $customerData->name = $resolved['agent_name'];
                    $customerData->email = $resolved['agent_email'];
                    $customerData->profile = $resolved['agent_profile_photo'];
                    $customerData->mobile = $resolved['agent_mobile'];
                    $customerData->is_agent = $customerData->is_agent;
                    $customerData->is_appointment_available = $customerData->is_appointment_available;
                    $customerData->is_agent_verified = $customerData->is_agent_verified;
                    $customerData->is_following = $customerData->is_following;
                    $customerData->followers_count = $customerData->followers_count;

                    // applyResolvedAgentProfile() above already replaced agent_profile with a
                    // plain array (not the AgentProfile model) — mutate a copy and reassign
                    // wholesale, since "$customerData->agent_profile->x = y" on an overloaded
                    // array property throws "Indirect modification of overloaded property".
                    if (is_array($customerData->agent_profile)) {
                        $profileExtras = $customerData->agent_profile_extras;
                        $agentProfileArray = $customerData->agent_profile;
                        $agentProfileArray['service_areas'] = $profileExtras['service_areas'];
                        $agentProfileArray['languages_spoken'] = $profileExtras['languages_spoken'];
                        $agentProfileArray['years_of_experience'] = $profileExtras['years_of_experience'];
                        $agentProfileArray['working_hours_start'] = $profileExtras['working_hours_start'];
                        $agentProfileArray['working_hours_end'] = $profileExtras['working_hours_end'];
                        $agentProfileArray['working_hours_total_per_day'] = $profileExtras['working_hours_total_per_day'];
                        $agentProfileArray['social_media_links'] = $customerData->social_media_links;
                        $customerData->agent_profile = $agentProfileArray;
                    }
                }
            }

            // if there is agent id then only get properties of it
            if (! empty($addedBy) || $addedBy === 0) {
                if (($request->has('is_projects') && ! empty($request->is_projects) && $request->is_projects == 1)) {
                    // Premium *projects* visibility must be gated by the
                    // PREMIUM_PROJECTS feature, not the PREMIUM_PROPERTIES
                    // one checked above for the properties branch — a
                    // package can grant one without the other.
                    $projectFeatureResponse = [
                        'package_available' => false,
                        'feature_available' => false,
                        'limit_available' => false,
                    ];
                    if (Auth::guard('sanctum')->check()) {
                        $projectFeatureResponse = HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'), true, true, $request->user_active_role);
                    }

                    // Always fetch non-premium projects regardless of package
                    $projectQuery = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location', 'category_id', 'added_by', 'is_premium');

                    // If Project Type is passed (0 = upcoming, 1 = under_construction)
                    if (isset($projectType) && $projectType !== null) {
                        $typeValue = $projectType == 0 ? 'upcoming' : 'under_construction';
                        $projectQuery = $projectQuery->where('type', $typeValue);
                    }

                    // If Category Id is Passed
                    if (isset($categoryId) && ! empty($categoryId)) {
                        $projectQuery = $projectQuery->where('category_id', $categoryId);
                    }

                    // If Category Slug is Passed
                    if (isset($categorySlugId) && ! empty($categorySlugId)) {
                        $projectQuery = $projectQuery->whereHas('category', function ($query) use ($categorySlugId) {
                            $query->where('slug_id', $categorySlugId);
                        });
                    }

                    // If Country is passed
                    if (isset($country) && ! empty($country)) {
                        $projectQuery = $projectQuery->where('country', 'like', '%'.$country.'%');
                    }

                    // If the user has a country selected, scope all results
                    // to it regardless of any free-text location filters above.
                    $projectQuery = $projectQuery->when($request->filled('country_id'), function ($query) use ($request) {
                        return $query->where('country_id', $request->country_id);
                    });

                    // If State is passed
                    if (isset($state) && ! empty($state)) {
                        $projectQuery = $projectQuery->where('state', 'like', '%'.$state.'%');
                    }

                    // If City is passed
                    if (isset($city) && ! empty($city)) {
                        $projectQuery = $projectQuery->where('city', 'like', '%'.$city.'%');
                    }

                    // If place ID is passed, resolve it to city name
                    if (isset($placeId) && ! empty($placeId)) {
                        $locationData = $this->resolvePlaceIdToLocation($placeId);
                        if ($locationData) {
                            if ($locationData['city']) {
                                $projectQuery = $projectQuery->where('city', $locationData['city']);
                            }
                            if ($locationData['state']) {
                                $projectQuery = $projectQuery->where('state', $locationData['state']);
                            }
                            if ($locationData['country']) {
                                $projectQuery = $projectQuery->where('country', $locationData['country']);
                            }
                        }
                    }

                    // Location radius filter (lat/long + radius in km). Radius no longer
                    // excludes rows — it only decides sort order (see $projectRadiusSort
                    // below, applied after the promoted-first ordering).
                    $projectRadiusSort = null;
                    if (! empty($latitude) && ! empty($longitude) && $latitude != 'null' && $longitude != 'null') {
                        if (! empty($radius) && $radius != 'null') {
                            $projectQuery = $projectQuery->where('latitude', '!=', 0)
                                ->where('longitude', '!=', 0);
                            $projectRadiusSort = ['lat' => $latitude, 'lng' => $longitude, 'radius' => $radius];
                        } else {
                            $projectQuery = $projectQuery->where('latitude', $latitude)->where('longitude', $longitude);
                        }
                    }

                    $projectQuery = $projectQuery->when(! empty($search), function ($query) use ($search) {
                        $query->where(function ($whereCondition) use ($search) {
                            $whereCondition->where('title', 'like', '%'.$search.'%')
                                ->orWhere('city', 'like', '%'.$search.'%')
                                ->orWhere('state', 'like', '%'.$search.'%')
                                ->orWhere('country', 'like', '%'.$search.'%')
                                ->orWhereHas('category', function ($query) use ($search) {
                                    $query->where('category', 'like', '%'.$search.'%');
                                })
                                ->orWhere(function ($query) use ($search) {
                                    $query->searchInAnyTranslation($search);
                                });
                        });
                    });

                    if ($isAdminListing == true) {
                        $projectQuery = $projectQuery->clone()->where(['status' => 1, 'is_admin_listing' => 1]);
                    } else {
                        $projectQuery = $projectQuery->clone()->onlyActive()->where(['added_by' => $addedBy, 'role_context' => 'agent']);
                    }
                    // Flag: promoted -> only projects with an active advertisement
                    if (isset($promoted) && ! empty($promoted) && $promoted == 1) {
                        $projectQuery = $projectQuery->whereHas('advertisement', function ($query) {
                            $query->where(['status' => 0, 'is_enable' => 1]);
                        });
                    }

                    // Flag: get_all_premium_properties -> only premium projects
                    if (isset($getPremiumProperties) && ! empty($getPremiumProperties) && $getPremiumProperties == 1) {
                        $projectQuery = $projectQuery->where('is_premium', 1);
                    }

                    // Note: most_liked is not supported for projects (no favourite relationship on Projects)

                    // Total count always includes premium + non-premium, active/not-expired
                    // only — independent of whether the viewer's package can see premium
                    // listings. The premium filter below only trims the *returned* listing
                    // data for viewers without premium access, not the count.
                    $totalProjects = $projectQuery->clone()->count();
                    $totalData = $totalProjects;

                    // Only show non-premium projects if feature is not available (no valid package)
                    if ($projectFeatureResponse['feature_available'] == false) {
                        $projectQuery = $projectQuery->where('is_premium', 0);
                    }

                    // Add promoted_count for promoted-first ordering
                    $projectQuery = $projectQuery->withCount([
                        'advertisement as promoted_count' => function ($query) {
                            $query->where('status', 0)
                                ->where('is_enable', 1)
                                ->where('for', 'project')
                                ->groupBy('project_id');
                        },
                    ]);

                    // Always group promoted projects first
                    $projectQuery = $projectQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END');

                    // Flag: most_views -> randomize promoted, order non-promoted by total_click DESC
                    if (isset($mostViewed) && ! empty($mostViewed) && $mostViewed == 1) {
                        $projectQuery = $projectQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - total_click) END');
                    }

                    // Radius sort: within the promoted/non-promoted groups above,
                    // in-radius projects sort first (nearest to farthest), then the
                    // rest of the country follows.
                    if ($projectRadiusSort) {
                        $projectQuery = $projectQuery->orderByRaw(
                            'CASE WHEN (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ? THEN 0 ELSE 1 END ASC',
                            [$projectRadiusSort['lat'], $projectRadiusSort['lng'], $projectRadiusSort['lat'], $projectRadiusSort['radius']]
                        )->orderByRaw(
                            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) ASC',
                            [$projectRadiusSort['lat'], $projectRadiusSort['lng'], $projectRadiusSort['lat']]
                        );
                    }

                    // Always return project data (no package check)
                    $projectData = $projectQuery->clone()->with('gallary_images', 'category:id,slug_id,image,category', 'category.translations', 'translations')->skip($offset)->take($limit)->get()->map(function ($project) {
                        if ($project->category) {
                            $project->category->translated_name = $project->category->translated_name;
                        }
                        $project->translated_title = $project->translated_title;
                        $project->translated_description = $project->translated_description;

                        return $project;
                    });
                    // Force feature_available = true for projects response
                    $response['feature_available'] = true;
                    $response['package_available'] = $response['package_available'] ?? true;
                } else {
                    // Create a proeprty query
                    $basePropertiesQuery = Property::onlyActive()
                        ->when($isAdminListing, function ($query) use ($addedBy) {
                            $query->where('added_by', $addedBy);
                        }, function ($query) use ($addedBy) {
                            $query->where(['added_by' => $addedBy, 'role_context' => 'agent']);
                        })
                        ->when(! empty($search), function ($query) use ($search) {
                            $query->where(function ($whereCondition) use ($search) {
                                $whereCondition->where('title', 'like', '%'.$search.'%')
                                    ->orWhere('address', 'like', '%'.$search.'%')
                                    ->orWhereHas('category', function ($query) use ($search) {
                                        $query->where('category', 'like', '%'.$search.'%');
                                    })
                                    ->orWhere(function ($query) use ($search) {
                                        $query->searchInAnyTranslation($search);
                                    });
                            });
                        });
                        
                    $propertiesQuery = $basePropertiesQuery->clone();

                    // If Property Type Passed
                    if (isset($propertyType) && (! empty($propertyType) || $propertyType == 0)) {
                        $propertiesQuery = $propertiesQuery->where('propery_type', $propertyType);
                    }

                    // If Category Id is Passed
                    if (isset($categoryId) && ! empty($categoryId)) {
                        $propertiesQuery = $propertiesQuery->where('category_id', $categoryId);
                    }

                    // If Category Slug is Passed
                    if (isset($categorySlugId) && ! empty($categorySlugId)) {
                        $propertiesQuery = $propertiesQuery->whereHas('category', function ($query) use ($categorySlugId) {
                            $query->where('slug_id', $categorySlugId);
                        });
                    }

                    // If parameter id (+ optionally value) passed
                    $propertiesQuery = HelperService::applyParameterFilters($propertiesQuery, $parameters);

                    if (isset($nearbyPlaces) && ! empty($nearbyPlaces)) {
                        foreach ($nearbyPlaces as $nearbyPlace) {
                            $nearbyPlaceId = $nearbyPlace['id'];
                            $nearbyPlaceValue = $nearbyPlace['value'];
                            if (isset($nearbyPlace['value']) && ! empty($nearbyPlace['value'])) {
                                $propertiesQuery = $propertiesQuery->whereHas('assignfacilities', function ($query) use ($nearbyPlaceId, $nearbyPlaceValue) {
                                    $query->where('facility_id', $nearbyPlaceId)->where('distance', '<=', $nearbyPlaceValue);
                                });
                            } else {
                                $propertiesQuery = $propertiesQuery->whereHas('assignfacilities', function ($query) use ($nearbyPlaceId) {
                                    $query->where('facility_id', $nearbyPlaceId);
                                });
                            }
                        }
                    }

                    // If Country is passed
                    if (isset($country) && ! empty($country)) {
                        $propertiesQuery = $propertiesQuery->where('country', 'like', '%'.$country.'%');
                    }

                    // If the user has a country selected, scope all results
                    // to it regardless of any free-text location filters above.
                    $propertiesQuery = $propertiesQuery->when($request->filled('country_id'), function ($query) use ($request) {
                        return $query->where('country_id', $request->country_id);
                    });

                    // If State is passed
                    if (isset($state) && ! empty($state)) {
                        $propertiesQuery = $propertiesQuery->where('state', 'like', '%'.$state.'%');
                    }

                    // If City is passed
                    if (isset($city) && ! empty($city)) {
                        $propertiesQuery = $propertiesQuery->where('city', 'like', '%'.$city.'%');
                    }

                    // If place ID is passed, resolve it to city name
                    if (isset($placeId) && ! empty($placeId)) {
                        $locationData = $this->resolvePlaceIdToLocation($placeId);
                        if ($locationData) {
                            if ($locationData['city']) {
                                $propertiesQuery = $propertiesQuery->where('city', $locationData['city']);
                            }
                            if ($locationData['state']) {
                                $propertiesQuery = $propertiesQuery->where('state', $locationData['state']);
                            }
                            if ($locationData['country']) {
                                $propertiesQuery = $propertiesQuery->where('country', $locationData['country']);
                            }
                        }
                    }

                    // Location radius filter (lat/long + radius in km). Radius no longer
                    // excludes rows — it only decides sort order (see $propertyRadiusSort
                    // below, applied after the promoted-first ordering).
                    $propertyRadiusSort = null;
                    if (! empty($latitude) && ! empty($longitude) && $latitude != 'null' && $longitude != 'null') {
                        if (! empty($radius) && $radius != 'null') {
                            $propertiesQuery = $propertiesQuery->where('latitude', '!=', 0)
                                ->where('longitude', '!=', 0);
                            $propertyRadiusSort = ['lat' => $latitude, 'lng' => $longitude, 'radius' => $radius];
                        } else {
                            $propertiesQuery = $propertiesQuery->where('latitude', $latitude)->where('longitude', $longitude);
                        }
                    }

                    // If Max Price And Min Price passed
                    if (isset($minPrice) && ! empty($minPrice)) {
                        $propertiesQuery = $propertiesQuery->where('price', '>=', $minPrice);
                    }

                    if (isset($maxPrice) && ! empty($maxPrice)) {
                        $propertiesQuery = $propertiesQuery->where('price', '<=', $maxPrice);
                    }

                    // If Posted Since is passed
                    if (isset($postedSince) && $postedSince !== '') {
                        if ($postedSince == 0) {
                            // Last Week (last 7 days)
                            $propertiesQuery = $propertiesQuery->where('created_at', '>=', Carbon::now()->subWeek());
                        }
                        if ($postedSince == 1) {
                            $yesterdayDate = Carbon::yesterday();
                            $propertiesQuery = $propertiesQuery->whereDate('created_at', $yesterdayDate);
                        }
                        if ($postedSince == 2) {
                            $lastMonthDate = Carbon::now()->subMonth();
                            $today = Carbon::now()->endOfDay();
                            $propertiesQuery = $propertiesQuery->whereBetween('created_at', [$lastMonthDate, $today]);
                        }
                    }

                    // Flag: promoted -> only properties with an active advertisement
                    if (isset($promoted) && ! empty($promoted) && $promoted == 1) {
                        $propertiesQuery = $propertiesQuery->whereHas('advertisement', function ($query) {
                            $query->where(['status' => 0, 'is_enable' => 1]);
                        });
                    }

                    // Flag: get_all_premium_properties -> only premium properties
                    if (isset($getPremiumProperties) && ! empty($getPremiumProperties) && $getPremiumProperties == 1) {
                        $propertiesQuery = $propertiesQuery->where('is_premium', 1);
                    }

                    // Count premium properties without the condition
                    $premiumPropertiesCount = $propertiesQuery->clone()->where('is_premium', 1)->count();

                    // Total count always includes premium + non-premium, active/not-expired
                    // only — independent of whether the viewer's package can see premium
                    // listings. The premium filter below only trims the *returned* listing
                    // data for viewers without premium access, not the count.
                    $totalProperties = $propertiesQuery->clone()->count();

                    $propertiesQuery = $propertiesQuery->when(($response['feature_available'] == false), function ($query) {
                        $query->where('is_premium', 0);
                    });

                    // Get Propertis Data
                    $propertiesDataQuery = $propertiesQuery->clone()
                        ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                        ->select('id', 'slug_id', 'city', 'state', 'category_id', 'country', 'price', 'propery_type', 'title', 'title_image', 'is_premium', 'address', 'added_by', 'role_context')
                        ->withCount([
                            'advertisement as promoted_count' => function ($query) {
                                $query->where('status', 0)
                                    ->where('is_enable', 1)
                                    ->where('for', 'property')
                                    ->groupBy('property_id');
                            },
                        ])
                        ->withCount('favourite');

                    if (isset($mostViewed) && ! empty($mostViewed) && $mostViewed == 1) {
                        // most_views: promoted first (randomized), then non-promoted by total_click DESC
                        $propertiesDataQuery = $propertiesDataQuery
                            ->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END')
                            ->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - total_click) END');
                    } elseif (isset($mostLiked) && ! empty($mostLiked) && $mostLiked == 1) {
                        // most_liked: promoted first (randomized), then non-promoted by favourite_count DESC
                        $propertiesDataQuery = $propertiesDataQuery
                            ->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END')
                            ->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - favourite_count) END');
                    } else {
                        $propertiesDataQuery = $propertiesDataQuery->orderBy('is_premium', 'DESC');
                    }

                    // Radius sort: within the ordering above, in-radius properties sort
                    // first (nearest to farthest), then the rest of the country follows.
                    if ($propertyRadiusSort) {
                        $propertiesDataQuery = $propertiesDataQuery->orderByRaw(
                            'CASE WHEN (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ? THEN 0 ELSE 1 END ASC',
                            [$propertyRadiusSort['lat'], $propertyRadiusSort['lng'], $propertyRadiusSort['lat'], $propertyRadiusSort['radius']]
                        )->orderByRaw(
                            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) ASC',
                            [$propertyRadiusSort['lat'], $propertyRadiusSort['lng'], $propertyRadiusSort['lat']]
                        );
                    }

                    $propertiesData = $propertiesDataQuery->skip($offset)->take($limit)->get()->map(function ($property) {
                            $property->property_type = $property->propery_type;
                            $property->parameters = $property->parameters;
                            $property->promoted = $property->is_promoted;
                            if ($property->category) {
                                $property->category->translated_name = $property->category->translated_name;
                            }
                            $property->translated_title = $property->translated_title;
                            $property->translated_description = $property->translated_description;
                            unset($property->propery_type);

                            return $property;
                        });
                    $totalData = $totalProperties;
                    $totalSoldProperties = $basePropertiesQuery->clone()->where('propery_type', 2)->count();
                    $totalRentedProperties = $basePropertiesQuery->clone()->where('propery_type', 3)->count();
                }
            }
            // customer_data.total_properties/total_projects come from an unfiltered
            // withCount (or, for the admin entry, an unfiltered raw count) computed
            // before any of the search/category/price/location/promoted/premium
            // filters above were applied — sync them to the actually-returned,
            // fully-filtered count so they never diverge from properties_data/projects_data.
            //
            // customerData must become a plain array first: Customer defines
            // getTotalPropertiesAttribute()/getTotalProjectsAttribute() accessors that
            // run their own unconditional count() (no onlyActive()/expiry check at all)
            // and always win over a manually-set attribute on the model (accessors take
            // precedence over raw attributes on every read/serialize, including toArray())
            // — so grab the accurate withCount()-computed value via getRawOriginal()
            // *before* converting, or the accessor silently clobbers it with an
            // unfiltered count that still includes expired/inactive listings.
            if (is_object($customerData)) {
                $rawTotalProperties = (int) $customerData->getRawOriginal('total_properties');
                $rawTotalProjects = (int) $customerData->getRawOriginal('total_projects');
                $customerData = $customerData->toArray();
                $customerData['total_properties'] = $rawTotalProperties;
                $customerData['total_projects'] = $rawTotalProjects;
                $customerData['is_admin'] = false;
            }

            if (isset($totalProperties)) {
                $customerData['total_properties'] = $totalProperties;
            }
            if (isset($totalProjects)) {
                $customerData['total_projects'] = $totalProjects;
            }

            // Add Sold and Rented Count in Customer Data
            $customerData['properties_sold_count'] = $totalSoldProperties ?? 0;
            $customerData['properties_rented_count'] = $totalRentedProperties ?? 0;

            $response = [
                'error' => false,
                'total' => $totalData ?? 0,
                'data' => [
                    'customer_data' => $customerData ?? [],
                    'properties_data' => $propertiesData ?? [],
                    'projects_data' => $projectData ?? [],
                    'premium_properties_count' => $premiumPropertiesCount ?? 0,
                    'package_available' => $response['package_available'] ?? true,
                    'feature_available' => $response['feature_available'] ?? true,

                ],
                'message' => trans('Data Fetched Successfully'),
            ];

            return response()->json($response);
        } catch (Exception $e) {
            Log::error('getAgentProperties error: '.$e->getMessage().' --> '.$e->getFile().' At Line : '.$e->getLine());

            return response()->json([
                'error' => true,
                'message' => trans('Something Went Wrong'),
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // helper function to get property counts

    private function getPropertyCounts($properyQuery)
    {
        $totalPropertiesCount = $properyQuery->clone()->count();
        $sellPropertiesCount = $properyQuery->clone()->where('propery_type', 0)->count();
        $rentPropertiesCount = $properyQuery->clone()->where('propery_type', 1)->count();

        return [
            'total_properties' => $totalPropertiesCount,
            'sell_properties' => $sellPropertiesCount,
            'rent_properties' => $rentPropertiesCount,
        ];
    }

    // helper function to get project counts
    private function getProjectCounts($projectQuery)
    {
        $totalProjectsCount = $projectQuery->clone()->count();
        $underConstructionProjectsCount = $projectQuery->clone()->where('type', 'under_construction')->count();
        $upcomingProjectsCount = $projectQuery->clone()->where('type', 'upcoming')->count();

        return [
            'total_projects' => $totalProjectsCount,
            'under_construction_projects' => $underConstructionProjectsCount,
            'upcoming_projects' => $upcomingProjectsCount,
        ];
    }

    // helper function to get current week properties count data
    private function currentWeekPropertiesCountData($propertyQuery)
    {
        $weeklyPropertyCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();

            $dayProperties = $propertyQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $weeklyPropertyCounts[] = [
                'date' => $date,
                'day_name' => now()->subDays($i)->format('l'),
                'total_properties' => $dayProperties->clone()->count(),
                'sell_properties' => $dayProperties->clone()->where('propery_type', 0)->count(),
                'rent_properties' => $dayProperties->clone()->where('propery_type', 1)->count(),
            ];
        }

        return $weeklyPropertyCounts;
    }

    // helper function to get current week projects count data
    private function currentWeekProjectsCountData($projectQuery)
    {
        $weeklyProjectCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();

            $dayProperties = $projectQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $weeklyProjectCounts[] = [
                'date' => $date,
                'day_name' => now()->subDays($i)->format('l'),
                'total_projects' => $dayProperties->clone()->count(),
                'under_construction_projects' => $dayProperties->clone()->where('type', 'under_construction')->count(),
                'upcoming_projects' => $dayProperties->clone()->where('type', 'upcoming')->count(),
            ];
        }

        return $weeklyProjectCounts;
    }

    private function currentMonthPropertyCountData($propertyQuery)
    {
        $monthlyPropertyCounts = [];
        $daysInMonth = now()->daysInMonth; // total days in current month

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = now()->startOfMonth()->addDays($i - 1);

            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $dayProperties = $propertyQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $monthlyPropertyCounts[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'total_properties' => $dayProperties->clone()->count(),
                'sell_properties' => $dayProperties->clone()->where('propery_type', 0)->count(),
                'rent_properties' => $dayProperties->clone()->where('propery_type', 1)->count(),
            ];
        }

        return $monthlyPropertyCounts;
    }

    private function currentMonthProjectsCountData($projectQuery)
    {
        $monthlyProjectCounts = [];
        $daysInMonth = now()->daysInMonth; // total days in current month

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = now()->startOfMonth()->addDays($i - 1);

            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $dayProjects = $projectQuery->clone()
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $monthlyProjectCounts[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'total_projects' => $dayProjects->clone()->count(),
                'under_construction_projects' => $dayProjects->clone()->where('type', 'under_construction')->count(),
                'upcoming_projects' => $dayProjects->clone()->where('type', 'upcoming')->count(),
            ];
        }

        return $monthlyProjectCounts;
    }

    private function last12MonthsPropertyCountData($propertyQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $monthPropertiesCounts = $propertyQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd]);

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_properties' => $monthPropertiesCounts->clone()->count(),
                'sell_properties' => $monthPropertiesCounts->clone()->where('propery_type', 0)->count(),
                'rent_properties' => $monthPropertiesCounts->clone()->where('propery_type', 1)->count(),
            ];
        }

        return $last12MonthsCounts;
    }

    private function last12MonthsProjectsCountData($projectQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $monthProjectCounts = $projectQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd]);

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_projects' => $monthProjectCounts->clone()->count(),
                'under_construction_projects' => $monthProjectCounts->clone()->where('type', 'under_construction')->count(),
                'upcoming_projects' => $monthProjectCounts->clone()->where('type', 'upcoming')->count(),
            ];
        }

        return $last12MonthsCounts;
    }

    private function getLast12MonthsPropertiesCountData($propertyQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $monthPropertiesCounts = $propertyQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->count();

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_properties' => $monthPropertiesCounts,
            ];
        }

        return $last12MonthsCounts;
    }

    private function getLast12MonthsProjectsCountData($projectQuery)
    {
        $last12MonthsCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $monthProjectCounts = $projectQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->count();

            $last12MonthsCounts[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_projects' => $monthProjectCounts,
            ];
        }

        return $last12MonthsCounts;
    }

    private function getLast12MonthsPropertyViewsData($propertyViewsQuery)
    {
        $last12MonthsPropertyViews = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            $monthProperties = $propertyViewsQuery->clone()->whereBetween('created_at', [$monthStart, $monthEnd])->sum('views');

            $last12MonthsPropertyViews[] = [
                'month' => $month->format('F Y'),
                'month_key' => $month->format('Y-m'),
                'total_views' => (int) $monthProperties,
            ];
        }

        return $last12MonthsPropertyViews;
    }

    private function getLast12MonthsAppointmentCounts($appointmentQuery)
    {
        $last12MonthsAppointmentCounts = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthName = $monthStart->format('F Y');
            $monthKey = $monthStart->format('Y-m');

            $totalAppointmentCounts = $appointmentQuery->clone()->whereYear('start_at', $monthStart->year)->whereMonth('start_at', $monthStart->month)->count();
            $last12MonthsAppointmentCounts[] = [
                'month' => $monthName,
                'month_key' => $monthKey,
                'total_appointments' => $totalAppointmentCounts,
            ];
        }

        return $last12MonthsAppointmentCounts;
    }

    private function resolvePlaceIdToLocation($placeId)
    {
        $googleApiKey = env('PLACE_API_KEY');
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'X-Goog-Api-Key' => $googleApiKey,
            'X-Goog-FieldMask' => 'addressComponents',
        ])->get("https://places.googleapis.com/v1/places/{$placeId}");

        if ($response->successful()) {
            $data = $response->json();
            $location = [
                'city' => null,
                'state' => null,
                'country' => null,
            ];

            if (isset($data['addressComponents']) && ! empty($data['addressComponents'])) {
                foreach ($data['addressComponents'] as $component) {
                    $types = $component['types'];

                    // Extract city (locality)
                    if (in_array('locality', $types)) {
                        $location['city'] = $component['longText'];
                    }
                    // Extract state (administrative_area_level_1)
                    elseif (in_array('administrative_area_level_1', $types)) {
                        $location['state'] = $component['longText'];
                    }
                    // Extract country
                    elseif (in_array('country', $types)) {
                        $location['country'] = $component['longText'];
                    }
                }
            } else {
                \Illuminate\Support\Facades\Log::error($data);
            }

            return $location;
        }

        return null;
    }
}
