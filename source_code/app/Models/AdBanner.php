<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdBanner extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'starts_at', 'ends_at'];

    protected $fillable = [
        'page',
        'platform',
        'placement',
        'image',
        'type',
        'external_link_url',
        'property_id',
        'duration_days',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function getImageAttribute($image)
    {
        $path = $image ? config('global.ADBANNER_IMAGE_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function getIsExpiredAttribute()
    {
        return $this->ends_at < now();
    }
}
