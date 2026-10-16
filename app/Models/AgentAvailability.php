<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentAvailability extends Model
{
    use HasFactory;
    protected $table = 'agent_availabilities';
    protected $fillable = ['id', 'agent_id', 'day_of_week', 'start_time', 'end_time', 'is_active', 'is_admin_data', 'admin_id'];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'agent_id', 'agent_id');
    }

    public function getStartTimeAttribute($value)
    {
        return date('H:i', strtotime($value));
    }

    public function getEndTimeAttribute($value)
    {
        return date('H:i', strtotime($value));
    }
}
