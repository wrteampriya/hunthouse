<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'categories';

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $fillable = [
        'category',
        'image',
        'status',
        'sequence',
        'parameter_types',
        'is_demo',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $hidden = [
        'updated_at',
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($category) {
            if (collect($category)->isNotEmpty()) {
                // before delete() method call this

                // Delete Title Image
                if ($category->getRawOriginal('image') != '') {
                    $path = config('global.CATEGORY_IMG_PATH');
                    $rawImage = $category->getRawOriginal('image');
                    FileService::delete($path, $rawImage);
                }

                /** Delete the properties associated data */
                // Delete Directly without modal boot events
                $properties = Property::where('category_id', $category->id)->get();
                if (collect($properties)->isNotEmpty()) {
                    foreach ($properties as $property) {
                        if (collect($property)->isNotEmpty()) {
                            $property->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
                $projects = Projects::where('category_id', $category->id)->get();
                if (collect($projects)->isNotEmpty()) {
                    foreach ($projects as $project) {
                        if (collect($project)->isNotEmpty()) {
                            $project->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
                $articles = Article::where('category_id', $category->id)->get();
                if (collect($articles)->isNotEmpty()) {
                    foreach ($articles as $article) {
                        if (collect($article)->isNotEmpty()) {
                            $article->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
                $sliders = Slider::where('category_id', $category->id)->get();
                if (collect($sliders)->isNotEmpty()) {
                    foreach ($sliders as $slider) {
                        if (collect($slider)->isNotEmpty()) {
                            $slider->delete(); // This will trigger the deleting and deleted events in modal
                        }
                    }
                }
            }
        });
    }

    public function getParametersAttribute()
    {
        $parameterTypes = explode(',', $this->parameter_types);
        if (! empty($parameterTypes)) {
            $parameters = parameter::whereIn('id', $parameterTypes)->with('translations')->get();
            $sortedParameters = $parameters->sortBy(function ($item) use ($parameterTypes) {
                return array_search($item->id, $parameterTypes);
            });

            return $sortedParameters;
        }

        return [];
    }

    public function parameter()
    {
        return $this->hasMany(parameter::class, 'id', 'parameter_types');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'category_id', 'id');
    }

    public function getImageAttribute($image)
    {
        $path = $image ? config('global.CATEGORY_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
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
        return HelperService::getTranslatedData($this, $this->category, 'category');
    }
}
