<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'form_type',
        'status',
    ];

    protected $casts = [
        'customer_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function values()
    {
        return $this->hasMany(AgentVerificationValue::class);
    }

    // relation to reject reason if exist
    public function rejectReason()
    {
        return $this->hasOne(RejectReason::class, 'agent_verification_id');
    }
}
