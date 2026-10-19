<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public $table = 'settings';

    protected $fillable = [
        'type',
        'data',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
    ];

    public function getDataAttribute($value)
    {
        if ($this->type == 'default_language') {
            if ($value == 'en-new') {
                return 'en';
            }
        }

        return $value;
    }

    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get translated data attribute
     */
    public function getTranslatedDataAttribute()
    {
        return HelperService::getTranslatedData($this, $this->data, 'data');
    }
}
