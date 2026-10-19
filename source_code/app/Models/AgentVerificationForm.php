<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentVerificationForm extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'agent_verification_form_section_id',
        'name',
        'field_type',
        'sequence',
        'status',
    ];

    protected $casts = [
        'agent_verification_form_section_id' => 'integer',
        'sequence' => 'integer',
    ];

    public function agent_verification_form_section()
    {
        return $this->belongsTo(AgentVerificationFormSection::class, 'agent_verification_form_section_id');
    }

    public function form_fields_values()
    {
        return $this->hasMany(AgentVerificationFormValue::class, 'agent_verification_form_id', 'id');
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
