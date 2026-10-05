<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PsocOccupation;
use App\Models\User;

class PsocOccupationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, PsocOccupation $occupation): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
