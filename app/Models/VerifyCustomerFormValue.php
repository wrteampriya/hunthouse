<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VerifyCustomerFormValue extends Model
{
    use HasFactory, HasAppTimezone, ManageTranslations;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'verify_customer_form_id',
        'value',
        'created_at',
        'updated_at',
    ];


    /**
     * Get the Form Field that owns the VerifyCustomerFormValue
     *
     */
    public function verify_customer_form()
    {
        return $this->belongsTo(VerifyCustomerForm::class, 'verify_customer_form_id');
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
    public function getTranslatedValueAttribute()
    {
        return HelperService::getTranslatedData($this, $this->value, 'value');
    }
}
