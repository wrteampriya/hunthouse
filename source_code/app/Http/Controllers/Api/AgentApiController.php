<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\AgentBookingPreference;
use App\Models\AgentProfile;
use App\Models\AgentVerification;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\Chats;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Projects;
use App\Models\ProjectView;
use App\Models\Property;
use App\Models\PropertyView;
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

class AgentApiController extends Controller
{
    public function getAgentList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) && ! empty($request->limit) ? $request->limit : 10;

            $latitude = $request->has('latitude') ? $request->latitude : null;
            $longitude = $request->has('longitude') ? $request->longitude : null;

            if (! empty($request->limit)) {
                $agentsListQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'is_agent')
                    ->with('agent_profile')
                    ->where(function ($query) {
                        $query->where('isActive', 1);
                    })
                    // Include customers who are either user-verified or agents
                    ->where(function ($query) {
                        $query->whereHas('becomeAgent', function ($q) {
                            $q->where('status', 'approved');
                        });
                    })
                    ->where(function ($query) use ($latitude, $longitude) {
                        $query->whereHas('projects', function ($query) use ($latitude, $longitude) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            });
                        })->orWhereHas('property', function ($query) use ($latitude, $longitude) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            });
                        });
                    })
                    ->withCount([
                        'projects' => function ($query) use ($latitude, $longitude) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            });
                        },
                        'property' => function ($query) use ($latitude, $longitude) {
                            $query->onlyActive()->where('role_context', 'agent')->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                                $query->where('latitude', $latitude)->where('longitude', $longitude);
                            });
                        },
                    ]);

                // $agentData = AgentProfile::where('user_id', Auth::user()->id);

                $agentListCount = $agentsListQuery->clone()->count();

                $agentListData = $agentsListQuery->clone()
                    ->get()
                    ->map(function ($customer) {
                        $resolved = $customer->resolved_agent_profile;
                        $customer->name = $resolved['agent_name'];
                        $customer->email = $resolved['agent_email'];
                        $customer->profile = $resolved['agent_profile_photo'];
                        $customer->agent_address = $resolved['agent_address'];
                        $customer->agent_profile = $resolved;
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
                    ->skip($offset)
                    ->take($limit)
                    ->values(); // This line resets the array keys

                // dd($agentListData);
                // Get admin List

                $adminEmail = system_setting('company_email');
                $adminData = [];
                $adminPropertiesCount = Property::onlyActive()->where(['added_by' => 0])->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                    $query->where('latitude', $latitude)->where('longitude', $longitude);
                })->count();
                $adminProjectsCount = Projects::onlyActive()->where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->when($latitude && $longitude, function ($query) use ($latitude, $longitude) {
                    $query->where('latitude', $latitude)->where('longitude', $longitude);
                })->count();
                $totalCount = $adminPropertiesCount + $adminProjectsCount;

                $adminData = User::where('type', 0)->select('id', 'name', 'profile')->first();

                $adminQuery = User::where('type', 0)->select('id', 'slug_id')->first();
                if ($adminQuery && ($adminPropertiesCount > 0 || $adminProjectsCount > 0)) {
                    $adminData = [
                        'id' => $adminQuery->id,
                        'name' => 'Admin',
                        'slug_id' => $adminQuery->slug_id,
                        'email' => ! empty($adminEmail) ? $adminEmail : '',
                        'property_count' => $adminPropertiesCount,
                        'projects_count' => $adminProjectsCount,
                        'total_count' => $totalCount,
                        // 'is_verified' => true,
                        // 'is_verified_user' => true,
                        'is_agent_verified' => true,
                        'profile' => ! empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg'),
                        'is_admin' => true,
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

    public function getAgentProfile(Request $request)
    {
        try {
            $customer = Auth::user();

            $customerData = Customer::with('agent_profile')
                ->where('id', $customer->id)
                ->first();
            // Add agent_verification_reject_reason if exist
            $rejectReason = AgentVerification::with('rejectReason')->where('customer_id', $customer->id)->where('form_type', 'verify_agent')->first()?->rejectReason?->reason ?? '';

            // Step 1: Pass MODEL to resource (important)
            $resource = new CustomerResource(
                $customerData,
                ['become_agent_status', 'agent_verification_status']
            );

            // Step 2: Resolve resource to array
            $data = $resource->resolve();

            // Step 3: Override only required fields
            $resolvedProfile = $customerData->resolved_agent_profile;

            $finalData = [
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
                'is_admin_added' => $customerData->is_admin_added ?? false,
                'isActive' => $customerData->isActive ?? false,
                'is_demo_user' => $customerData->is_demo_user ?? false,
                'is_appointment_available' => $customerData->is_appointment_available ?? false,
                'become_agent_status' => $data['become_agent_status'] ?? 'not_applied',
                'agent_verification_status' => $data['agent_verification_status'] ?? 'not_applied',
                'agent_verification_reject_reason' => $rejectReason,
                'facebook_id' => $resolvedProfile['facebook_id'] ?? '',
                'twitter_id' => $resolvedProfile['twitter_id'] ?? '',
                'youtube_id' => $resolvedProfile['youtube_id'] ?? '',
                'instagram_id' => $resolvedProfile['instagram_id'] ?? '',
            ];

            return response()->json([
                'error' => false,
                'data' => $finalData,
                'message' => trans('Data Fetched Successfully'),
            ]);

        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function updateAgentProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agent_name' => 'nullable|string|max:255',
                'agent_email' => 'nullable|email|max:255',
                'agent_profile_photo' => 'nullable|file|max:3000|mimes:jpeg,png,jpg,webp',
                'about_me' => 'nullable|string|max:1000',
                'facebook_id' => 'nullable|string|max:255',
                'twitter_id' => 'nullable|string|max:255',
                'youtube_id' => 'nullable|string|max:255',
                'instagram_id' => 'nullable|string|max:255',
                'agent_address' => 'nullable|string|max:500',
                'agent_mobile' => 'nullable|string|max:20',
                'agent_country_code' => 'nullable|string|max:10',
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

            if ($request->has('facebook_id')) {
                $agentProfile->facebook_id = $request->facebook_id;
            }

            if ($request->has('twitter_id')) {
                $agentProfile->twitter_id = $request->twitter_id;
            }

            if ($request->has('youtube_id')) {
                $agentProfile->youtube_id = $request->youtube_id;
            }

            if ($request->has('instagram_id')) {
                $agentProfile->instagram_id = $request->instagram_id;
            }

            if ($request->has('agent_address')) {
                $agentProfile->agent_address = $request->agent_address;
            }

            if ($request->has('agent_mobile')) {
                $agentProfile->agent_mobile = $request->agent_mobile;
            }

            if ($request->has('agent_country_code')) {
                $agentProfile->agent_country_code = $request->agent_country_code;
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
                'data' => $agentProfile->fresh(),
                'message' => trans('Agent profile updated successfully'),
            ]);
        } catch (Exception $e) {
            DB::rollback();

            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

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

    public function getAgentDashboardSummeryData(Request $request)
    {
        try {
            $loggedInUser = Auth::user();
            $last12Months = now()->subMonths(12);
            // Properties Query (only sell and rent properties)
            $propertiesQuery = Property::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => $request->user_active_role])->where(function ($q) {
                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
            })->whereIn('propery_type', [0, 1]);

            // Projects Query
            $projectsQuery = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => $request->user_active_role])->where(function ($q) {
                $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
            });

            // Property Views Query
            $propertyViewsQuery = PropertyView::whereHas('property', function ($query) use ($loggedInUser, $request) {
                $query->where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => $request->user_active_role])->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
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
                    DB::raw('GREATEST(sender_id, receiver_id) as user2_id'),
                    DB::raw('COUNT(CASE WHEN receiver_id = '.$loggedInUser->id.' AND is_read = 0 THEN 1 END) AS unread_count')
                )
                ->where(function ($query) use ($loggedInUser) {
                    $query->where('sender_id', $loggedInUser->id)
                        ->orWhere('receiver_id', $loggedInUser->id);
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
                        'name' => $otherUserID == 0 ? 'Admin' : $otherUser->name ?? null,
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
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
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
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                })->whereIn('propery_type', [0, 1])->clone()->with('category.translations', 'advertisement', 'interested_users:id,property_id,customer_id', 'interested_users.customer:id,name,profile', 'translations')->latest()->limit(5)->get()->map($propertyMapper);
            } elseif ($type == 'project') {
                // Get Recently Added Projects
                $recentlyListedData = Projects::where(['added_by' => $loggedInUser->id, 'status' => 1, 'request_status' => 'approved', 'role_context' => 'agent'])->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
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

    public function getAgentProperties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug_id' => 'required_without_all:id,is_admin',
            'is_projects' => 'nullable|in:1',
            'is_admin' => 'nullable|in:1',
            'search' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        try {
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
                $adminPropertiesCount = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved', 'role_context' => 'agent'])->count();
                $adminProjectsCount = Projects::where(['is_admin_listing' => 1, 'status' => 1, 'role_context' => 'agent'])->count();
                $totalCount = $adminPropertiesCount + $adminProjectsCount;

                $adminData = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id', 'type')->first();
                $adminData['is_agent'] = $adminData->is_agent;
                $adminData['is_appointment_available'] = $adminData->is_appointment_available;
                if ($adminData) {
                    $customerData = [
                        'id' => $adminData->id,
                        'name' => 'Admin',
                        'slug_id' => $adminData->slug_id,
                        'email' => ! empty($adminEmail) ? $adminEmail : '',
                        'mobile' => ! empty($adminCompanyTel1) ? $adminCompanyTel1 : '',
                        'address' => ! empty($adminAddress) ? $adminAddress : '',
                        'property_count' => $adminPropertiesCount,
                        'projects_count' => $adminProjectsCount,
                        'total_count' => $totalCount,
                        // 'is_verify' => true,
                        // 'is_verified_user' => true,
                        'is_agent_verified' => true,
                        'profile' => ! empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg'),
                        'is_agent' => $adminData->is_agent,
                        'is_appointment_available' => $adminData->is_appointment_available,
                    ];
                }
            } else {
                // Customer Query
                $customerQuery = Customer::select('id', 'slug_id', 'name', 'profile', 'mobile', 'email', 'address', 'city', 'country', 'state', 'latitude', 'longitude', 'is_agent')
                    ->with('agent_profile')
                    ->where(function ($query) {
                        $query->where('isActive', 1);
                    })->withCount(['projects' => function ($query) {
                        $query->where('status', 1)->where('role_context', 'agent');
                    }, 'property' => function ($query) use ($response) {
                        if ($response['package_available'] == true && $response['feature_available'] == true) {
                            $query->onlyActive()->where('role_context', 'agent');
                        } else {
                            $query->where(['status' => 1, 'request_status' => 'approved', 'is_premium' => 0, 'role_context' => 'agent']);
                        }
                    }]);
                // Check if id exists or slug id on the basis of get agent id
                if ($request->has('id') && ! empty($request->id)) {
                    $addedBy = $request->id;
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('id', $request->id)->first();
                    $addedBy = ! empty($customerData) ? $customerData->id : '';
                } elseif ($request->has('slug_id')) {
                    // Get Customer Data
                    $customerData = $customerQuery->clone()->where('slug_id', $request->slug_id)->first();
                    $addedBy = ! empty($customerData) ? $customerData->id : '';
                }
                // Add Is User Verified Status in Customer Data
                // is_verified_user = true means the user has submitted a verification request and admin has approved it
                // !empty($customerData) ? $customerData->is_verify = $customerData->is_user_verified : "";
                // !empty($customerData) ? $customerData->is_verified_user = $customerData->is_user_verified : "";
                if (! empty($customerData)) {
                    $resolved = $customerData->resolved_agent_profile;
                    $customerData->name = $resolved['agent_name'];
                    $customerData->email = $resolved['agent_email'];
                    $customerData->profile = $resolved['agent_profile_photo'];
                    $customerData->mobile = $resolved['agent_mobile'];
                    $customerData->agent_profile = $resolved;
                    $customerData->is_agent = $customerData->is_agent;
                    $customerData->is_appointment_available = $customerData->is_appointment_available;
                    $customerData->is_agent_verified = $customerData->is_agent_verified;
                }
            }

            // if there is agent id then only get properties of it
            if (! empty($addedBy) || $addedBy === 0) {
                if (($request->has('is_projects') && ! empty($request->is_projects) && $request->is_projects == 1)) {
                    // Always fetch non-premium projects regardless of package
                    $projectQuery = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location', 'category_id', 'added_by', 'is_premium')->when($request->has('search') && ! empty($request->search), function ($query) use ($request) {
                        $query->where('title', 'LIKE', "%$request->search%");
                    });
                    if ($isAdminListing == true) {
                        $projectQuery = $projectQuery->clone()->where(['status' => 1, 'is_admin_listing' => 1]);
                    } else {
                        $projectQuery = $projectQuery->clone()->onlyActive()->where(['added_by' => $addedBy, 'role_context' => 'agent']);
                    }
                    // Only show non-premium projects if feature is not available (no valid package)
                    if ($response['feature_available'] == false) {
                        $projectQuery = $projectQuery->where('is_premium', 0);
                    }
                    $totalProjects = $projectQuery->clone()->count();
                    $totalData = $totalProjects;
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
                    $propertiesQuery = Property::onlyActive()->where(['added_by' => $addedBy, 'role_context' => 'agent'])
                        ->when($request->has('search') && ! empty($request->search), function ($query) use ($request) {
                            $query->where('title', 'LIKE', "%$request->search%");
                        });
                    // Count premium properties without the condition
                    $premiumPropertiesCount = $propertiesQuery->clone()->where('is_premium', 1)->count();

                    $propertiesQuery = $propertiesQuery->when(($response['feature_available'] == false), function ($query) {
                        $query->where('is_premium', 0);
                    });

                    // Count total properties
                    $totalProperties = $propertiesQuery->clone()->count();

                    // Get Propertis Data
                    $propertiesData = $propertiesQuery->clone()
                        ->with('category:id,slug_id,image,category', 'category.translations', 'translations')
                        ->select('id', 'slug_id', 'city', 'state', 'category_id', 'country', 'price', 'propery_type', 'title', 'title_image', 'is_premium', 'address', 'added_by', 'role_context')
                        ->orderBy('is_premium', 'DESC')->skip($offset)->take($limit)->get()->map(function ($property) {
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
                    $totalSoldProperties = $propertiesQuery->clone()->where('propery_type', 2)->count();
                    $totalRentedProperties = $propertiesQuery->clone()->where('propery_type', 3)->count();
                }
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
}
