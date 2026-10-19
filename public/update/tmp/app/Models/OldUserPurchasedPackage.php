<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldUserPurchasedPackage extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'old_user_purchased_packages';

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function modal()
    {
        return $this->morphTo();
    }

    public function package()
    {
        return $this->belongsTo(OldPackage::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'modal_id');
    }
}
