<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentExtraTimeSlot extends Model
{
    use HasFactory;
    protected $table = 'agent_extra_time_slots';
    protected $fillable = ['id', 'agent_id', 'date', 'start_time', 'end_time', 'reason', 'is_admin_data', 'admin_id'];

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'agent_id', 'agent_id');
    }
}
