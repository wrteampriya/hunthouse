<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyImages extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'property_images';

    protected $fillable = [
        'propertys_id',
        'image',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
    ];

    public function getImageAttribute($image)
    {
        $propertyId = $this->propertys_id;
        $path = $image ? config('global.PROPERTY_GALLERY_IMG_PATH').$propertyId.'/'.$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }
}
