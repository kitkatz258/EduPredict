<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Student-level lists (names, numbers, individual results).
     * Deans are aggregate-only and are excluded on purpose.
     */
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::DepartmentHead, UserRole::Administrator);
    }

    public function view(User $user, Student $student): bool
    {
        return Student::query()->visibleTo($user)->whereKey($student->id)->exists();
    }

    /**
     * Aggregate dashboards that never name an individual student.
     */
    public function viewAggregates(User $user): bool
    {
        return $user->isRole(UserRole::DepartmentHead, UserRole::Dean, UserRole::Administrator);
    }
}
