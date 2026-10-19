<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialMediaLink extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'icon',
        'status',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function getIconAttribute($icon)
    {
        $path = $icon ? config('global.SOCIAL_MEDIA_ICON_PATH').$icon : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function values()
    {
        return $this->hasMany(AgentSocialMediaValue::class);
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedNameAttribute()
    {
        return HelperService::getTranslatedData($this, $this->name, 'name');
    }
}
