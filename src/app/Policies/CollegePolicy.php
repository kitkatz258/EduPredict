<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\User;

class CollegePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, College $college): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function update(User $user, College $college): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
