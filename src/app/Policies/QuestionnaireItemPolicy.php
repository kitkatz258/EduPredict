<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QuestionnaireItem;
use App\Models\User;

class QuestionnaireItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, QuestionnaireItem $item): bool
    {
        return $user->isRole(UserRole::Administrator)
            || ($user->isRole(UserRole::Student) && $item->is_active);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function update(User $user, QuestionnaireItem $item): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
