<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifyCustomer extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $with = ['user', 'verify_customer_values'];

    protected $fillable = [
        'user_id',
        'status',
    ];

    /**
     * Get the user that owns the VerifyCustomer
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    /**
     * Get all of the Verify Form Values for the VerifyCustomer
     */
    public function verify_customer_values()
    {
        return $this->hasMany(VerifyCustomerValue::class, 'verify_customer_id');
    }

    // relation to reject reason if exist
    public function rejectReason()
    {
        return $this->hasOne(RejectReason::class, 'verify_customer_id');
    }
}
