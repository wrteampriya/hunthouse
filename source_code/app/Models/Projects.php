<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\HasRoleContext;
use App\Traits\ManageTranslations;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Projects extends Model
{
    use HasAppTimezone, HasFactory, HasRoleContext, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $with = ['gallary_images', 'documents'];

    public const VIDEO_CUSTOM = 0;

    public const VIDEO_YOUTUBE = 1;

    public const VIDEO_VIMEO = 2;

    protected $fillable = [
        'title',
        'slug_id',
        'category_id',
        'description',
        'location',
        'added_by',
        'is_admin_listing',
        'country',
        'state',
        'city',
        'latitude',
        'longitude',
        'video_link',
        'video_type',
        'type',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'meta_image',
        'status',
        'request_status',
        'total_click',
        'is_premium',
        'expiry_date',
        'edit_reason',
        'role_context',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'is_admin_listing' => 'boolean',
        'status' => 'integer',
        'total_click' => 'integer',
    ];

    protected $appends = [
        'is_promoted',
        'is_feature_available',
        'low_quality_title_image',
        'is_expired',
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(static function ($project) {
            if (collect($project)->isNotEmpty()) {
                // before delete() method call this

                // Delete Title Image
                if ($project->getRawOriginal('image') != '') {
                    $path = config('global.PROJECT_TITLE_IMG_PATH').$project->image;
                    FileService::clearCachedBlurImageUrl('blur_project_title_image_'.$project->id);
                    FileService::delete($path, $project->getRawOriginal('image'));
                }

                // Delete Gallery Image
                if (isset($project->gallery) && collect($project->gallery)->isNotEmpty()) {
                    foreach ($project->gallery as $row) {
                        if (ProjectDocuments::where('id', $row->id)->delete()) {
                            $image = $row->getRawOriginal('name');
                            $path = config('global.PROJECT_DOCUMENT_PATH');
                            FileService::delete($path, $image);
                        }
                    }
                }

                // Delete Documents
                if (isset($project->documents) && collect($project->documents)->isNotEmpty()) {
                    foreach ($project->documents as $row) {
                        if (ProjectDocuments::where('id', $row->id)->delete()) {
                            $file = $row->getRawOriginal('name');
                            $path = config('global.PROJECT_DOCUMENT_PATH');
                            FileService::delete($path, $file);
                        }
                    }
                }

                // Delete Floor Plans
                if (isset($project->floor_plans) && collect($project->floor_plans)->isNotEmpty()) {
                    foreach ($project->floor_plans as $row) {
                        $file = $row->getRawOriginal('document');
                        $path = config('global.PROJECT_DOCUMENT_PATH');
                        FileService::delete($path, $file);
                        ProjectPlans::where('id', $row->id)->delete();
                    }
                }
            }
        });
    }

    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'category_id')->select('id', 'category', 'parameter_types', 'image');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'added_by');
    }

    public function gallary_images()
    {
        return $this->hasMany(ProjectDocuments::class, 'project_id')->where('type', 'image');
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocuments::class, 'project_id')->whereIn('type', ['doc']);
    }

    public function plans()
    {
        return $this->hasMany(ProjectPlans::class, 'project_id');
    }

    public function reject_reason()
    {
        return $this->hasMany(RejectReason::class, 'project_id');
    }

    public function advertisement()
    {
        return $this->hasMany(Advertisement::class, 'project_id', 'id')->where('for', 'project');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getImageAttribute($image, $fullUrl = true)
    {
        if (! empty($image)) {
            $path = config('global.PROJECT_TITLE_IMG_PATH').$image;

            return ! empty($path) ? FileService::getFileUrl($path) : '';
        }

        return null;
    }

    public function getMetaImageAttribute($image, $fullUrl = true)
    {
        if (! empty($image)) {
            $path = config('global.PROJECT_SEO_IMG_PATH').$image;

            return ! empty($path) ? FileService::getFileUrl($path) : '';
        }

        return null;
    }

    public function getIsPromotedAttribute()
    {
        $id = $this->id;

        return $this->whereHas('advertisement', function ($query) use ($id) {
            $query->where(['project_id' => $id, 'status' => 0, 'is_enable' => 1, 'for' => 'project']);
        })->count() ? true : false;
    }

    public function getIsFeatureAvailableAttribute()
    {
        $id = $this->id;

        $isProjectTypeValid = $this->where('id', $this->id)->where(['status' => 1, 'request_status' => 'approved'])->exists();

        // Check if there is no advertisement or if the advertisement has expired
        $adsQuery = $this->advertisement()->where('project_id', $id);
        $hasExpiredAdvertisement = ! $adsQuery->exists() || ! $adsQuery->where('status', '!=', 3)->exists();

        return $isProjectTypeValid && $hasExpiredAdvertisement;
    }

    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }

    public function getTranslatedDescriptionAttribute()
    {
        return HelperService::getTranslatedData($this, $this->description, 'description');
    }

    public function getIsExpiredAttribute()
    {
        return ($this->expiry_date !== null && $this->expiry_date < now()) ? 1 : 0;
    }

    public function scopeOnlyActive($query)
    {
        return $query->where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) {
            $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
        });
    }

    /**
     * Accessor for low-quality title image (base64 blur)
     */
    public function getLowQualityTitleImageAttribute()
    {
        try {
            $rawImage = $this->getRawOriginal('image');
            if (! $rawImage) {
                return null;
            }

            $propertyImagePath = config('global.PROJECT_TITLE_IMG_PATH').$rawImage;
            $cacheKey = 'blur_project_title_image_'.$this->id;
            $blurUrl = FileService::getCachedBlurImageUrl($propertyImagePath, $cacheKey);

            return $blurUrl;
        } catch (Exception $e) {
            Log::error('Error generating low-quality project title image: '.$e->getMessage());

            return null;
        }
    }

    public function getVideoLinkAttribute($value)
    {
        if ($this->video_type == self::VIDEO_CUSTOM && ! empty($value)) {
            $path = config('global.PROJECT_VIDEO_PATH').$value;

            return ! empty($path) ? FileService::getFileUrl($path) : '';
        }

        return $value;
    }
}
