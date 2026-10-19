<?php

namespace App\Traits;

use App\Services\ActiveRoleService;

trait HasRoleContext
{
    public static function bootHasRoleContext(): void
    {
        static::creating(function ($model) {
            if (empty($model->role_context)) {
                $model->role_context = request()->get('user_active_role') ?? 'user';
            }
        });
    }

    /**
     * Scope to filter records by role context.
     * Uses ActiveRoleService role if none given.
     */
    public function scopeForRole($query, ?string $role = null)
    {
        $role = $role ?? request()->get('user_active_role') ?? 'user';

        return $query->where($this->getTable().'.role_context', $role);
    }
}
