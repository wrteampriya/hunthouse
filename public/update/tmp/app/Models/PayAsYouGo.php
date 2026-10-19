<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayAsYouGo extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'type',
        'status',
        'ios_product_id',
    ];
}
