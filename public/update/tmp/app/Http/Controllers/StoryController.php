<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use App\Models\ProjectDocuments;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Models\Setting;
use App\Models\Story;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoryController extends Controller
{
    public function index()
    {
        $adminPhoto = url('assets/images/logo/logo.png');
        return view('story.index', compact('adminPhoto'));
    }

    public function getStoryList(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $limit  = (int) $request->input('limit', 10);
        $sort   = $request->input('sort', 'created_at');
        $order  = $request->input('order', 'desc');

        $query = Story::with(['agent:id,name,profile', 'agent.agent_profile:customer_id,agent_profile_photo'])
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->orderBy($sort, $order);

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->media_type);
        }

        $total = $query->count();
        $stories = $query->skip($offset)->take($limit)->get();

        $adminPhoto = url('assets/images/logo/logo.png');

        $propertyIds = $stories->where('linked_entity_type', 'property')->pluck('linked_entity_id')->unique();
        $projectIds  = $stories->where('linked_entity_type', 'project')->pluck('linked_entity_id')->unique();
        $properties = Property::whereIn('id', $propertyIds)->select('id', 'title', 'is_premium', 'title_image', 'city', 'price')->with('translations')->get()->keyBy('id');
        $projects   = Projects::whereIn('id', $projectIds)->select('id', 'title', 'is_premium', 'image', 'city')->with('translations')->get()->keyBy('id');

        $rows = [];
        foreach ($stories as $story) {
            $entity  = $story->linked_entity_type === 'property'
                ? ($properties[$story->linked_entity_id] ?? null)
                : ($projects[$story->linked_entity_id] ?? null);

            $isExpired = ! $story->is_active || $story->expires_at->isPast();

            $entityThumb = $story->thumbnail_url ?? $story->media_url;
            if ($entity) {
                if ($story->linked_entity_type === 'property') {
                    $rawImg = $entity->getRawOriginal('title_image');
                    if ($rawImg) $entityThumb = $this->mediaUrl(config('global.PROPERTY_TITLE_IMG_PATH') . $rawImg) ?? $entityThumb;
                } else {
                    $rawImg = $entity->getRawOriginal('image');
                    if ($rawImg) $entityThumb = $this->mediaUrl(config('global.PROJECT_TITLE_IMG_PATH') . $rawImg) ?? $entityThumb;
                }
            }

            $placeholderPhoto = url('assets/images/placeholder/profile_placeholder.png');
            if ($story->agent_id == 0) {
                $resolvedPhoto = $adminPhoto;
            } elseif ($story->agent) {
                $resolvedPhoto = $this->resolveProfileUrl(
                    $story->agent->agent_profile?->getRawOriginal('agent_profile_photo'),
                    config('global.AGENT_PROFILE_IMG_PATH')
                ) ?: $this->resolveProfileUrl(
                    $story->agent->getRawOriginal('profile'),
                    config('global.USER_IMG_PATH')
                ) ?: $placeholderPhoto;
            } else {
                $resolvedPhoto = $placeholderPhoto;
            }

            $thumbSrc = $story->thumbnail_url ?? ($story->media_type === 'image' ? $story->media_url : null);
            if ($thumbSrc) {
                $preview = '<div class="position-relative d-inline-block rounded overflow-hidden border" style="width:44px;height:60px;cursor:pointer;background:#111827;" onclick=\'openStoryById("' . $story->story_id . '")\'><img src="'.$thumbSrc.'" class="w-100 h-100" style="object-fit:contain;" alt=""></div>';
            } else {
                $preview = '<div class="position-relative d-inline-block rounded overflow-hidden border bg-dark d-flex align-items-center justify-content-center" style="width:44px;height:60px;cursor:pointer;" onclick=\'openStoryById("' . $story->story_id . '")\'><i class="fas fa-play text-white small"></i></div>';
            }

            $uploaderHtml = $story->agent_id == 0
                ? '<span class="badge bg-primary">'.__('Admin').'</span>'
                : e($story->agent->name ?? '—');

            $mediaHtml = '<span class="badge '.($story->media_type === 'video' ? 'bg-primary' : 'bg-info').'">'.($story->media_type === 'video' ? __('Video') : __('Image')).'</span>';

            $linkedHtml = '<span class="badge '.($story->linked_entity_type === 'property' ? 'bg-primary' : 'bg-secondary').' me-1">'.($story->linked_entity_type === 'property' ? __('Property') : __('Project')).'</span>';
            if ($entity && $entity->is_premium) {
                $linkedHtml .= '<span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:#ffc107;border-radius:4px;margin-right:4px;"><i class="fas fa-crown" style="font-size:0.65rem;color:#fff;"></i></span>';
            }
            $linkedHtml .= e($entity?->translated_title ?? $entity?->title ?? '—');

            $statusHtml = $isExpired
                ? '<span class="badge bg-light-danger text-danger">'.__('Expired').'</span>'
                : '<span class="badge bg-light-success text-success">'.__('Active').'</span>';

            $operate = '';
            if (has_permissions('delete', 'story')) {
                $onclickAttr = htmlspecialchars('openStoryById("' . $story->story_id . '")', ENT_QUOTES);
                $previewBtn  = BootstrapTableService::button('fas fa-mobile-alt', '', ['btn', 'icon', 'btn-light-primary', 'border', 'border-primary', 'btn-sm', 'rounded-pill', 'me-1'], ['title' => __('Preview'), 'onclick' => $onclickAttr]);
                $deleteBtn   = BootstrapTableService::deleteButton(url('story/'.$story->id), $story->id);
                $operate     = $previewBtn.$deleteBtn;
            }

            $rows[] = [
                'id'             => $story->id,
                'preview'        => $preview,
                'uploader'       => $uploaderHtml,
                'media_type'     => $mediaHtml,
                'linked_listing' => $linkedHtml,
                'view_count'     => number_format($story->view_count),
                'expires_at'     => $story->expires_at->format('d M Y, h:i A'),
                'status'         => $statusHtml,
                'operate'        => $operate,
                'story_raw'      => [
                    'story_id'            => $story->story_id,
                    'id'                  => $story->id,
                    'media_type'          => $story->media_type,
                    'media_url'           => $story->media_url,
                    'thumbnail_url'       => $story->thumbnail_url,
                    'duration_seconds'    => $story->duration_seconds,
                    'view_count'          => $story->view_count,
                    'is_expired'          => $isExpired,
                    'linked_entity_title' => $entity?->translated_title ?? $entity?->title ?? '—',
                    'linked_entity_type'  => $story->linked_entity_type,
                    'linked_entity_city'  => $entity?->city ?? '',
                    'linked_entity_price' => $story->linked_entity_type === 'property' ? format_price($entity?->price) : null,
                    'entity_is_premium'   => $entity ? (bool) $entity->is_premium : false,
                    'entity_thumb'        => $entityThumb,
                ],
                'agent_raw'      => [
                    'agent_id' => $story->agent_id,
                    'uploader' => $story->agent_id == 0 ? __('Admin') : ($story->agent->name ?? __('Agent')),
                    'profile'  => $resolvedPhoto,
                ],
            ];
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function create()
    {
        if (! has_permissions('create', 'story')) {
            return redirect()->route('story.index')->with('error', PERMISSION_ERROR_MSG);
        }

        $properties  = Property::where('status', 1)->where('post_type', 0)->select('id', 'title')->orderBy('title')->get();
        $projects    = Projects::where('status', 1)->where('is_admin_listing', 1)->select('id', 'title')->orderBy('title')->get();
        $maxDuration = (int) (Setting::where('type', 'story_max_duration')->value('data') ?? 60);

        return view('story.create', compact('properties', 'projects', 'maxDuration'));
    }

    public function getEntityMedia(Request $request)
    {
        if (! has_permissions('create', 'story')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $type = $request->input('type'); // property | project
        $id   = (int) $request->input('id');

        $images = [];

        if ($type === 'property') {
            $property = Property::find($id);
            if (! $property) return response()->json(['images' => []]);

            $rawTitle = $property->getRawOriginal('title_image');
            if ($rawTitle) {
                $p = config('global.PROPERTY_TITLE_IMG_PATH') . $rawTitle;
                $images[] = ['url' => $this->mediaUrl($p), 'path' => $p, 'label' => __('Title Image')];
            }

            $gallery = PropertyImages::where('propertys_id', $id)->get();
            foreach ($gallery as $img) {
                $raw = $img->getRawOriginal('image');
                if ($raw) {
                    $p = config('global.PROPERTY_GALLERY_IMG_PATH') . $id . '/' . $raw;
                    $images[] = ['url' => $this->mediaUrl($p), 'path' => $p, 'label' => ''];
                }
            }

        } elseif ($type === 'project') {
            $project = Projects::find($id);
            if (! $project) return response()->json(['images' => []]);

            $rawTitle = $project->getRawOriginal('image');
            if ($rawTitle) {
                $p = config('global.PROJECT_TITLE_IMG_PATH') . $rawTitle;
                $images[] = ['url' => $this->mediaUrl($p), 'path' => $p, 'label' => __('Title Image')];
            }

            $gallery = ProjectDocuments::where('project_id', $id)->where('type', 'image')->get();
            foreach ($gallery as $img) {
                $raw = $img->getRawOriginal('name');
                if ($raw) {
                    $p = config('global.PROJECT_DOCUMENT_PATH') . $raw;
                    $images[] = ['url' => $this->mediaUrl($p), 'path' => $p, 'label' => ''];
                }
            }
        }

        return response()->json(['images' => array_values(array_filter($images, fn($i) => ! empty($i['url'])))]);
    }

    /**
     * Build a URL from a raw DB profile value (filename or full URL) without
     * any file-existence check so it works on both local disk and S3.
     */
    private function resolveProfileUrl(?string $raw, string $prefix): ?string
    {
        if (empty($raw)) return null;
        if (filter_var($raw, FILTER_VALIDATE_URL)) return $raw;
        return Storage::disk('public')->url($prefix . $raw);
    }

    /** Build a public URL for a storage path, works for local disk and S3. */
    private function mediaUrl(string $path): ?string
    {
        // Try storage disk first (works for S3 and storage/app/public)
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        // Fallback: file stored directly in public/ folder (older upload method)
        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return null;
    }

    public function store(Request $request)
    {
        if (! has_permissions('create', 'story')) {
            return redirect()->route('story.index')->with('error', PERMISSION_ERROR_MSG);
        }

        $hasUpload = $request->hasFile('media');
        $hasPath   = $request->filled('listing_media_path');

        $request->validate([
            'entity_type'        => 'required|in:property,project',
            'entity_id'          => 'required|integer',
            'media_type'         => 'required|in:image,video',
            'media'              => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,webm|max:102400',
            'listing_media_path' => 'nullable|string',
            'thumbnail'          => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if (! $hasUpload && ! $hasPath) {
            return back()->with('error', 'Please select or upload a media file.')->withInput();
        }

        try {
            $maxDuration = (int) (Setting::where('type', 'story_max_duration')->value('data') ?? 60);

            // Generate unique story_id
            do {
                $storyId = 'str_' . Str::lower(Str::random(6));
            } while (Story::where('story_id', $storyId)->exists());

            if ($hasUpload) {
                if ($request->media_type === 'image') {
                    $fileName = FileService::compressAndUpload($request->file('media'), 'stories/images/', false);
                    if (! $fileName) {
                        return back()->with('error', 'Failed to upload image')->withInput();
                    }
                    $mediaUrl     = Storage::disk('public')->url('stories/images/' . $fileName);
                    $thumbnailUrl = $mediaUrl;
                } else {
                    $file     = $request->file('media');
                    $ext      = strtolower($file->getClientOriginalExtension());
                    $fileName = $storyId . '.' . $ext;
                    $file->storeAs('stories/videos', $fileName, 'public');
                    $mediaUrl = Storage::disk('public')->url('stories/videos/' . $fileName);

                    $thumbnailUrl = null;
                    if ($request->hasFile('thumbnail')) {
                        $thumbName = FileService::compressAndUpload($request->file('thumbnail'), 'stories/thumbnails/', false);
                        $thumbnailUrl = $thumbName
                            ? Storage::disk('public')->url('stories/thumbnails/' . $thumbName)
                            : null;
                    }
                }
            } else {
                // Copy from listing storage path — no re-upload needed
                $srcPath  = $request->input('listing_media_path');
                $ext      = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION)) ?: ($request->media_type === 'image' ? 'jpg' : 'mp4');
                $fileName = $storyId . '.' . $ext;

                if (Storage::disk('public')->exists($srcPath)) {
                    $contents = Storage::disk('public')->get($srcPath);
                } elseif (file_exists(public_path($srcPath))) {
                    $contents = file_get_contents(public_path($srcPath));
                } else {
                    return back()->with('error', 'Selected media file not found.')->withInput();
                }

                if ($request->media_type === 'image') {
                    Storage::disk('public')->put('stories/images/' . $fileName, $contents);
                    $mediaUrl     = Storage::disk('public')->url('stories/images/' . $fileName);
                    $thumbnailUrl = $mediaUrl;
                } else {
                    Storage::disk('public')->put('stories/videos/' . $fileName, $contents);
                    $mediaUrl     = Storage::disk('public')->url('stories/videos/' . $fileName);
                    $thumbnailUrl = null;
                }
            }

            Story::create([
                'story_id'           => $storyId,
                'agent_id'           => 0,
                'media_type'         => $request->media_type,
                'media_url'          => $mediaUrl,
                'thumbnail_url'      => $thumbnailUrl,
                'duration_seconds'   => $request->media_type === 'image' ? 5 : $maxDuration,
                'linked_entity_type' => $request->entity_type,
                'linked_entity_id'   => (int) $request->entity_id,
                'is_active'          => true,
                'expires_at'         => now()->addHours(24),
            ]);

            return redirect()->route('story.index')->with('success', 'Story uploaded successfully');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $story = Story::findOrFail($id);

            $disk = 'public';
            if ($story->media_type === 'image') {
                $path = 'stories/images/' . basename($story->media_url);
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } else {
                $videoPath = 'stories/videos/' . basename($story->media_url);
                if (Storage::disk($disk)->exists($videoPath)) {
                    Storage::disk($disk)->delete($videoPath);
                }
                if ($story->thumbnail_url) {
                    $thumbPath = 'stories/thumbnails/' . basename($story->thumbnail_url);
                    if (Storage::disk($disk)->exists($thumbPath)) {
                        Storage::disk($disk)->delete($thumbPath);
                    }
                }
            }

            $story->delete();

            return redirect()->route('story.index')->with('success', 'Story deleted successfully');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
