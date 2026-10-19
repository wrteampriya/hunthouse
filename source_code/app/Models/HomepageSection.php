<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'section_type',
        'is_active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Boot function to set the sort order.
     */
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->sort_order = static::max('sort_order') + 1;
        });
    }

    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }
}
