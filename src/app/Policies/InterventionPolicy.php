<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Intervention;
use App\Models\User;

class InterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, Intervention $intervention): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function update(User $user, Intervention $intervention): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
