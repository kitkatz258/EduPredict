<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GradeReport;
use App\Models\User;

class GradeReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Student, UserRole::Faculty, UserRole::DepartmentHead, UserRole::Dean, UserRole::Administrator);
    }

    public function view(User $user, GradeReport $gradeReport): bool
    {
        $gradeReport->loadMissing('student');

        return $gradeReport->student !== null
            && $user->can('view', $gradeReport->student);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::Student) && $user->student !== null;
    }

    public function update(User $user, GradeReport $gradeReport): bool
    {
        return $user->isRole(UserRole::Student)
            && $gradeReport->student?->user_id === $user->id;
    }

    public function delete(User $user, GradeReport $gradeReport): bool
    {
        return $this->update($user, $gradeReport);
    }

    public function confirm(User $user, GradeReport $gradeReport): bool
    {
        return $this->update($user, $gradeReport);
    }
}
