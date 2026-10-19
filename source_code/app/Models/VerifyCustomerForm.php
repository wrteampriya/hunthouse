<?php

namespace App\Models;

use App\Services\HelperService;
use App\Traits\HasAppTimezone;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifyCustomerForm extends Model
{
    use HasAppTimezone, HasFactory, ManageTranslations;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $with = ['form_fields_values'];

    protected $fillable = [
        'name',
        'field_type',
        'rank',
    ];

    /**
     * Get all of the form_fields_values for the VerifyCustomerForm
     */
    public function form_fields_values()
    {
        return $this->hasMany(VerifyCustomerFormValue::class, 'verify_customer_form_id', 'id');
    }

    /**
     * Get all of the translations for the VerifyCustomerForm
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get translated name attribute
     */
    public function getTranslatedNameAttribute()
    {
        return HelperService::getTranslatedData($this, $this->name, 'name');
    }
}
