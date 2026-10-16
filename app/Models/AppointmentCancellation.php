<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentCancellation extends Model
{
    use HasFactory;
    protected $table = 'appointment_cancellations';
    protected $fillable = ['appointment_id', 'reason', 'cancelled_by'];
}
