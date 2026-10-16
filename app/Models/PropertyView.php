<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyView extends Model
{
    use HasFactory;
    protected $table = 'property_views';
    protected $fillable = [
        'user_id',
        'property_id',
        'date',
        'views'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
