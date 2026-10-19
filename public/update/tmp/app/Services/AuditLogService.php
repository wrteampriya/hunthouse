<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Write an audit log entry.
     * Silently skips if the audit_log_enabled setting is off.
     *
     * @param  string       $entityType   property | project
     * @param  int|null     $entityId
     * @param  string|null  $entityTitle  snapshot of the record name at time of action
     * @param  string       $action       created | updated | deleted | status_changed | approved | rejected
     * @param  string       $description  human-readable summary
     * @param  string       $source       admin_panel | api
     * @param  string|null  $actorType    admin | agent | user  (auto-detected from Auth if null)
     */
    public static function log(
        string $entityType,
        ?int $entityId,
        ?string $entityTitle,
        string $action,
        string $description,
        string $source = 'admin_panel',
        ?string $actorType = null
    ): void {
        try {
            $enabled = Setting::where('type', 'audit_log_enabled')->value('data');
            if (! $enabled) {
                return;
            }

            $user = Auth::user();
            if (! $user) {
                return;
            }

            // Auto-detect actor type if not provided
            if ($actorType === null) {
                if ($source === 'admin_panel') {
                    $actorType = 'admin';
                } elseif (request()->filled('user_active_role')) {
                    // The account may be agent-verified (is_agent = 1) but still have
                    // performed this specific action in "user" mode (X-Active-Role:
                    // user, translated to user_active_role by ActiveRoleMiddleware) —
                    // that per-request context reflects what the actor actually did,
                    // so it takes priority over the account's blanket is_agent flag.
                    $actorType = request()->get('user_active_role');
                } elseif (isset($user->is_agent) && $user->is_agent) {
                    $actorType = 'agent';
                } else {
                    $actorType = $user->role ?? 'user';
                }
            }

            AuditLog::create([
                'actor_type'   => $actorType,
                'actor_id'     => $user->id,
                'actor_name'   => $user->name,
                'source'       => $source,
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'entity_title' => $entityTitle,
                'action'       => $action,
                'description'  => $description,
            ]);
        } catch (\Throwable $e) {
            // Never let a log failure break the main operation
        }
    }
}
