<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AccountDeletionRequest;
use App\Models\User;

class AccountDeletionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, AccountDeletionRequest $request): bool
    {
        return $user->isRole(UserRole::Administrator) || $user->id === $request->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Student) && $user->student !== null;
    }

    public function process(User $user, AccountDeletionRequest $request): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
