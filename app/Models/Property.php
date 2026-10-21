<?php

namespace App\Models;

use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\HasRoleContext;
use App\Traits\ManageTranslations;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Property extends Model
{
    use HasAppTimezone, HasFactory, HasRoleContext, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'propertys';

    const VIDEO_CUSTOM = 0;

    const VIDEO_YOUTUBE = 1;

    const VIDEO_VIMEO = 2;

    protected $fillable = [
        'category_id',
        'title',
        'slug_id',
        'description',
        'address',
        'client_address',
        'propery_type',
        'rentduration',
        'price',
        'title_image',
        'state',
        'country',
        'country_id',
        'currency_id',
        'custom_video',
        'status',
        'request_status',
        'total_click',
        'latitude',
        'longitude',
        'three_d_image',
        'is_premium',
        'expiry_date',
        'is_demo',
        'edit_reason',
        'role_context',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'gallery',
        'documents',
        'is_favourite',
        'low_quality_title_image',
        'is_expired',
        'currency_symbol',
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(static function ($property) {
            if (collect($property)->isNotEmpty()) {
                // before delete() method call this

                // Delete Title Image
                if ($property->getRawOriginal('title_image') != '') {
                    $path = config('global.PROPERTY_TITLE_IMG_PATH');
                    $rawImage = $property->getRawOriginal('title_image');
                    FileService::clearCachedBlurImageUrl('blur_property_title_image_'.$property->id);
                    FileService::delete($path, $rawImage);
                }

                // Delete 3D image
                if ($property->getRawOriginal('three_d_image') != '') {
                    $path = config('global.3D_IMG_PATH');
                    $rawImage = $property->getRawOriginal('three_d_image');
                    FileService::delete($path, $rawImage);
                }

                // Delete Gallery Image
                if (isset($property->gallery) && collect($property->gallery)->isNotEmpty()) {
                    foreach ($property->gallery as $row) {
                        if (PropertyImages::where('id', $row->id)->delete()) {
                            if ($row->getRawOriginal('image') != '') {
                                $path = config('global.PROPERTY_GALLERY_IMG_PATH').$property->id.'/';
                                $rawImage = $row->getRawOriginal('image');
                                FileService::delete($path, $rawImage);
                            }
                        }
                    }
                    if (is_dir(storage_path('app/public').config('global.PROPERTY_GALLERY_IMG_PATH').$property->id)) {
                        rmdir(storage_path('app/public').config('global.PROPERTY_GALLERY_IMG_PATH').$property->id);
                    }
                }

                // Delete Documents
                if (isset($property->documents) && collect($property->documents)->isNotEmpty()) {
                    foreach ($property->documents as $row) {
                        if (PropertiesDocument::where('id', $row->id)->delete()) {
                            if ($row->getRawOriginal('name') != '') {
                                $path = config('global.PROPERTY_DOCUMENT_PATH').$property->id.'/';
                                $rawImage = $row->getRawOriginal('name');
                                FileService::delete($path, $rawImage);
                            }
                        }
                    }
                    if (is_dir(storage_path('app/public').config('global.PROPERTY_DOCUMENT_PATH').$property->id)) {
                        rmdir(storage_path('app/public').config('global.PROPERTY_DOCUMENT_PATH').$property->id);
                    }
                }
                /** Delete the properties associated data */
                // Delete Directly without modal boot events
                Advertisement::where('property_id', $property->id)->delete();
                AssignedOutdoorFacilities::where('property_id', $property->id)->delete();
                Favourite::where('property_id', $property->id)->delete();
                AssignParameters::where('property_id', $property->id)->delete();
                InterestedUser::where('property_id', $property->id)->delete();
                PropertysInquiry::where('propertys_id', $property->id)->delete();
                user_reports::where('property_id', $property->id)->delete();
                InterestedUser::where('property_id', $property->id)->delete();

                // Delete The Data with modal boot events
                $chats = Chats::where('property_id', $property->id)->get();
                if (collect($chats)->isNotEmpty()) {
                    foreach ($chats as $chat) {
                        if (collect($chat)->isNotEmpty()) {
                            $chat->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
                $sliders = Slider::where('propertys_id', $property->id)->get();
                if (collect($sliders)->isNotEmpty()) {
                    foreach ($sliders as $slider) {
                        if (collect($slider)->isNotEmpty()) {
                            $slider->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
                $notifications = Notifications::where('propertys_id', $property->id)->get();
                if (collect($notifications)->isNotEmpty()) {
                    foreach ($notifications as $notification) {
                        if (collect($notification)->isNotEmpty()) {
                            $notification->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
            }
        });
    }

    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'category_id')->select('id', 'category', 'parameter_types', 'image');
    }

    public function propertyCountry()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function propertyCurrency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'added_by', 'fcm_id', 'notification');
    }

    public function user()
    {
        return $this->hasMany(User::class, 'id', 'added_by', 'fcm_id', 'notification');
    }

    public function assignParameter()
    {
        return $this->morphMany(AssignParameters::class, 'modal');
    }

    public function parameters()
    {
        return $this->belongsToMany(parameter::class, 'assign_parameters', 'modal_id', 'parameter_id')->withPivot('value');
    }

    public function assignfacilities()
    {
        return $this->hasMany(AssignedOutdoorFacilities::class, 'property_id', 'id');
    }

    public function favourite()
    {
        return $this->hasMany(Favourite::class, 'property_id', 'id');
    }

    public function interested_users()
    {
        return $this->hasMany(InterestedUser::class, 'property_id');
    }

    // public function assign_parameter()
    // {
    //     return $this->hasMany(AssignParameters::class);
    // }
    public function advertisement()
    {
        return $this->hasMany(Advertisement::class)->where('for', 'property');
    }

    public function reject_reason()
    {
        return $this->hasMany(RejectReason::class, 'property_id');
    }

    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getGalleryAttribute()
    {
        $data = PropertyImages::select('id', 'propertys_id', 'image')->where('propertys_id', $this->id)->get()->map(function ($item) {
            $image = $item->getRawOriginal('image');
            if ($image != '') {
                $item->image_url = $item->image;
            }

            return $item;
        });

        return $data;
    }

    public function getTitleImageAttribute($image)
    {
        $path = ! empty($image) ? config('global.PROPERTY_TITLE_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getMetaImageAttribute($image)
    {
        $path = ! empty($image) ? config('global.PROPERTY_SEO_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getThreeDImageAttribute($image)
    {
        $path = ! empty($image) ? config('global.3D_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getProperyTypeAttribute($value)
    {
        if ($value == 0) {
            return 'sell';
        } elseif ($value == 1) {
            return 'rent';
        } elseif ($value == 2) {
            return 'sold';
        } elseif ($value == 3) {
            return 'rented';
        }
    }

    // Correctly-spelled twin of the accessor above — queries that alias the
    // column as `propery_type as property_type` land the raw value under this
    // attribute name instead, and Eloquent only fires an accessor whose name
    // matches the attribute exactly, so without this the alias bypasses the
    // sell/rent/sold/rented conversion entirely.
    // Must be idempotent: some call sites do
    // `$model->property_type = $model->propery_type;` (reading the raw column
    // through the OLD accessor, which already returns text) — that re-assigns
    // an already-converted label, which this accessor then receives as
    // $value on the next read. Passing a string like 'sell' into the raw
    // int/string checks below matches none of them and falls through to
    // null, so already-converted labels must short-circuit here.
    public function getPropertyTypeAttribute($value)
    {
        if (in_array($value, ['sell', 'rent', 'sold', 'rented'], true)) {
            return $value;
        }

        return $this->getProperyTypeAttribute($value);
    }

    public function getIsPromotedAttribute()
    {
        $id = $this->id;

        return $this->whereHas('advertisement', function ($query) use ($id) {
            $query->where(['property_id' => $id, 'status' => 0, 'is_enable' => 1]);
        })->count() ? true : false;
    }

    public function getHomePromotedAttribute()
    {
        $id = $this->id;

        return $this->whereHas('advertisement', function ($query) use ($id) {
            $query->where(['property_id' => $id, 'type' => 'HomeScreen', 'status' => 0, 'is_enable' => 1]);
        })->count() ? true : false;
    }

    public function getListPromotedAttribute()
    {
        $id = $this->id;

        return $this->whereHas('advertisement', function ($query) use ($id) {
            $query->where(['property_id' => $id, 'type' => 'ProductListing', 'status' => 0, 'is_enable' => 1]);
        })->count() ? true : false;
    }

    public function getIsFavouriteAttribute()
    {
        $propertyId = $this->id;
        $auth = Auth::guard('sanctum');
        if ($auth->check()) {
            $userId = $auth->user()->id;

            return $this->whereHas('favourite', function ($query) use ($userId, $propertyId) {
                $query->where(['user_id' => $userId, 'property_id' => $propertyId]);
            })->count() >= 1 ? 1 : 0;
        }

        return 0;
    }

    public function getParametersAttribute()
    {
        // Not cached: translated_name/translated_option_value/translated_value
        // depend on the request's Content-Language header, and change whenever
        // a parameter's name or option translations are added/edited — a
        // forever cache keyed only by property id served the first requester's
        // language (and translation state) to everyone else indefinitely.
        $parameterQueryData = $this->parameters()->with('translations')->get();
        $parameters = [];

        if ($parameterQueryData->isNotEmpty()) {
            foreach ($parameterQueryData as $res) {
                $res = (object) $res;

                // JSON decode & translation
                if (is_string($res['pivot']['value']) && is_array(json_decode($res['pivot']['value'], true))) {
                    $value = json_decode($res['pivot']['value'], true);
                    $translatedValue = [];
                    if ($res->translated_option_value) {
                        $translatedMap = collect($res->translated_option_value)->keyBy('value');
                        foreach ($value as $val) {
                            if (isset($translatedMap[$val])) {
                                $translatedValue[] = $translatedMap[$val]['translated'];
                            }
                        }
                    }
                } else {
                    if ($res['type_of_parameter'] == 'file') {
                        $value = ($res['pivot']['value'] == 'null' || ! $res['pivot']['value'])
                            ? ''
                            : FileService::getFileUrl(config('global.PARAMETER_IMG_PATH').'/'.$res['pivot']['value']);
                    } else {
                        $value = ($res['pivot']['value'] == 'null') ? '' : $res['pivot']['value'];
                    }
                }

                if (collect($value)->isNotEmpty()) {
                    $parameters[] = [
                        'id' => $res->id,
                        'name' => $res->name,
                        'image' => $res->image,
                        'is_required' => $res->is_required,
                        'type_of_parameter' => $res->type_of_parameter,
                        'type_values' => $res->type_values,
                        'translated_option_value' => $res->translated_option_value,
                        'value' => $value,
                        'translated_value' => $translatedValue ?? [],
                        'translated_name' => $res->translated_name,
                        'translations' => $res->translations->map(fn ($t) => [
                            'language_id' => $t->language_id,
                            'value' => $t->value,
                        ])->toArray(),
                    ];
                }
            }
        }

        // Sort by category order
        if ($this->relationLoaded('category') && $this->category?->parameter_types) {
            $orderIds = array_map('intval', explode(',', $this->category->parameter_types));
            usort($parameters, fn ($a, $b) => (array_search($a['id'], $orderIds) ?? PHP_INT_MAX) <=> (array_search($b['id'], $orderIds) ?? PHP_INT_MAX));
        }

        return $parameters;
    }

    public function getAssignFacilitiesAttribute()
    {
        // Not cached: translated_name depends on the request's Content-Language
        // header and changes whenever a facility's name translation is added or
        // edited — a forever cache keyed only by property id served the first
        // requester's language (and translation state) to everyone else
        // indefinitely.
        $assignFacilitiesQuery = $this->assignfacilities()->with('outdoorfacilities.translations')->get();
        $assignFacilitiesData = [];

        foreach ($assignFacilitiesQuery as $facility) {
            if ($facility->outdoorfacilities) {
                $assignFacilitiesData[] = [
                    'id' => $facility->id,
                    'property_id' => $facility->property_id,
                    'facility_id' => $facility->facility_id,
                    'distance' => $facility->distance,
                    'created_at' => $facility->created_at,
                    'updated_at' => $facility->updated_at,
                    'name' => $facility->outdoorfacilities->name,
                    'image' => $facility->outdoorfacilities->image,
                    'translated_name' => $facility->outdoorfacilities->translated_name,
                    'translations' => $facility->outdoorfacilities->translations->map(fn ($t) => [
                        'language_id' => $t->language_id,
                        'value' => $t->value,
                    ])->toArray(),
                ];
            }
        }

        return $assignFacilitiesData;
    }

    public function getDocumentsAttribute()
    {
        return PropertiesDocument::select('id', 'property_id', 'name', 'type')->where('property_id', $this->id)->get()->map(function ($document) {
            $document->id = $document->id;
            $document->file_name = $document->getRawOriginal('name');
            $document->file = $document->name;
            unset($document->name);

            return $document;
        });
    }

    public function getIsUserVerifiedAttribute()
    {
        // Only the owner of THIS property can make its badge show
        $verification = VerifyCustomer::where('user_id', $this->added_by)->first();

        // Permanent manual/admin approval
        if ($verification && $verification->status === 'approved' && in_array($verification->source, ['admin', 'manual'])) {
            return true;
        }

        // Subscription approval: only while a package is active
        return UserPackage::where('user_id', $this->added_by)->onlyActive()->exists();
    }

    public function getIsFeatureAvailableAttribute()
    {
        $id = $this->id;

        // Check if the property type is 0 or 1
        $isPropertyTypeValid = $this->where('id', $this->id)
            ->whereIn('propery_type', [0, 1])->where(['status' => 1, 'request_status' => 'approved'])
            ->exists();

        // Check if there is no advertisement or if the advertisement has expired
        $adsQuery = $this->advertisement()->where('property_id', $id);
        $hasExpiredAdvertisement = ! $adsQuery->exists() || ! $adsQuery->where('status', '!=', 3)->exists();

        return $isPropertyTypeValid && $hasExpiredAdvertisement;
    }

    /**
     * Get translated name attribute
     */
    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }

    /**
     * Get translated name attribute
     */
    public function getTranslatedDescriptionAttribute()
    {
        return HelperService::getTranslatedData($this, $this->description, 'description');
    }

    public function getTranslatedMetaTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->meta_title, 'meta_title');
    }

    public function getTranslatedMetaDescriptionAttribute()
    {
        return HelperService::getTranslatedData($this, $this->meta_description, 'meta_description');
    }

    public function getTranslatedMetaKeywordsAttribute()
    {
        return HelperService::getTranslatedData($this, $this->meta_keywords, 'meta_keywords');
    }

    public function getTranslatedSchemaMarkupAttribute()
    {
        return HelperService::getTranslatedData($this, $this->schema_markup, 'schema_markup');
    }

    protected $casts = [
        'category_id' => 'integer',
        'status' => 'integer',
        'is_premium' => 'boolean',
        'total_click' => 'integer',
    ];

    /**
     * Accessor for low-quality title image (base64 blur)
     */
    public function getLowQualityTitleImageAttribute()
    {
        try {
            $rawImage = $this->getRawOriginal('title_image');
            if (! $rawImage) {
                return null;
            }

            $propertyImagePath = config('global.PROPERTY_TITLE_IMG_PATH').$rawImage;
            $cacheKey = 'blur_property_title_image_'.$this->id;
            $blurUrl = FileService::getCachedBlurImageUrl($propertyImagePath, $cacheKey);

            return $blurUrl;
        } catch (Exception $e) {
            Log::error('Error generating low-quality image: '.$e->getMessage());

            return null;
        }
    }

    public function getVideoLinkAttribute($value)
    {
        if ($this->video_type == self::VIDEO_CUSTOM && ! empty($value)) {
            $path = config('global.PROPERTY_VIDEO_PATH').$value;

            return FileService::getFileUrl($path);
        }

        return $value;
    }

    public function getIsExpiredAttribute()
    {
        return ($this->expiry_date !== null && $this->expiry_date < now()->startOfDay()) ? 1 : 0;
    }

    /**
     * The listing's currency symbol, so API clients can display price
     * without a separate get-currencies lookup — its own currency if set,
     * otherwise the system default currency.
     */
    public function getCurrencySymbolAttribute(): ?string
    {
        return optional($this->propertyCurrency)->symbol
            ?? optional((new CachingService)->getDefaultCurrency())->symbol;
    }

    public function scopeOnlyActive($query)
    {
        return $query->where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) {
            $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
        });
    }
}
