<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Feature extends Model
{
    use HasFactory,SoftDeletes, HasAppTimezone, ManageTranslations;
    protected $hidden = array('created_at','updated_at','deleted_at');
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $fillable = array(
        'id',
        'name',
        'type',
        'status',
        'user_type'
    );
    protected $casts = [
        'status' => 'boolean',
    ];

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
