<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use App\Traits\HasRoleContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favourite extends Model
{
    use HasAppTimezone, HasFactory, HasRoleContext;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = ['user_id', 'property_id', 'role_context'];

    protected $casts = [
        'user_id' => 'integer',
        'property_id' => 'integer',
    ];
}
