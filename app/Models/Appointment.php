<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;
    protected $table = 'appointments';
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'start_at', 'end_at'];
    protected $fillable = [
        'is_admin_appointment',
        'admin_id',
        'agent_id',
        'user_id',
        'property_id',
        'meeting_type',
        'start_at',
        'end_at',
        'status',
        'is_auto_confirmed',
        'last_status_updated_by',
        'notes'
    ];

    protected $casts = [
        'is_admin_appointment' => 'boolean',
        'is_auto_confirmed' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }

    public function user()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function reschedules()
    {
        return $this->hasMany(AppointmentReschedule::class);
    }

    public function cancellations()
    {
        return $this->hasMany(AppointmentCancellation::class);
    }

    public function latestReschedule()
    {
        return $this->hasOne(AppointmentReschedule::class)->latest();
    }

    public function latestCancellation()
    {
        return $this->hasOne(AppointmentCancellation::class)->latest();
    }
}
