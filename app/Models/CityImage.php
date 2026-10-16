<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CityImage extends Model
{
    use HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $table ='city_images';

    protected $fillable = [
        'city',
        'image',
        'status'
    ];

    public function getImageAttribute($image)
    {
        $path = $image ? config('global.CITY_IMAGE_PATH') . $image : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }

    public function property(){
        return $this->hasMany(Property::class,'city','city');
    }
}
