<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'name',
        'code',
        'symbol',
        'status',
    ];

    public function countries()
    {
        return $this->hasMany(Country::class);
    }
}
