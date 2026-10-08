<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GradeReport;
use App\Models\User;

class GradeReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Student, UserRole::DepartmentHead, UserRole::Administrator);
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

    /**
     * Only drafts are edited in place; confirmed reports are replaced by a new version.
     */
    public function update(User $user, GradeReport $gradeReport): bool
    {
        return $this->owns($user, $gradeReport) && $gradeReport->isDraft();
    }

    public function delete(User $user, GradeReport $gradeReport): bool
    {
        return $this->update($user, $gradeReport);
    }

    public function confirm(User $user, GradeReport $gradeReport): bool
    {
        return $this->update($user, $gradeReport);
    }

    public function replace(User $user, GradeReport $gradeReport): bool
    {
        return $this->owns($user, $gradeReport) && $gradeReport->isCurrent();
    }

    private function owns(User $user, GradeReport $gradeReport): bool
    {
        return $user->isRole(UserRole::Student)
            && $gradeReport->student?->user_id === $user->id;
    }
}
