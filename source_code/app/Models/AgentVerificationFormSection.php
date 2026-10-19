<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentVerificationFormSection extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'name',
        'form_type',
        'sequence',
        'status',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function agent_verification_forms()
    {
        return $this->hasMany(AgentVerificationForm::class, 'agent_verification_form_section_id', 'id');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslatedNameAttribute()
    {
        return HelperService::getTranslatedData($this, $this->name, 'name');
    }
}
