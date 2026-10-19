<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_reports extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function property()
    {
        return $this->hasOne(Property::class, 'id', 'property_id');
    }

    public function reason()
    {
        return $this->hasOne(report_reasons::class, 'id', 'reason_id');
    }
}
