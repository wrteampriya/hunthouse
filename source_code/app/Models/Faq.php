<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'question',
        'answer',
        'user_type',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Translations relationship
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedQuestionAttribute()
    {
        return HelperService::getTranslatedData($this, $this->question, 'question');
    }

    public function getTranslatedAnswerAttribute()
    {
        return HelperService::getTranslatedData($this, $this->answer, 'answer');
    }
}
