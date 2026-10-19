<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomPage extends Model
{
    use HasFactory, ManageTranslations;

    protected $fillable = [
        'title',
        'slug_id',
        'icon',
        'content',
        'status',
    ];

    public function getIconAttribute($icon)
    {
        $path = $icon ? config('global.CUSTOM_PAGE_ICON_PATH').$icon : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }

    public function getTranslatedContentAttribute()
    {
        return HelperService::getTranslatedData($this, $this->content, 'content');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }
}
