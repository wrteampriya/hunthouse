<?php

namespace App\Services;

class ActiveRoleService
{
    protected string $role = 'user';

    public function setRole(string $role): void
    {
        $this->role = in_array($role, ['user', 'agent']) ? $role : 'user';
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }
}
