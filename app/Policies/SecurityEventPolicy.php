<?php

namespace App\Policies;

use App\Models\SecurityEvent;
use App\Models\User;

class SecurityEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function view(User $user, SecurityEvent $event): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function update(User $user, SecurityEvent $event): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }
}
