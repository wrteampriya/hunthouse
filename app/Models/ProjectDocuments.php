<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectDocuments extends Model
{
    use HasFactory, HasAppTimezone;
    protected $table = 'project_documents';
    protected $fillable = ['name', 'project_id', 'type'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    public function getNameAttribute($name)
    {
        $path = $name ? config('global.PROJECT_DOCUMENT_PATH').$name : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }
}
