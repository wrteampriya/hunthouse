<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OutdoorFacilities extends Model
{
    use HasFactory, HasAppTimezone, ManageTranslations;
    protected $table = 'outdoor_facilities';
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $fillable = [
        'name',
        'image',
        'is_demo'
    ];
    public function getImageAttribute($image)
    {
        $path = $image ? config('global.FACILITY_IMAGE_PATH') . $image : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }
    public function assign_facilities()
    {
        return $this->hasMany(AssignedOutdoorFacilities::class, 'facility_id', 'id');
    }
    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get translated name attribute
     */
    public function getTranslatedNameAttribute()
    {
        return HelperService::getTranslatedData($this, $this->name, 'name');
    }
}
