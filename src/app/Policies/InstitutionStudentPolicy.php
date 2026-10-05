<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\InstitutionStudent;
use App\Models\User;

class InstitutionStudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function update(User $user, InstitutionStudent $institutionStudent): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
