<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PredictionAttemptGrant;
use App\Models\User;

class PredictionAttemptGrantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, PredictionAttemptGrant $grant): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
