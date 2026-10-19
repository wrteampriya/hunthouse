<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\ProjectDocuments;
use App\Models\ProjectPlans;
use App\Models\Projects;
use App\Models\Property;
use App\Models\User;
use App\Rules\VideoUrlRule;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectApiController extends Controller
{
    public function post_project(Request $request)
    {
        $videoRules = [
            'video_type' => 'nullable|in:0,1,2',
            'video_link' => ['nullable', 'required_if:video_type,1,2', new VideoUrlRule($request->video_type)],
            'custom_video' => 'nullable|file|mimes:mp4,webm,ogg|max:20480|required_if:video_type,0',
        ];

        if ($request->has('id')) {
            $validator = Validator::make($request->all(), array_merge([
                'title' => 'required',
            ], $videoRules), [], [
                'custom_video.max' => 'The custom video must not be greater than 20MB.',
            ]);
        } else {
            $validator = Validator::make($request->all(), array_merge([
                'title' => 'required',
                'description' => 'required',
                'image' => 'required|file|max:3000|mimes:jpeg,png,jpg,webp',
                'category_id' => 'required',
                'city' => 'required',
                'state' => 'required',
                'country' => 'required',
                'translations.*.title.translation_id' => 'nullable|exists:translations,id',
                'translations.*.title.language_id' => 'nullable|exists:languages,id',
                'translations.*.title.value' => 'nullable',
                'translations.*.description.translation_id' => 'nullable|exists:translations,id',
                'translations.*.description.language_id' => 'nullable|exists:languages,id',
                'translations.*.description.value' => 'nullable',
            ], $videoRules),[
                'custom_video.max' => 'The custom video must not be greater than 20MB.',
                'custom_video.*.max' => 'The custom video must not be greater than 20MB.',
            ]);
        }
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();
            $isPayAsYouGo = false;
            $isDraft = false;

            if (! $request->id) {
                // Check if limit is available without failing automatically
                $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), true, true, $request->user_active_role);
                if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
                    $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), false, true);
                    $isPayAsYouGo = ($limitResult === 'pay_as_you_go');
                } else {
                    // If no limit or package is available, implicitly save as draft!
                    $isDraft = true;
                    $isPayAsYouGo = false;
                }
            }
            $slugData = (isset($request->slug_id) && ! empty($request->slug_id)) ? $request->slug_id : $request->title;

            $currentUserId = Auth::user()->id;
            if (! (isset($request->id))) {
                $project = new Projects;

                if ($isDraft) {
                    $project->status = 0;
                    $project->request_status = 'draft';
                } else {
                    // Check the auto approve and verified user status and make project auto enable or disable
                    $autoApproveStatus = HelperService::getAutoApproveStatus($currentUserId, $request->user_active_role);
                    if ($autoApproveStatus) {
                        $project->status = 1;
                        $project->request_status = 'approved';
                        if ($isPayAsYouGo) {
                            $project->expiry_date = Carbon::now()->addDays(30);
                        } elseif (! $isDraft) {
                            $project->expiry_date = HelperService::calculateExpirationDate($currentUserId);
                        }
                    } else {
                        $project->status = 0;
                        $project->request_status = 'pending';
                    }
                }

                // if ($autoApproveStatus) {

                // }

                $project->is_premium = isset($request->is_premium) ? ($request->is_premium == 'true' ? 1 : 0) : 0;
            } else {
                $project = Projects::where('added_by', $currentUserId)->find($request->id);
                if (! $project) {
                    $response['error'] = false;
                    $response['message'] = trans('Project Not Found');

                    return response()->json($response);
                }
                $wasDraft = ($project->getRawOriginal('request_status') === 'draft');

                if ($wasDraft) {
                    $graduation = $this->graduateDraft($project, $request->user_active_role);
                    if ($graduation['success']) {
                        $isPayAsYouGo = $graduation['is_pay_as_you_go'];
                        $autoApproveStatus = $graduation['auto_approve'];
                    } else {
                        // Stay as draft
                    }
                } else {
                    $autoApproveStatus = HelperService::getAutoApproveStatus($currentUserId, $request->user_active_role);
                    if (! $autoApproveStatus) {
                        if (HelperService::getSettingData('auto_approve_edited_listings') == 0) {
                            $project->request_status = 'pending';
                        }
                    }
                }
            }

            // is_premium check
            $showPremiumToggle = system_setting('show_premium_toggle');
            if ($showPremiumToggle == 1) {
                $project->is_premium = isset($request->is_premium) ? ($request->is_premium) : 0;
            } else {
                $project->is_premium = 0;
            }

            if ($request->category_id) {
                $project->category_id = $request->category_id;
            }
            if ($request->description) {
                $project->description = $request->description;
            }
            if ($request->location) {
                $project->location = $request->location;
            }
            // Meta details
            $project->meta_title = isset($request->meta_title) && ! empty($request->meta_title) ? $request->meta_title : null;
            $project->meta_description = isset($request->meta_description) && ! empty($request->meta_description) ? $request->meta_description : null;
            $project->meta_keywords = isset($request->meta_keywords) && ! empty($request->meta_keywords) ? $request->meta_keywords : null;

            $project->added_by = $currentUserId;
            // role_context auto-set by HasRoleContext trait on creation
            if ($request->country) {
                $project->country = $request->country;
            }
            if ($request->state) {
                $project->state = $request->state;
            }
            if ($request->city) {
                $project->city = $request->city;
            }
            if ($request->latitude) {
                $project->latitude = $request->latitude;
            }
            if ($request->longitude) {
                $project->longitude = $request->longitude;
            }
            // Video Logic
            if ($request->has('remove_video') && $request->remove_video == 1) {
                if ($project->video_type == Projects::VIDEO_CUSTOM && ! empty($project->getRawOriginal('video_link'))) {
                    FileService::delete(config('global.PROJECT_VIDEO_PATH'), $project->getRawOriginal('video_link'));
                }
                $project->video_type = null;
                $project->video_link = null;
            } else {
                $videoType = $request->video_type;
                $directUploadEnabled = HelperService::getSettingData('show_direct_video_upload');

                if ($videoType !== null && (int) $videoType === Projects::VIDEO_CUSTOM && (int) $directUploadEnabled !== 1) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Direct video upload is currently disabled by admin.',
                    ]);
                }

                $project->video_type = $videoType;
                if ($videoType !== null && (int) $videoType === Projects::VIDEO_CUSTOM && $request->hasFile('custom_video')) {
                    $file = $request->file('custom_video');
                    if ($file instanceof UploadedFile) {
                        $path = config('global.PROJECT_VIDEO_PATH');
                        if (isset($request->id) && $project->getRawOriginal('video_link')) {
                            $project->video_link = FileService::compressAndReplace($file, $path, $project->getRawOriginal('video_link'));
                        } else {
                            $project->video_link = FileService::compressAndUpload($file, $path);
                        }
                    } else {
                        Log::error('custom_video is not an instance of UploadedFile', ['value' => $file]);
                    }
                } elseif ($videoType !== null && in_array($videoType, [1, 2])) {
                    $project->video_link = $request->video_link;
                }
            }
            if ($request->type) {
                $project->type = $request->type;
            }
            if ($request->id) {
                if ($project->title !== $request->title) {
                    $title = ! empty($request->title) ? $request->title : $project->title;
                    $project->title = $title;
                } else {
                    $title = $request->title;
                    $project->title = $title;
                }
                $project->slug_id = generateUniqueSlug($slugData, 4, null, $request->id);
                if ($request->hasFile('image')) {
                    $path = config('global.PROJECT_TITLE_IMG_PATH');
                    $rawImage = $project->getRawOriginal('image');
                    $project->image = FileService::compressAndReplace($request->file('image'), $path, $rawImage, true);
                }

                if ($request->has('remove_meta_image') && $request->remove_meta_image == 1) {
                    if (! empty($project->meta_image)) {
                        $file = $project->getRawOriginal('meta_image');
                        FileService::delete(config('global.PROJECT_SEO_IMG_PATH'), $file);
                    }
                    $project->meta_image = null;
                }

                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROJECT_SEO_IMG_PATH');
                    $rawImage = $project->getRawOriginal('meta_image');
                    $project->meta_image = FileService::compressAndReplace($request->file('meta_image'), $path, $rawImage);
                }
            } else {
                $project->title = $request->title;
                if ($request->hasFile('image')) {
                    $path = config('global.PROJECT_TITLE_IMG_PATH');
                    $project->image = FileService::compressAndUpload($request->file('image'), $path, true);
                }
                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROJECT_SEO_IMG_PATH');
                    $project->meta_image = FileService::compressAndUpload($request->file('meta_image'), $path);
                }
                $title = $request->title;
                $project->slug_id = generateUniqueSlug($slugData, 4);
            }

            $project->save();

            // Link payment transaction to project (pay-as-you-go only, new projects)
            if ($isPayAsYouGo && HelperService::$lastConsumedPaymentTransactionId) {
                PaymentTransaction::where('id', HelperService::$lastConsumedPaymentTransactionId)
                    ->update(['project_id' => $project->id]);
            }

            if ($request->remove_gallery_images) {
                $removeGalleryImagesIds = explode(',', $request->remove_gallery_images);
                $galleryImagesQuery = ProjectDocuments::whereIn('id', $removeGalleryImagesIds);
                $galleryImagesData = $galleryImagesQuery->get();
                if (collect($galleryImagesData)->isNotEmpty()) {
                    foreach ($galleryImagesData as $row) {
                        $file = $row->getRawOriginal('name');
                        $path = config('global.PROJECT_DOCUMENT_PATH');
                        FileService::delete($path, $file);
                    }
                    $galleryImagesQuery->delete();
                }
            }

            if ($request->remove_documents) {
                $removeDocumentsIds = explode(',', $request->remove_documents);
                $documentsQuery = ProjectDocuments::whereIn('id', $removeDocumentsIds);
                $documentsData = $documentsQuery->get();
                if (collect($documentsData)->isNotEmpty()) {
                    foreach ($documentsData as $row) {
                        $file = $row->getRawOriginal('name');
                        $path = config('global.PROJECT_DOCUMENT_PATH');
                        FileService::delete($path, $file);
                    }
                    $documentsQuery->delete();
                }
            }

            if ($request->hasfile('gallery_images')) {
                $galleryImagesData = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImagesData[] = [
                        'project_id' => $project->id,
                        'name' => FileService::compressAndUpload($file, config('global.PROJECT_DOCUMENT_PATH'), true),
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (collect($galleryImagesData)->isNotEmpty()) {
                    ProjectDocuments::insert($galleryImagesData);
                }
            }

            if ($request->hasfile('documents')) {
                $documentsData = [];
                foreach ($request->file('documents') as $file) {
                    $documentsData[] = [
                        'project_id' => $project->id,
                        'name' => FileService::compressAndUpload($file, config('global.PROJECT_DOCUMENT_PATH')),
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (collect($documentsData)->isNotEmpty()) {
                    ProjectDocuments::insert($documentsData);
                }
            }

            if (! empty($request->plans)) {

                $path = config('global.PROJECT_DOCUMENT_PATH');
                $planIds = collect($request->plans)->pluck('id')->filter()->all();

                // Preload existing plans in one query
                $existingPlans = ProjectPlans::whereIn('id', $planIds)->get()->keyBy('id');

                foreach ($request->plans as $planData) {

                    // Use existing plan or new instance
                    $projectPlan = $existingPlans->get($planData['id'] ?? null) ?? new ProjectPlans;

                    // Handle document upload if present
                    if (! empty($planData['document'])) {
                        $oldFile = $projectPlan->getRawOriginal('document');
                        $projectPlan->document = FileService::compressAndReplace($planData['document'], $path, $oldFile, true);
                    }

                    // Fill common fields
                    $projectPlan->fill([
                        'title' => $planData['title'] ?? '',
                        'project_id' => $project->id,
                    ]);

                    $projectPlan->save();
                }
            }

            if (! empty($request->remove_plans)) {
                $removePlanIds = array_filter(explode(',', $request->remove_plans));
                if (! empty($removePlanIds)) {
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    // Fetch all plans in one query
                    $plans = ProjectPlans::whereIn('id', $removePlanIds)->get();
                    // Delete associated files
                    foreach ($plans as $plan) {
                        $file = $plan->getRawOriginal('document');
                        if ($file) {
                            FileService::delete($path, $file);
                        }
                    }
                    // Delete all plans from DB in a single query
                    ProjectPlans::whereIn('id', $removePlanIds)->delete();
                }
            }

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = [
                            'id' => $value['translation_id'] ?? null,
                            'translatable_id' => $project->id,
                            'translatable_type' => 'App\Models\Projects',
                            'language_id' => $value['language_id'],
                            'key' => $key,
                            'value' => $value['value'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }
            $result = Projects::with('customer')->with('gallary_images')->with('documents')->with('plans')->with('category:id,category,image')->where('id', $project->id)->first();

            // $projectData = new CustomerResource($result, ['is_agent','is_user_verified', 'is_agent_verified', 'become_agent_status', 'agent_verification_status', 'user_verification_status']);

            DB::commit();
            $response['error'] = false;
            $response['message'] = isset($request->id) ? trans('Project Updated Successfully') : trans('Project Posted Successfully');
            // $response['data'] = $result;
            $customerMeta = HelperService::getCustomerMeta($result->added_by ?? null);

            if ($customerMeta) {
                $result->is_agent = $customerMeta['is_agent'] ?? false;
                $result->is_agent_verified = $customerMeta['is_agent_verified'] ?? false;
                $result->is_user_verified = $customerMeta['is_user_verified'] ?? false;
                $result->agent_verification_status = $customerMeta['agent_varification_status'] ?? 'not_applied';
                $result->become_agent_status = $customerMeta['become_agent_status'] ?? 'not_applied';
                $result->user_verification_status = $customerMeta['user_verification_status'] ?? 'not_applied';
            }

            $response['data'] = $result;

            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
                'details' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    public function getProjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|numeric',
                'limit' => 'nullable|numeric',
                'filters' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            // Decode filters from base64 (same as getPropertyList)
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

            $filterValidator = Validator::make(
                collect($filters)->toArray(),
                [
                    'category_id' => 'nullable|exists:categories,id',
                    'location.country' => 'nullable',
                    'location.state' => 'nullable',
                    'location.city' => 'nullable',
                    'location.latitude' => 'nullable',
                    'location.longitude' => 'nullable',
                    'location.range' => 'nullable',
                    'posted_since' => 'nullable|in:0,1,2,3,4',
                    'flags.promoted' => 'nullable',
                    'flags.most_views' => 'nullable',
                    'flags.most_liked' => 'nullable',
                    'flags.get_all_premium_properties' => 'nullable',
                    'project_type' => 'nullable|in:0,1',
                    'role_context' => 'nullable|in:user,agent',
                ]
            );
            if ($filterValidator->fails()) {
                ApiResponseService::validationError($filterValidator->errors()->first());
            }

            // Get Offset and Limit
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Get filter variables (same structure as getPropertyList)
            $categoryId = isset($filters['category_id']) ? $filters['category_id'] : null;
            $country = isset($filters['location']['country']) ? $filters['location']['country'] : null;
            $state = isset($filters['location']['state']) ? $filters['location']['state'] : null;
            $city = isset($filters['location']['city']) ? $filters['location']['city'] : null;
            $latitude = isset($filters['location']['latitude']) ? $filters['location']['latitude'] : null;
            $longitude = isset($filters['location']['longitude']) ? $filters['location']['longitude'] : null;
            $range = isset($filters['location']['range']) ? $filters['location']['range'] : null;
            $postedSince = isset($filters['posted_since']) ? $filters['posted_since'] : null;
            $promoted = isset($filters['flags']['promoted']) ? $filters['flags']['promoted'] : null;
            $getPremiumProjects = isset($filters['flags']['get_all_premium_properties']) ? $filters['flags']['get_all_premium_properties'] : null;
            $mostViewed = isset($filters['flags']['most_views']) ? $filters['flags']['most_views'] : null;
            $mostLiked = isset($filters['flags']['most_liked']) ? $filters['flags']['most_liked'] : null;
            $projectType = isset($filters['project_type']) ? $filters['project_type'] : null;
            $addedAs = isset($filters['role_context']) ? $filters['role_context'] : null;

            // Also support legacy top-level slug_id / id params
            $projectSlugId = $request->has('slug_id') ? $request->slug_id : null;
            $projectId = $request->has('id') ? $request->id : null;

            // Base query
            $projectsQuery = Projects::where(['request_status' => 'approved', 'status' => 1])
                ->where(function ($q) {
                    $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                })
                ->when($addedAs, function ($query) use ($addedAs) {
                    return $query->where('role_context', $addedAs);
                })
                ->with('category:id,slug_id,image,category', 'gallary_images', 'customer:id,name,profile,email,mobile,slug_id,is_agent,is_agent_verified', 'category.translations', 'translations')
                ->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'status', 'location', 'category_id', 'added_by', 'role_context', 'is_admin_listing', 'is_premium', 'request_status', 'meta_title', 'meta_description', 'meta_keywords', 'meta_image', 'total_click', 'expiry_date');

            // If Project Type is passed (0 = upcoming, 1 = under_construction)
            if (isset($projectType) && $projectType !== null) {
                $typeValue = $projectType == 0 ? 'upcoming' : 'under_construction';
                $projectsQuery = $projectsQuery->where('type', $typeValue);
            }

            // If Category ID is passed
            if (isset($categoryId) && ! empty($categoryId)) {
                $projectsQuery = $projectsQuery->where('category_id', $categoryId);
            }

            // If Country is passed
            if (isset($country) && ! empty($country)) {
                $projectsQuery = $projectsQuery->where('country', 'like', '%'.$country.'%');
            }

            // If State is passed
            if (isset($state) && ! empty($state)) {
                $projectsQuery = $projectsQuery->where('state', 'like', '%'.$state.'%');
            }

            // If City is passed
            if (isset($city) && ! empty($city)) {
                $projectsQuery = $projectsQuery->where('city', 'like', '%'.$city.'%');
            }

            // Latitude and Longitude
            if (isset($latitude) && ! empty($latitude) && isset($longitude) && ! empty($longitude) && $latitude != 'null' && $longitude != 'null') {
                if (isset($range) && ! empty($range) && $range != 'null') {
                    $projectsQuery = $projectsQuery->selectRaw("
                            (6371 * acos(cos(radians($latitude))
                            * cos(radians(latitude))
                            * cos(radians(longitude) - radians($longitude))
                            + sin(radians($latitude))
                            * sin(radians(latitude)))) AS distance")
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->having('distance', '<', $range);
                } else {
                    $projectsQuery = $projectsQuery->where('latitude', $latitude)->where('longitude', $longitude);
                }
            }

            // If Posted Since is passed
            if (isset($postedSince)) {
                if ($postedSince == 0) {
                    $projectsQuery = $projectsQuery->whereBetween(
                        'created_at',
                        [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                    );
                }
                if ($postedSince == 1) {
                    $projectsQuery = $projectsQuery->whereDate('created_at', Carbon::yesterday());
                }
                if ($postedSince == 2) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonth());
                }
                if ($postedSince == 3) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonths(3));
                }
                if ($postedSince == 4) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonths(6));
                }
            }

            // Legacy support for direct slug_id / id params
            if ($projectSlugId) {
                $projectsQuery = $projectsQuery->where('slug_id', $projectSlugId);
            }
            if ($projectId) {
                $projectsQuery = $projectsQuery->where('id', $projectId);
            }

            // Existing get_featured support
            if ($request->filled('get_featured') && $request->get_featured == 1) {
                $projectsQuery = $projectsQuery->whereHas('advertisement', function ($query) {
                    $query->where('for', 'project')->where('status', 0)->where('is_enable', 1);
                });
            }

            // If promoted is passed then show only projects that have active advertisements
            if (isset($promoted) && ! empty($promoted) && $promoted == 1) {
                $projectsQuery = $projectsQuery->whereHas('advertisement', function ($query) {
                    $query->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // If get_all_premium_properties is passed then show only premium projects
            if (isset($getPremiumProjects) && ! empty($getPremiumProjects) && $getPremiumProjects == 1) {
                $projectsQuery = $projectsQuery->where('is_premium', 1);
            }

            // Add promoted_count for ordering
            $projectsQuery = $projectsQuery->withCount([
                'advertisement as promoted_count' => function ($query) {
                    $query->where('status', 0)
                        ->where('is_enable', 1)
                        ->where('for', 'project')
                        ->groupBy('project_id');
                },
            ]);

            // Always group promoted projects first
            $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END');

            // Randomize promoted, order non-promoted by chosen sort key
            if (isset($mostViewed) && ! empty($mostViewed) && $mostViewed == 1) {
                // For most viewed: order non-promoted by total_click DESC
                $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - total_click) END');
            } elseif (isset($mostLiked) && ! empty($mostLiked) && $mostLiked == 1) {
                // For most liked: order non-promoted by id DESC (projects have no favourites table)
                $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - id) END');
            } else {
                // Default: order non-promoted by id DESC
                $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - id) END');
            }

            // Get Total
            $total = $projectsQuery->clone()->count();

            // Get Admin Company Details
            $adminCompanyTel1 = system_setting('company_tel1');
            $adminEmail = system_setting('company_email');
            $adminUser = User::where('id', 1)->select('id', 'slug_id')->first();

            // Get Data
            $data = $projectsQuery->clone()
                ->take($limit)
                ->skip($offset)
                ->get()
                ->map(function ($project) use ($adminCompanyTel1, $adminEmail, $adminUser) {
                    // Check if listing is by admin then add admin details in customer
                    if ($project->is_admin_listing == true) {
                        unset($project->customer);
                        $project->customer = [
                            'name' => 'Admin',
                            'email' => $adminEmail,
                            'mobile' => $adminCompanyTel1,
                            'slug_id' => $adminUser->slug_id,
                            'is_agent' => false,
                            'is_agent_verified' => false,
                            'is_admin' => true,
                        ];
                    }
                    if ($project->category) {
                        $project->category->translated_name = $project->category->translated_name;
                    }
                    $project->translated_title = $project->translated_title;
                    $project->promoted = $project->is_promoted;
                    $project->is_premium = $project->is_premium == 1 ? true : false;
                    // $project->is_agent =
                    // dd($project->customer);
                    // return new CustomerResource($project, ['is_agent', 'is_user_verified', 'is_agent_verified', 'become_agent_status', 'agent_verification_status', 'user_verification_status']);
                    $customerId = $project->added_by ?? null;

                    $customerMeta = HelperService::getCustomerMeta($customerId);

                    if ($customerMeta) {
                        $project->is_agent = $customerMeta['is_agent'] ?? false;
                        $project->is_agent_verified = $customerMeta['is_agent_verified'] ?? false;
                        $project->is_user_verified = $customerMeta['is_user_verified'] ?? false;
                        $project->agent_verification_status = $customerMeta['agent_varification_status'] ?? 'not_applied';
                        $project->become_agent_status = $customerMeta['become_agent_status'] ?? 'not_applied';
                        $project->user_verification_status = $customerMeta['user_verification_status'] ?? 'not_applied';
                    }

                    return $project;
                });

            ApiResponseService::successResponse('Data Fetched Successfully', $data, ['total' => $total]);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getProjectDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required_without:slug_id|exists:projects,id',
            'slug_id' => 'required_without:id|exists:projects,slug_id',
            'get_similar' => 'nullable|in:1',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $isPremium = 0;
            if ($request->id) {
                $isPremium = Projects::where('id', $request->id)->value('is_premium');
            } elseif ($request->slug_id) {
                $isPremium = Projects::where('slug_id', $request->slug_id)->value('is_premium');
            }

            if ($isPremium) {
                if (Auth::guard('sanctum')->check()) {
                    HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'), true, true, $request->user_active_role);
                } else {
                    ApiResponseService::errorResponse('Please Login to see premium project details', null, config('constants.RESPONSE_CODE.UNAUTHORIZED'));
                }
            }

            $getSimilarProjects = [];
            $project = Projects::with(['customer' => function ($query) {
                $query->select('id', 'name', 'profile', 'email', 'mobile', 'address', 'slug_id');
            }])
                ->with('gallary_images')
                ->with('documents')
                ->with('plans')
                ->with('category:id,category,image')
                ->with('category.translations', 'translations')
                ->where(function ($query) {
                    $query->where(['request_status' => 'approved', 'status' => 1]);
                });

            if ($request->get_similar == 1) {
                $similarProjectMapper = function ($item) {
                    if ($item->category) {
                        $item->category->translated_name = $item->category->translated_name;
                    }
                    $item->translated_title = $item->translated_title;
                    $item->translated_description = $item->translated_description;

                    return $item;
                };
                $similarProjectQuery = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location', 'category_id', 'added_by', 'request_status')->where(function ($query) {
                    $query->where(['request_status' => 'approved', 'status' => 1]);
                })->with('category:id,category,image')->with('category.translations', 'translations');
                if ($request->has('id') && ! empty($request->id)) {
                    $getSimilarProjects = $similarProjectQuery->clone()->where('id', '!=', $request->id)->get()->map($similarProjectMapper);
                } elseif ($request->has('slug_id') && ! empty($request->slug_id)) {
                    $getSimilarProjects = $similarProjectQuery->clone()->where('slug_id', '!=', $request->slug_id)->get()->map($similarProjectMapper);
                }
            }

            if ($request->id) {
                $project = $project->where('id', $request->id);
                HelperService::incrementTotalClick('project', $request->id);
            }

            if ($request->slug_id) {
                $project = $project->where('slug_id', $request->slug_id);
                HelperService::incrementTotalClick('project', null, $request->slug_id);
            }

            $total = $project->clone()->count();
            $data = $project->first();

            if (! empty($data)) {
                if ($data->is_admin_listing != 1 && $data->customer) {
                    $roleContext = $data->role_context ?? 'user';
                    $data->customer->loadCount([
                        'projects' => function ($subQuery) use ($roleContext) {
                            $subQuery->onlyActive()->where('role_context', $roleContext);
                        },
                        'property' => function ($subQuery) use ($roleContext) {
                            $subQuery->onlyActive()->where('role_context', $roleContext);
                        },
                    ]);
                }

                if ($data->category) {
                    $data->category->translated_name = $data->category->translated_name;
                }
                $data->translated_title = $data->translated_title;
                $data->translated_description = $data->translated_description;

                if ($data->is_admin_listing == 1) {
                    $adminCompanyTel1 = system_setting('company_tel1');
                    $adminEmail = system_setting('company_email');
                    $adminAddress = system_setting('company_address');
                    $adminData = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id')->first();
                    $totalPropertiesOfAdmin = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                        $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                    })->count();
                    $totalProjectsOfAdmin = Projects::where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                        $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                    })->count();

                    // Create modified customer data
                    $customCustomer = [
                        'id' => $adminData->id,
                        'name' => $adminData->name,
                        'slug_id' => $adminData->slug_id,
                        'profile' => ! empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg'),
                        'mobile' => ! empty($adminCompanyTel1) ? $adminCompanyTel1 : '',
                        'email' => ! empty($adminEmail) ? $adminEmail : '',
                        'address' => ! empty($adminAddress) ? $adminAddress : '',
                        'total_properties' => $totalPropertiesOfAdmin,
                        'total_projects' => $totalProjectsOfAdmin,
                        'is_admin' => true,
                    ];

                    // Force Laravel to include the modified customer data
                    $data->setRelation('customer', (object) $customCustomer);
                    $data->customer = (object) $customCustomer;
                } else {
                    // dd($data->customer);
                    $data->total_properties = $data->customer->property_count;
                    $data->total_projects = $data->customer->projects_count;

                    $data->customer->agent_profile = $data->customer?->resolved_agent_profile;
                }
                $data->role_context = $data->role_context ?? 'user';
            }

            // $projectData = new CustomerResource($data, ['is_agent','is_user_verified', 'is_agent_verified']);

            $customerId = $data?->added_by ?? null;
            if ($customerId) {
                $meta = HelperService::getCustomerMeta($customerId);

                if ($meta) {
                    $data->is_agent = $meta['is_agent'] ?? false;
                    $data->is_agent_verified = $meta['is_agent_verified'] ?? false;
                    $data->is_user_verified = $meta['is_user_verified'] ?? false;
                    $data->agent_verification_status = $meta['agent_varification_status'] ?? 'not_applied';
                    $data->become_agent_status = $meta['become_agent_status'] ?? 'not_applied';
                    $data->user_verification_status = $meta['user_verification_status'] ?? 'not_applied';
                }
            }

            ApiResponseService::successResponse(
                'Data Fetched Successfully',
                $data,
                [
                    'total' => $total,
                    'similar_projects' => $getSimilarProjects,
                ]
            );
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function delete_project(Request $request)
    {
        $current_user = Auth::user()->id;

        $validator = Validator::make($request->all(), [

            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        $project = Projects::where('added_by', $current_user)->with('gallary_images')->with('documents')->with('plans')->find($request->id);

        if ($project) {
            // Validate active role matches project's role context
            if ($request->user_active_role !== ($project->role_context ?? 'user')) {
                return response()->json([
                    'error' => true,
                    'message' => trans('This project was created in :role mode. Please switch to :role mode to delete it.', ['role' => $project->role_context ?? 'user']),
                ]);
            }
            if ($project->title_image != '') {
                $path = config('global.PROJECT_TITLE_IMG_PATH');
                FileService::clearCachedBlurImageUrl('blur_project_title_image_'.$project->id);
                FileService::delete($path, $project->getRawOriginal('image'));
            }
            if (collect($project->gallary_images)->isNotEmpty()) {
                foreach ($project->gallary_images as $row) {
                    $file = $row->getRawOriginal('name');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->gallary_images()->delete();
            }

            if (collect($project->documents)->isNotEmpty()) {
                foreach ($project->documents as $row) {
                    $file = $row->getRawOriginal('name');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->documents()->delete();
            }
            if (collect($project->plans)->isNotEmpty()) {
                foreach ($project->plans as $row) {
                    $file = $row->getRawOriginal('document');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->plans()->delete();
            }
            $project->delete();
            $response['error'] = false;
            $response['message'] = trans('Project Deleted Successfully');
        } else {
            $response['error'] = true;
            $response['message'] = trans('No Data Found');
        }

        return response()->json($response);
    }

    public function changeProjectStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $loggedInUserID = Auth::user()->id;
            // Get Query Data of project based on project id
            $projectQuery = Projects::where('id', $request->project_id);
            $projectQueryData = $projectQuery->firstOrFail();
            if ($projectQueryData->added_by != $loggedInUserID) {
                ApiResponseService::validationError('Cannot change the status of project owned by others');
            }
            // Validate active role matches project's role context
            if ($request->user_active_role !== ($projectQueryData->role_context ?? 'user')) {
                ApiResponseService::validationError(trans('This project was created in :role mode. Please switch to :role mode.', ['role' => $projectQueryData->role_context ?? 'user']));
            }
            if ($projectQueryData->request_status != 'approved') {
                ApiResponseService::validationError('Project is not approved');
            }
            // update user status
            $projectQuery->update(['status' => $request->status == 1 ? 1 : 0]);
            ApiResponseService::successResponse('Data Updated Successfully');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAddedProjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'nullable|in:under_construction,upcoming',
                'request_status' => 'nullable|in:pending,approved,rejected,expired',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            $user = Auth::user();

            // 🔐 Role validation
            if ($request->has('id') || $request->has('slug_id')) {
                $isAgentProject = Projects::where('added_by', $user->id)
                    ->when($request->filled('id'), fn ($q) => $q->where('id', $request->id))
                    ->when($request->filled('slug_id'), fn ($q) => $q->where('slug_id', $request->slug_id))
                    ->where('role_context', 'agent')
                    ->exists();

                if ($isAgentProject && $request->user_active_role != 'agent') {
                    return ApiResponseService::errorResponse(
                        'Unauthorized. Active role must be agent.',
                        null,
                        null,
                        403,
                        null,
                        [],
                        config('constants.API_RESPONSE_KEY.REQUIRED_AGENT_ROLE')
                    );
                }
            }

            // 📦 Base query
            $projectsQuery = Projects::where([
                'added_by' => $user->id,
                'role_context' => $request->user_active_role,
            ])->with([
                'category:id,slug_id,image,category',
                'gallary_images',
                'customer:id,name,profile,email,mobile,is_agent,is_agent_verified',
                'category.translations',
                'translations',
                'plans',
                'documents',
            ]);

            // =========================================================
            // 📌 SINGLE PROJECT (id / slug_id)
            // =========================================================
            if ($request->filled('id') || $request->filled('slug_id')) {

                $data = $projectsQuery->clone()
                    ->where(function ($query) use ($request) {
                        $query->when($request->filled('id'), fn ($q) => $q->where('id', $request->id))
                            ->when($request->filled('slug_id'), fn ($q) => $q->orWhere('slug_id', $request->slug_id));
                    })
                    ->first();

                if (! empty($data)) {
                    $data->posted_since = $data->created_at->diffForHumans();

                    if ($data->category) {
                        $data->category->translated_name = $data->category->translated_name;
                    }

                    $data->translated_title = $data->translated_title;
                    $data->translated_description = $data->translated_description;

                    $data = $this->attachCustomerMeta($data);
                }

                $projectData = $data;

                // 🔁 Similar Projects
                $getSimilarProjects = collect();

                if ($request->filled('id')) {
                    $getSimilarProjects = $projectsQuery->clone()
                        ->where('id', '!=', $request->id)
                        ->get()
                        ->map(function ($project) {
                            return $this->formatProject($project);
                        });
                } elseif ($request->filled('slug_id')) {
                    $getSimilarProjects = $projectsQuery->clone()
                        ->where('slug_id', '!=', $request->slug_id)
                        ->get()
                        ->map(function ($project) {
                            return $this->formatProject($project);
                        });
                }

                $responseExtra = [];

                if ($request->user_active_role != 'agent') {
                    $responseExtra['similar_projects'] = $getSimilarProjects;
                }

                return ApiResponseService::successResponse(
                    'Data Fetched Successfully',
                    $projectData,
                    $responseExtra
                );
            }

            // =========================================================
            // 📌 LISTING PROJECTS
            // =========================================================
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;

            $projectsQuery = $projectsQuery->clone()
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
                ->when($request->filled('request_status'), function ($q) use ($request) {
                    if ($request->request_status == 'expired') {
                        return $q->whereNotNull('expiry_date')->where('expiry_date', '<', now());
                    }

                    return $q->where('request_status', $request->request_status);
                })
                ->select(
                    'id', 'slug_id', 'city', 'state', 'country',
                    'title', 'type', 'image', 'location',
                    'status', 'category_id', 'added_by',
                    'created_at', 'request_status',
                    'edit_reason', 'is_premium', 'expiry_date'
                );

            $total = $projectsQuery->clone()->count();

            $data = $projectsQuery->clone()
                ->latest()
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($project) {

                    if ($project->request_status == 'rejected') {
                        $project->reject_reason = $project->reject_reason()
                            ->select('id', 'project_id', 'reason', 'created_at')
                            ->latest()
                            ->first();
                    } else {
                        $project->reject_reason = (object) [];
                    }

                    return $this->formatProject($project);
                });

            return ApiResponseService::successResponse(
                'Data Fetched Successfully',
                $data,
                ['total' => $total]
            );

        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function update_project_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $currentUserId = Auth::user()->id;
            $project = Projects::where('added_by', $currentUserId)->findOrFail($request->project_id);

            // Handle Draft Graduation
            if ($project->getRawOriginal('request_status') === 'draft') {
                $graduation = $this->graduateDraft($project, $request->user_active_role);
                if ($graduation['success']) {
                    return response()->json([
                        'error' => false,
                        'message' => trans('Project Published Successfully'),
                        'data' => $project,
                    ]);
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => $graduation['message'],
                    ]);
                }
            }

            return response()->json(['error' => true, 'message' => 'Invalid status update requested.']);

        } catch (Exception $e) {
            return ResponseService::errorResponse($e);
        }
    }

    private function graduateDraft($project, $userActiveRole)
    {
        $currentUserId = Auth::user()->id;
        $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), true, true, $userActiveRole);
        if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
            $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), false, true);
            $isPayAsYouGo = ($limitResult === 'pay_as_you_go');

            $autoApproveStatus = HelperService::getAutoApproveStatus($currentUserId, $userActiveRole);
            if ($autoApproveStatus) {
                $project->request_status = 'approved';
            } else {
                $project->request_status = 'pending';
            }
            $project->status = 1;

            if ($autoApproveStatus) {
                if ($isPayAsYouGo) {
                    $project->expiry_date = Carbon::now()->addDays(30);
                } elseif ($autoApproveStatus) {
                    $project->expiry_date = HelperService::calculateExpirationDate($currentUserId);
                }
            }
            $project->save();

            return ['success' => true, 'is_pay_as_you_go' => $isPayAsYouGo, 'auto_approve' => $autoApproveStatus];
        } else {
            return ['success' => false, 'message' => trans('Please purchase a package to publish this project.')];
        }
    }

    private function formatProject($project)
    {
        $project->posted_since = $project->created_at->diffForHumans();

        if ($project->category) {
            $project->category->translated_name = $project->category->translated_name;
        }

        $project->translated_title = $project->translated_title;
        $project->translated_description = $project->translated_description;

        return $this->attachCustomerMeta($project);
    }

    private function attachCustomerMeta($project)
    {
        if (! empty($project->customer)) {
            $meta = HelperService::getCustomerMeta($project->customer->id);

            $project->is_agent = $meta['is_agent'] ?? false;
            $project->is_agent_verified = $meta['is_agent_verified'] ?? false;
            $project->is_user_verified = $meta['is_user_verified'] ?? false;
            $project->agent_verification_status = $meta['agent_verification_status'] ?? 'not_applied';
            $project->become_agent_status = $meta['become_agent_status'] ?? 'not_applied';
            $project->user_verification_status = $meta['user_verification_status'] ?? 'not_applied';
        }

        return $project;
    }
}
