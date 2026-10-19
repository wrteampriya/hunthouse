<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifyCustomerValue extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $with = ['verify_form'];

    protected $fillable = [
        'verify_customer_id',
        'verify_customer_form_id',
        'value',
        'created_at',
        'updated_at',
    ];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($model) {
            if (collect($model)->isNotEmpty()) {
                if (! empty($model->value) && $model->verify_form->field_type == 'file') {
                    FileService::delete(config('global.AGENT_VERIFICATION_DOC_PATH'), $model->value);
                }
            }
        });

    }

    /**
     * Get Data from Verify Customer
     */
    public function verifyCustomer()
    {
        return $this->belongsTo(VerifyCustomer::class, 'verify_customer_id');
    }

    /**
     * Get the Verify Form that owns the VerifyCustomerValue
     */
    public function verify_form()
    {
        return $this->belongsTo(VerifyCustomerForm::class, 'verify_customer_form_id');
    }

    public function getValueAttribute($value)
    {
        if ($this->relationLoaded('verify_form')) {
            if ($this->verify_form->field_type == 'file') {
                if (! empty($value)) {
                    // Check if value is already a URL
                    if (filter_var($value, FILTER_VALIDATE_URL)) {
                        // Extract the file name from the URL
                        $fileName = basename($value);

                        // Regenerate the URL
                        return url('').config('global.IMG_PATH').config('global.AGENT_VERIFICATION_DOC_PATH').$fileName;
                    } else {
                        return FileService::getFileUrl(config('global.AGENT_VERIFICATION_DOC_PATH').$value);
                    }
                } else {
                    return null;
                }
            } elseif ($this->verify_form->field_type == 'checkbox') {
                $decodedValue = htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);

                return explode(',', $decodedValue);
            } else {
                return htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5);
            }
        }

        return $value;
    }
}
