<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use App\Traits\HasRoleContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    use HasAppTimezone, HasFactory, HasRoleContext;

    protected $fillable = ['status', 'is_enable', 'for', 'role_context'];

    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'start_date', 'end_date'];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function property()
    {
        return $this->hasOne(Property::class, 'id', 'property_id');
    }

    public function project()
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }

    protected $casts = [
        'status' => 'integer',
        'is_enable' => 'boolean',
    ];
}
