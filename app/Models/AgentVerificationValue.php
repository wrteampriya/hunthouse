<?php

namespace App\Models;

use App\Services\FileService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentVerificationValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_verification_id',
        'agent_verification_form_id',
        'value',
    ];

    public function agent_verification()
    {
        return $this->belongsTo(AgentVerification::class);
    }

    public function verify_form()
    {
        return $this->belongsTo(AgentVerificationForm::class, 'agent_verification_form_id');
    }

    public function getValueAttribute($value)
    {
        if ($this->relationLoaded('verify_form')) {
            if ($this->verify_form->field_type == 'file') {
                if (! empty($value)) {
                    $fileName = filter_var($value, FILTER_VALIDATE_URL) ? basename($value) : $value;

                    return FileService::getFileUrl(config('global.AGENT_VERIFICATION_DOC_PATH').$fileName);
                }

                return null;
            } elseif (in_array($this->verify_form->field_type, ['checkbox', 'chips'])) {
                $decodedValue = htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);

                return explode(',', $decodedValue);
            } else {
                return htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);
            }
        }

        return $value;
    }
}
