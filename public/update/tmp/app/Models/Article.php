<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'title',
        'slug_id',
        'view_count',
        'image',
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'meta_image',
        'schema_markup',
        'category_id',
        'country_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getImageAttribute($image)
    {
        $path = $image ? config('global.ARTICLE_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getMetaImageAttribute($image)
    {
        $path = $image ? config('global.ARTICLE_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function scopeForCountry($query, $countryId)
    {
        if (empty($countryId)) {
            return $query;
        }

        return $query->where(function ($q) use ($countryId) {
            $q->whereNull('country_id')->orWhere('country_id', $countryId);
        });
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }

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
    // public function getCreatedAtAttribute($value){
    //     return \Carbon\Carbon::parse($value)->diffForHumans();
    // }
}
