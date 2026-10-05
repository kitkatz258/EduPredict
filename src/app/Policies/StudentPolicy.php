<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(
            UserRole::Faculty,
            UserRole::DepartmentHead,
            UserRole::Dean,
            UserRole::Administrator,
        );
    }

    public function view(User $user, Student $student): bool
    {
        return Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    public function assignAdviser(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
