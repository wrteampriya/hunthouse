<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectPlans extends Model
{
    use HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $fillable = array(
        'title',
        'document',
        'project_id',
        'created_at',
        'updated_at'
    );
    public function getDocumentAttribute($name)
    {
        $path = $name ? config('global.PROJECT_DOCUMENT_PATH').$name : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }
}
