<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InstitutionStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues a student login from an eligible-student row.
 * Temporary passwords are random, hashed, shown once, and never written to audit logs.
 */
class StudentAccountProvisioner
{
    /**
     * @return array{user: User, student: Student, temporary_password: string}
     */
    public function provision(InstitutionStudent $record, User $actor, string $email, ?string $ip = null): array
    {
        $email = Str::lower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'A valid email is required so the student can reset a forgotten password.',
            ]);
        }

        return DB::transaction(function () use ($record, $actor, $email, $ip): array {
            $locked = InstitutionStudent::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_registered || Student::query()->where('student_number', $locked->student_number)->exists()) {
                throw ValidationException::withMessages([
                    'studentNumber' => 'This student number already has a login. Duplicate accounts are not created.',
                ]);
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'That email is already used by another account.',
                ]);
            }

            $temporary = Str::password(12);
            $user = User::query()->create([
                'name' => trim($locked->first_name.' '.$locked->last_name),
                'email' => $email,
                'password' => $temporary,
                'role' => UserRole::Student,
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]);

            $student = Student::query()->create([
                'user_id' => $user->id,
                'student_number' => $locked->student_number,
                'program_id' => $locked->program_id,
                'year_level' => $locked->year_level,
                'enrollment_year' => (int) now('Asia/Manila')->year,
                'semesters_completed' => 0,
                'consent_version' => config('edupredict.consent.current_version'),
            ]);

            $locked->update([
                'is_registered' => true,
                'email' => $email,
            ]);

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'student_account_provisioned',
                'subject_type' => InstitutionStudent::class,
                'subject_id' => $locked->id,
                'meta' => [
                    'student_number' => $locked->student_number,
                    'user_id' => $user->id,
                ],
                'ip' => $ip,
            ]);

            return ['user' => $user, 'student' => $student, 'temporary_password' => $temporary];
        });
    }
}
