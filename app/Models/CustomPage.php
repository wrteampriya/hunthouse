<?php

namespace App\Models;

use App\Services\FileService;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomPage extends Model
{
    use HasFactory, ManageTranslations;

    protected $fillable = [
        'title',
        'slug_id',
        'content',
        'status',
    ];

    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }

    public function getTranslatedContentAttribute()
    {
        return HelperService::getTranslatedData($this, $this->content, 'content');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }
}
