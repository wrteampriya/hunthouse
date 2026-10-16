<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentFollow extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = ['user_id', 'agent_id'];

    protected $casts = [
        'user_id' => 'integer',
        'agent_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    /**
     * Null for admin follows (agent_id=0) — admin is a users-table row, not a
     * customers row, so it can't be resolved through this relation.
     */
    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }
}
