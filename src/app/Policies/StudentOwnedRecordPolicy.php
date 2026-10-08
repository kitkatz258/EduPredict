<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Skills, certifications, and work experience are readable and editable only by
 * the owning student. Staff roles get no access to the raw entries.
 */
abstract class StudentOwnedRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->ownsAStudentRecord($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->ownsAStudentRecord($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    private function ownsAStudentRecord(User $user): bool
    {
        return $user->isRole(UserRole::Student) && $user->student !== null;
    }

    private function owns(User $user, Model $record): bool
    {
        return $this->ownsAStudentRecord($user)
            && (int) $record->getAttribute('student_id') === $user->student->id;
    }
}
