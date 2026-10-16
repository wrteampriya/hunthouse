<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReportUserByAgent extends Model
{
    use HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at'];
    protected $table = 'report_user_by_agents';
    protected $fillable = [
        'is_admin_data',
        'admin_id',
        'agent_id',
        'user_id',
        'reason',
        'status',
    ];
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }
    public function user()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function user_blocked(){
        return $this->hasMany(BlockedUserForAppointment::class, 'report_id', 'id');
    }


    public function getIsUserBlockedAttribute()
    {
        return $this->user_blocked->where(['user_id' => $this->user_id, 'status' => 'active'])->count() > 0;
    }
}
