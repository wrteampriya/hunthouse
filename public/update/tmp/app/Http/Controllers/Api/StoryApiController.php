<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentFollow;
use App\Models\Customer;
use App\Models\Projects;
use App\Models\Property;
use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Dedoc\Scramble\Attributes\HeaderParameter;

class StoryApiController extends Controller
{
    /**
     * GET /api/get-stories
     * Public with optional auth (checkAuth middleware).
     *
     * Optional filters (all combinable):
     *   category_id  — categories screen
     *   agent_id     — agent profile screen
     *   property_id  — property detail screen
     *   project_id   — project detail screen
     *
     * Response is always the same shape regardless of which filter is used.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|integer',
            'agent_id' => 'nullable|integer',
            'property_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:50',
            'offset' => 'nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $limit = (int) ($request->limit ?? 10);
            $offset = (int) ($request->offset ?? 0);
            $categoryId = $request->category_id;
            $agentId = $request->has('agent_id') ? (int) $request->agent_id : null;
            $propertyId = $request->property_id;
            $projectId = $request->project_id;

            $currentUser = Auth::user();
            $isGuest = $currentUser === null;

            $userHasPremiumPropertiesAccess = ! $isGuest && HelperService::userHasFeatureAccess(
                $currentUser->id,
                config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE')
            );
            $userHasPremiumProjectsAccess = ! $isGuest && HelperService::userHasFeatureAccess(
                $currentUser->id,
                config('constants.FEATURES.PREMIUM_PROJECTS.TYPE')
            );

            $baseQuery = Story::active()
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('linked_entity_type', 'property')
                            ->whereIn('linked_entity_id', Property::onlyActive()->pluck('id'));
                    })->orWhere(function ($q) {
                        $q->where('linked_entity_type', 'project')
                            ->whereIn('linked_entity_id', Projects::onlyActive()->pluck('id'));
                    });
                })
                ->when($agentId !== null, fn ($q) => $q->where('agent_id', $agentId))
                ->when($propertyId, fn ($q) => $q->where('linked_entity_type', 'property')->where('linked_entity_id', $propertyId))
                ->when($projectId, fn ($q) => $q->where('linked_entity_type', 'project')->where('linked_entity_id', $projectId))
                ->when($categoryId, function ($q) use ($categoryId) {
                    $propertyIds = Property::where('category_id', $categoryId)->pluck('id');
                    $projectIds = Projects::where('category_id', $categoryId)->pluck('id');
                    $q->where(function ($q) use ($propertyIds, $projectIds) {
                        $q->where(function ($q) use ($propertyIds) {
                            $q->where('linked_entity_type', 'property')->whereIn('linked_entity_id', $propertyIds);
                        })->orWhere(function ($q) use ($projectIds) {
                            $q->where('linked_entity_type', 'project')->whereIn('linked_entity_id', $projectIds);
                        });
                    });
                })
                ->when($request->filled('country_id'), function ($q) use ($request) {
                    $propertyIds = Property::where('country_id', $request->country_id)->pluck('id');
                    $projectIds = Projects::where('country_id', $request->country_id)->pluck('id');
                    $q->where(function ($q) use ($propertyIds, $projectIds) {
                        $q->where(function ($q) use ($propertyIds) {
                            $q->where('linked_entity_type', 'property')->whereIn('linked_entity_id', $propertyIds);
                        })->orWhere(function ($q) use ($projectIds) {
                            $q->where('linked_entity_type', 'project')->whereIn('linked_entity_id', $projectIds);
                        });
                    });
                })
                ->orderBy('created_at', 'desc');

            // Paginate by uploader — agent_id=0 is admin, others are agents
            $allUploaderIds = $baseQuery->clone()->distinct()->pluck('agent_id');

            // Followed agents' stories surface first, preserving the existing
            // recency order within each partition (stable sort).
            if (! $isGuest) {
                $followedAgentIds = AgentFollow::where('user_id', $currentUser->id)->pluck('agent_id')->flip();
                if ($followedAgentIds->isNotEmpty()) {
                    $allUploaderIds = $allUploaderIds
                        ->sortBy(fn ($id) => $followedAgentIds->has($id) ? 0 : 1)
                        ->values();
                }
            }

            $totalUploaders = $allUploaderIds->count();
            $pagedUploaderIds = $allUploaderIds->slice($offset, $limit);

            $stories = $baseQuery->clone()->whereIn('agent_id', $pagedUploaderIds)->get();

            // Pre-load seen story IDs for logged-in users in one query
            $seenStoryIds = collect();
            if (! $isGuest) {
                $seenStoryIds = StoryView::where('user_id', $currentUser->id)
                    ->whereIn('story_id', $stories->pluck('id'))
                    ->pluck('story_id')
                    ->flip(); // use as a set for O(1) lookup
            }

            [$agents, $properties, $projects] = $this->resolveLinkedEntities($stories);

            $userHasPremiumAccess = $userHasPremiumPropertiesAccess || $userHasPremiumProjectsAccess;

            // group by agent_id; admin stories land under key '0'
            $grouped = $stories->groupBy('agent_id');
            $agentsData = $pagedUploaderIds->map(function ($id) use ($grouped, $agents, $properties, $projects, $isGuest, $seenStoryIds, $userHasPremiumAccess) {
                $formatted = ($grouped[$id] ?? collect())
                    ->map(fn ($s) => $this->formatStory($s, $properties, $projects, $isGuest, $seenStoryIds));

                $allPremium      = $formatted->filter(fn ($s) => $s['is_premium']);
                $premiumStories  = $userHasPremiumAccess ? $allPremium->values() : collect();
                $nonPremiumStories = $formatted->filter(fn ($s) => ! $s['is_premium'])->values();

                $hasUnseenStory = ! $isGuest && $formatted->contains(fn ($s) => ! $s['is_seen']);

                if ($id == 0) {
                    $getprofile = User::where('id', 1)->first();
                    return [
                        'agent_id' => 0,
                        'is_admin' => true,
                        'admin_slug_id' => $getprofile?->slug_id,
                        'agent_name' => 'Admin',
                        'agent_profile_image' => $getprofile?->profile,
                        'has_unseen_story' => $hasUnseenStory,
                        'premium_story_count' => $allPremium->count(),
                        'premium_stories' => $premiumStories,
                        'non_premium_stories' => $nonPremiumStories,
                    ];
                }

                $agent = $agents[$id] ?? null;
                if (! $agent) {
                    return null;
                }

                // Use the agent's branded name/photo (AgentProfile), falling back to
                // their raw account name/photo — same resolution used for chats and
                // appointments. Also fixes broken image links: the model accessors
                // already confirm the file exists on disk before building a URL,
                // which the previous manual Storage::url() reconstruction did not.
                $resolvedAgentProfile = $agent->resolved_agent_profile;

                return [
                    'agent_id' => $agent->id,
                    'agent_name' => $resolvedAgentProfile['agent_name'],
                    'agent_slug_id' => $agent->slug_id,
                    'is_agent_verified' => (bool) $agent->is_agent_verified,
                    'agent_profile_image' => $resolvedAgentProfile['agent_profile_photo'],
                    'agent_mobile' => $agent->getRawOriginal('mobile'),
                    'agent_country_code' => $agent->country_code,
                    'has_unseen_story' => $hasUnseenStory,
                    'premium_story_count' => $allPremium->count(),
                    'premium_stories' => $premiumStories,
                    'non_premium_stories' => $nonPremiumStories,
                ];
            })->filter()->values();

            return ApiResponseService::successResponse('Stories fetched successfully', [
                'section_title' => 'Featured Stories',
                'is_guest' => $isGuest,
                'user_has_premium_properties_access' => $userHasPremiumPropertiesAccess,
                'user_has_premium_projects_access' => $userHasPremiumProjectsAccess,
                'agents' => $agentsData,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $totalUploaders,
                    'has_more' => ($offset + $limit) < $totalUploaders,
                ],
            ]);
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Error fetching stories');

            return ApiResponseService::errorResponse('Something went wrong');
        }
    }

    /**
     * POST /api/story-view  [auth required — logged-in users only]
     * Records the story as seen and backfills earlier stories by the same agent.
     */
    public function recordView(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'story_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $story = Story::where('story_id', $request->story_id)->active()->first();
            if (! $story) {
                return ApiResponseService::errorResponse('Story not found or expired');
            }

            $userId = Auth::id();
            $now = now();

            // Find all earlier (or same) stories by the same agent to backfill
            $storiesToMark = Story::where('agent_id', $story->agent_id)
                ->where('created_at', '<=', $story->created_at)
                ->pluck('id');

            // Upsert story_views rows — ignores duplicates via unique constraint
            $rows = $storiesToMark->map(fn ($id) => [
                'story_id' => $id,
                'user_id' => $userId,
                'viewed_at' => $now,
                'created_at' => $now,
            ])->values()->all();

            DB::table('story_views')->upsert(
                $rows,
                ['story_id', 'user_id'],
                ['viewed_at']
            );

            // Increment view_count on the specific story the user was on
            $story->increment('view_count');

            return ApiResponseService::successResponse('View recorded');
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Error recording view');

            return ApiResponseService::errorResponse('Something went wrong');
        }
    }

    /**
     * POST /api/upload-story  [auth:sanctum, agent]
     * Upload a new story (free for agents — no package check).
     */
    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function upload(Request $request)
    {
        $isVideo = $request->input('media_type') === 'video';

        $videoMaxMb = (int) (HelperService::getSettingData('story_video_max_size') ?: 50);
        $videoMaxKb = $videoMaxMb * 1024;

        $validator = Validator::make($request->all(), [
            'media'            => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov|max:' . ($isVideo ? $videoMaxKb : '3072'),
            'media_type'       => 'required|in:image,video',
            'entity_type'      => 'required|in:property,project',
            'entity_id'        => 'required|integer',
            'duration_seconds' => 'nullable|integer|min:1|max:180',
            'thumbnail'        => 'nullable|file|mimes:jpg,jpeg,png,webp|max:3072',
        ], [
            'media.max'       => $isVideo
                ? trans('File size exceeds the limit. Please upload a video smaller than :mb MB.', ['mb' => $videoMaxMb])
                : trans('File size exceeds the limit. Please upload an image smaller than 3MB.'),
            'media.mimes'     => $isVideo
                ? trans('Invalid video format. Allowed formats: mp4, mov.')
                : trans('Invalid image format. Allowed formats: jpg, jpeg, png, webp.'),
            'thumbnail.max'   => trans('Thumbnail size exceeds the limit. Please upload an image smaller than 3MB.'),
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $agent = Auth::user();
            $entityId = (int) $request->entity_id;

            // Verify the agent owns the linked entity
            if ($request->entity_type === 'property') {
                $entity = Property::where('id', $entityId)->where('added_by', $agent->id)->first();
            } else {
                $entity = Projects::where('id', $entityId)->where('added_by', $agent->id)->first();
            }

            if (! $entity) {
                return ApiResponseService::errorResponse('You do not own this listing or it does not exist');
            }

            // Generate unique story_id
            do {
                $storyId = 'str_'.Str::lower(Str::random(6));
            } while (Story::where('story_id', $storyId)->exists());

            // Upload media
            if ($request->media_type === 'image') {
                $fileName = FileService::compressAndUpload($request->file('media'), 'stories/images/', false);
                if (! $fileName) {
                    return ApiResponseService::errorResponse('Failed to upload image');
                }
                $mediaUrl = Storage::disk('public')->url('stories/images/'.$fileName);
                $thumbnailUrl = $mediaUrl;
            } else {
                $file = $request->file('media');
                $ext = strtolower($file->getClientOriginalExtension());
                $fileName = $storyId.'.'.$ext;
                $file->storeAs('stories/videos', $fileName, 'public');
                $mediaUrl = Storage::disk('public')->url('stories/videos/'.$fileName);

                // Optional thumbnail upload for videos
                if ($request->hasFile('thumbnail')) {
                    $thumbName = FileService::compressAndUpload($request->file('thumbnail'), 'stories/thumbnails/', false);
                    $thumbnailUrl = $thumbName
                        ? Storage::disk('public')->url('stories/thumbnails/'.$thumbName)
                        : null;
                } else {
                    $thumbnailUrl = null;
                }
            }

            $story = Story::create([
                'story_id' => $storyId,
                'agent_id' => $agent->id,
                'media_type' => $request->media_type,
                'media_url' => $mediaUrl,
                'thumbnail_url' => $thumbnailUrl,
                'duration_seconds' => (int) ($request->duration_seconds ?? 6),
                'linked_entity_type' => $request->entity_type,
                'linked_entity_id' => $entityId,
                'is_active' => true,
                'expires_at' => now()->addHours(24),
            ]);

            $isPremium = (bool) $entity->is_premium;

            return ApiResponseService::successResponse('Story uploaded successfully', [
                'story_id' => $story->story_id,
                'media_type' => $story->media_type,
                'media_url' => $story->media_url,
                'thumbnail_url' => $story->thumbnail_url,
                'duration_seconds' => $story->duration_seconds,
                'created_at' => $story->created_at->toISOString(),
                'expires_at' => $story->expires_at->toISOString(),
                'view_count' => 0,
                'is_premium' => $isPremium,
                'linked_entity' => [
                    'type' => $request->entity_type,
                    'id' => $entity->id,
                    'slug' => $entity->slug_id,
                    'title' => $entity->title,
                    'is_premium' => $isPremium,
                ],
            ]);
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Error uploading story');

            return ApiResponseService::errorResponse('Something went wrong');
        }
    }

    /**
     * DELETE /api/delete-story  [auth:sanctum, agent]
     */
    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'story_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $story = Story::where('story_id', $request->query('story_id'))
                ->where('agent_id', Auth::id())
                ->first();

            if (! $story) {
                return ApiResponseService::errorResponse('Story not found or you do not own it');
            }

            $this->deleteStoryFiles($story);
            $story->delete();

            return ApiResponseService::successResponse('Story deleted successfully');
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Error deleting story');

            return ApiResponseService::errorResponse('Something went wrong');
        }
    }

    /**
     * GET /api/my-stories  [auth:sanctum, agent]
     * Returns the agent's own stories (active and expired).
     */
    #[HeaderParameter('X-Active-Role', description: 'Active role context: user or agent', type: 'string', example: 'user')]
    public function myStories(Request $request)
    {
        try {
            $stories = Story::where('agent_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            $propertyIds = $stories->where('linked_entity_type', 'property')->pluck('linked_entity_id')->unique();
            $projectIds = $stories->where('linked_entity_type', 'project')->pluck('linked_entity_id')->unique();

            $properties = Property::whereIn('id', $propertyIds)
                ->select('id', 'slug_id', 'title', 'is_premium', 'address', 'city', 'state', 'country', 'latitude', 'longitude')
                ->get()->keyBy('id');

            $projects = Projects::whereIn('id', $projectIds)
                ->select('id', 'slug_id', 'title', 'is_premium', 'city', 'state', 'country', 'latitude', 'longitude')
                ->get()->keyBy('id');

            $data = $stories->map(function ($story) use ($properties, $projects) {
                $entity = $story->linked_entity_type === 'property'
                    ? ($properties[$story->linked_entity_id] ?? null)
                    : ($projects[$story->linked_entity_id] ?? null);

                $isPremium = $entity ? (bool) $entity->is_premium : false;

                return [
                    'story_id' => $story->story_id,
                    'media_type' => $story->media_type,
                    'media_url' => $story->media_url,
                    'thumbnail_url' => $story->thumbnail_url,
                    'duration_seconds' => $story->duration_seconds,
                    'created_at' => $story->created_at->toISOString(),
                    'expires_at' => $story->expires_at->toISOString(),
                    'view_count' => $story->view_count,
                    'is_active' => $story->is_active && $story->expires_at->isFuture(),
                    'is_premium' => $isPremium,
                    'linked_entity' => $entity ? [
                        'type' => $story->linked_entity_type,
                        'id' => $entity->id,
                        'slug' => $entity->slug_id,
                        'title' => $entity->title,
                        'is_premium' => $isPremium,
                        'location' => [
                            'address' => $entity->address ?? null,
                            'city' => $entity->city,
                            'state' => $entity->state,
                            'country' => $entity->country,
                            'latitude' => $entity->latitude,
                            'longitude' => $entity->longitude,
                        ],
                    ] : null,
                ];
            });

            return ApiResponseService::successResponse('Stories fetched successfully', [
                'premium_stories' => $data->filter(fn ($s) => $s['is_premium'])->values(),
                'non_premium_stories' => $data->filter(fn ($s) => ! $s['is_premium'])->values(),
            ]);
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Error fetching stories');

            return ApiResponseService::errorResponse('Something went wrong');
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveLinkedEntities($stories): array
    {
        $agentIds = $stories->pluck('agent_id')->unique()->filter(); // exclude 0 (admin)
        $propertyIds = $stories->where('linked_entity_type', 'property')->pluck('linked_entity_id')->unique();
        $projectIds = $stories->where('linked_entity_type', 'project')->pluck('linked_entity_id')->unique();

        $agents = Customer::whereIn('id', $agentIds)
            ->select('id', 'name', 'profile', 'mobile', 'country_code', 'is_agent_verified', 'slug_id')
            ->with('agent_profile')
            ->get()->keyBy('id');

        $properties = Property::whereIn('id', $propertyIds)
            ->select('id', 'slug_id', 'title', 'title_image', 'price', 'city', 'state', 'country', 'address', 'propery_type', 'is_premium')
            ->with('translations')
            ->get()->keyBy('id');

        $projects = Projects::whereIn('id', $projectIds)
            ->select('id', 'slug_id', 'title', 'image', 'city', 'state', 'country', 'is_premium', 'type')
            ->with('translations')
            ->get()->keyBy('id');

        return [$agents, $properties, $projects];
    }

    private function formatStory(Story $story, $properties, $projects, bool $isGuest, $seenStoryIds): array
    {
        $isProperty = $story->linked_entity_type === 'property';
        $entity = $isProperty
            ? ($properties[$story->linked_entity_id] ?? null)
            : ($projects[$story->linked_entity_id] ?? null);

        $isPremium = $entity ? (bool) $entity->is_premium : false;

        // is_seen: logged-in users check against pre-loaded set; guests are always false
        $isSeen = ! $isGuest && isset($seenStoryIds[$story->id]);

        $linkedEntity = null;
        if ($entity) {
            if ($isProperty) {
                // propery_type: 0 = Sell, 1 = Rent, 2 = Sold, 3 = Rented
                $typeMap = [0 => 'Sell', 1 => 'Rent', 2 => 'Sold', 3 => 'Rented'];

                $linkedEntity = [
                    'type' => 'property',
                    'id' => $entity->id,
                    'slug' => $entity->slug_id,
                    'title' => $entity->title,
                    'translated_title' => $entity->translated_title,
                    'image' => $entity->title_image,
                    'price' => $entity->price,
                    'city' => $entity->city,
                    'state' => $entity->state,
                    'country' => $entity->country,
                    'address' => $entity->address,
                    'property_type' => $typeMap[$entity->propery_type] ?? 'Sell',
                    'is_premium' => $isPremium,
                    'is_favourite' => (bool) $entity->is_favourite,
                ];
            } else {
                $linkedEntity = [
                    'type' => 'project',
                    'id' => $entity->id,
                    'slug' => $entity->slug_id,
                    'title' => $entity->title,
                    'translated_title' => $entity->translated_title,
                    'image' => $entity->image,
                    'city' => $entity->city,
                    'state' => $entity->state,
                    'country' => $entity->country,
                    'project_type' => $entity->type,
                    'is_premium' => $isPremium,
                    // Projects have no favourites table/feature yet (see
                    // ProjectApiController's most_liked comment) — always false.
                    'is_favourite' => false,
                ];
            }
        }

        return [
            'story_id' => $story->story_id,
            'media_type' => $story->media_type,
            'media_url' => $story->media_url,
            'thumbnail_url' => $story->thumbnail_url,
            'duration_seconds' => $story->duration_seconds,
            'created_at' => $story->created_at->toISOString(),
            'expires_at' => $story->expires_at->toISOString(),
            'view_count' => $story->view_count,
            'is_premium' => $isPremium,
            'is_seen' => $isSeen,
            'linked_entity' => $linkedEntity,
        ];
    }

    private function deleteStoryFiles(Story $story): void
    {
        $disk = 'public';

        if ($story->media_type === 'image') {
            $path = 'stories/images/'.basename($story->media_url);
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } else {
            $path = 'stories/videos/'.basename($story->media_url);
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
            if ($story->thumbnail_url) {
                $thumbPath = 'stories/thumbnails/'.basename($story->thumbnail_url);
                if (Storage::disk($disk)->exists($thumbPath)) {
                    Storage::disk($disk)->delete($thumbPath);
                }
            }
        }
    }
}
