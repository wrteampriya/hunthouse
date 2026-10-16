<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentBookingPreference extends Model
{
    use HasFactory;
    protected $table = 'agent_booking_preferences';
    protected $fillable = ['is_admin_data','admin_id','agent_id','meeting_duration_minutes','lead_time_minutes','buffer_time_minutes','auto_confirm','cancel_reschedule_buffer_minutes','auto_cancel_after_minutes','auto_cancel_message','daily_booking_limit','availability_types','anti_spam_enabled','timezone'];

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }
}
