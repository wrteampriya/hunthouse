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
        'app_title',
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

    /**
     * Admin-curated picks for a curated_properties_section row.
     */
    public function properties()
    {
        return $this->belongsToMany(Property::class, 'homepage_section_properties', 'homepage_section_id', 'property_id')
            ->withPivot('sort_order')
            ->orderBy('homepage_section_properties.sort_order');
    }

    /**
     * Admin-curated picks for a curated_projects_section row.
     */
    public function projects()
    {
        return $this->belongsToMany(Projects::class, 'homepage_section_projects', 'homepage_section_id', 'project_id')
            ->withPivot('sort_order')
            ->orderBy('homepage_section_projects.sort_order');
    }

    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }

    public function getTranslatedAppTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->app_title, 'app_title');
    }
}
