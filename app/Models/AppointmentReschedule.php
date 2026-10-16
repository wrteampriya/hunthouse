<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentReschedule extends Model
{
    use HasFactory;
    protected $table = 'appointment_reschedules';
    protected $fillable = ['appointment_id', 'old_start_at', 'old_end_at', 'new_start_at', 'new_end_at', 'reason', 'rescheduled_by'];
}
