<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoSettings extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = ['page', 'image', 'title', 'description', 'keywords', 'schema_markup'];

    public function getImageAttribute($image)
    {
        $path = $image ? config('global.SEO_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
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

    public function getTranslatedKeywordsAttribute()
    {
        return HelperService::getTranslatedData($this, $this->keywords, 'keywords');
    }

    public function getTranslatedSchemaMarkupAttribute()
    {
        return HelperService::getTranslatedData($this, $this->schema_markup, 'schema_markup');
    }
}
