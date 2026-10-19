<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentVerificationFormValue extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'agent_verification_form_id',
        'value',
    ];

    protected $casts = [
        'agent_verification_form_id' => 'integer',
    ];

    public function agent_verification_form()
    {
        return $this->belongsTo(AgentVerificationForm::class, 'agent_verification_form_id');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedValueAttribute()
    {
        return HelperService::getTranslatedData($this, $this->value, 'value');
    }
}
