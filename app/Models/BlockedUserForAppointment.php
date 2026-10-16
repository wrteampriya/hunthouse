<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAppTimezone;

class BlockedUserForAppointment extends Model
{
    use HasFactory, HasAppTimezone;

    protected $table = 'blocked_users_for_appointments';
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'blocked_at', 'unblocked_at'];
    protected $fillable = [
        'user_id',
        'blocked_by_admin_id',
        'report_id',
        'block_type',
        'agent_id',
        'reason',
        'status',
        'blocked_at',
        'unblocked_at',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'unblocked_at' => 'datetime',
    ];

    /**
     * Get the user who is blocked
     */
    public function user()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    /**
     * Get the admin who blocked the user
     */
    public function blockedByAdmin()
    {
        return $this->belongsTo(User::class, 'blocked_by_admin_id');
    }

    /**
     * Get the report that led to this blocking
     */
    public function report()
    {
        return $this->belongsTo(ReportUserByAgent::class, 'report_id');
    }

    /**
     * Get the agent (for agent-specific blocks)
     */
    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }

    /**
     * Scope for active blocks
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for global blocks
     */
    public function scopeGlobal($query)
    {
        return $query->where('block_type', 'global');
    }

    /**
     * Scope for agent-specific blocks
     */
    public function scopeAgentSpecific($query)
    {
        return $query->where('block_type', 'agent_specific');
    }

    /**
     * Check if a user is blocked for appointments
     */
    public static function isUserBlocked($userId, $agentId = null)
    {
        $query = self::active()
            ->where('user_id', $userId);

        // Check for global blocks first
        $globalBlock = $query->clone()->global()->exists();
        if ($globalBlock) {
            return true;
        }

        // Check for agent-specific blocks
        if ($agentId) {
            return $query->clone()->agentSpecific()->where('agent_id', $agentId)->exists();
        }

        return false;
    }

    /**
     * Get blocking details for a user
     */
    public static function getBlockingDetails($userId, $agentId = null)
    {
        $query = self::active()
            ->where('user_id', $userId)
            ->with(['blockedByAdmin', 'report', 'agent']);

        // Check for global blocks first
        $globalBlock = $query->clone()->global()->first();
        if ($globalBlock) {
            return $globalBlock;
        }

        // Check for agent-specific blocks
        if ($agentId) {
            return $query->clone()->agentSpecific()->where('agent_id', $agentId)->first();
        }

        return null;
    }
}