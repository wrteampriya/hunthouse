<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'type',
        'image',
        'web_image',
        'sequence',
        'category_id',
        'propertys_id',
        'show_property_details',
        'link',
        'default_data',
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(static function ($slider) {
            if (collect($slider)->isNotEmpty()) {
                // before delete() method call this

                // Delete Image
                if ($slider->getRawOriginal('image') != '') {
                    $rawImage = $slider->getRawOriginal('image');
                    $path = config('global.SLIDER_IMG_PATH');
                    FileService::delete($path, $rawImage);
                }

                // Delete Web Image
                if ($slider->getRawOriginal('web_image') != '') {
                    $rawWebImage = $slider->getRawOriginal('web_image');
                    $path = config('global.SLIDER_IMG_PATH');
                    FileService::delete($path, $rawWebImage);
                }
            }
        });
    }

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'type' => 'string',
        'sequence' => 'integer',
    ];

    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'category_id')->select('id', 'category')->where('status', 1);
    }

    public function property()
    {
        return $this->hasOne(Property::class, 'id', 'propertys_id')->where(['status' => 1, 'request_status' => 'approved']);
    }

    public function getImageAttribute($image)
    {
        return ! empty($image) ? FileService::getFileUrl(config('global.SLIDER_IMG_PATH').$image) : url('assets/images/logo/slider-default.png');
    }

    public function getWebImageAttribute($webImage)
    {
        return ! empty($webImage) ? FileService::getFileUrl(config('global.SLIDER_IMG_PATH').$webImage) : url('assets/images/logo/slider-default.png');
    }

    public function getTypeAttribute($value)
    {
        switch ($value) {
            case '1':
                return trans('Only Image');
                break;
            case '2':
                return trans('Category');
                break;
            case '3':
                return trans('Property');
                break;
            case '4':
                return trans('Other Link');
                break;
            default:
                return trans('Invalid');
                break;
        }
    }
}
