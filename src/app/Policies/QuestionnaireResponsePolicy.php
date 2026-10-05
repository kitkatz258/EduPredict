<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QuestionnaireResponse;
use App\Models\User;

class QuestionnaireResponsePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Student) && $user->student !== null;
    }

    public function view(User $user, QuestionnaireResponse $response): bool
    {
        return $this->owns($user, $response);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Student) && $user->student !== null;
    }

    public function update(User $user, QuestionnaireResponse $response): bool
    {
        return $this->owns($user, $response) && $response->submitted_at === null;
    }

    private function owns(User $user, QuestionnaireResponse $response): bool
    {
        return $user->isRole(UserRole::Student)
            && $response->student?->user_id === $user->id;
    }
}
