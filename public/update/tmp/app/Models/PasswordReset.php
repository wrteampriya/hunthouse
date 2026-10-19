<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $hidden = [
        'updated_at',
    ];

    protected $fillable = [
        'email',
        'token',
        'expires_at',
    ];
}
