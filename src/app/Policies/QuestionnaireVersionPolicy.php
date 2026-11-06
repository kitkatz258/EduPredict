<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QuestionnaireVersion;
use App\Models\User;

class QuestionnaireVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, QuestionnaireVersion $version): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function update(User $user, QuestionnaireVersion $version): bool
    {
        return $user->isRole(UserRole::Administrator) && $version->isMutable();
    }

    public function publish(User $user, QuestionnaireVersion $version): bool
    {
        return $user->isRole(UserRole::Administrator) && $version->isMutable();
    }
}
