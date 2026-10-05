<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, Program $program): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
