<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Prediction;
use App\Models\User;

class PredictionPolicy
{
    public function view(User $user, Prediction $prediction): bool
    {
        $prediction->loadMissing('student');

        return $prediction->student !== null && $user->can('view', $prediction->student);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Student) && $user->student !== null;
    }
}
