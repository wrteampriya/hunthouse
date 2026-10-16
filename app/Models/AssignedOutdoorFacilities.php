<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssignedOutdoorFacilities extends Model
{
    use HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $table ='assigned_outdoor_facilities';

    protected $fillable = [
        'facility_id',
        'property_id',
        'distance',
    ];
    public function outdoorfacilities()
    {
        return $this->belongsTo(OutdoorFacilities::class, 'facility_id');
    }
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
