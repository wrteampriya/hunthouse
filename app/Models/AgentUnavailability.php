<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentUnavailability extends Model
{
    use HasFactory;
    protected $table = 'agent_unavailabilities';
    protected $fillable = ['id', 'agent_id', 'date', 'unavailability_type', 'start_time', 'end_time', 'reason', 'is_admin_data','admin_id'];
}
