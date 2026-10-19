<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPayAsYouGoCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pay_as_you_go_id',
        'payment_transaction_id',
        'used',
    ];

    /**
     * Get the PayAsYouGo plan that owns this credit
     */
    public function pay_as_you_go()
    {
        return $this->belongsTo(PayAsYouGo::class, 'pay_as_you_go_id');
    }
}
