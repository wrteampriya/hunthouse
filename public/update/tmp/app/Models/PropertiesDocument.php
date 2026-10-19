<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertiesDocument extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $table = 'properties_documents';

    protected $fillable = [
        'property_id',
        'name',
        'type',
        'created_at',
        'updated_at',
    ];

    public function getNameAttribute($name)
    {
        $propertyId = $this->property_id;
        $path = $name ? config('global.PROPERTY_DOCUMENT_PATH').$propertyId.'/'.$name : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }
}
