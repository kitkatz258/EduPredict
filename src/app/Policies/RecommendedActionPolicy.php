<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RecommendedAction;
use App\Models\User;

class RecommendedActionPolicy
{
    public function view(User $user, RecommendedAction $action): bool
    {
        if ($user->isRole(UserRole::Student)) {
            return false;
        }

        $action->loadMissing('prediction.student');
        $student = $action->prediction?->student;

        return $student !== null && $user->can('view', $student);
    }

    public function review(User $user, RecommendedAction $action): bool
    {
        if (! $user->isRole(UserRole::Faculty, UserRole::DepartmentHead)) {
            return false;
        }

        return $this->view($user, $action);
    }
}
