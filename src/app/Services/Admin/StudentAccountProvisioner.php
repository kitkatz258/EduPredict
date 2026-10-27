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
 * The initial password is the uppercase last name, hashed before storage, and never written to audit logs.
 */
class StudentAccountProvisioner
{
    /**
     * @return array{user: User, student: Student, temporary_password: string}
     */
    public function provision(InstitutionStudent $record, User $actor, ?string $email = null, ?string $ip = null): array
    {
        return DB::transaction(function () use ($record, $actor, $email, $ip): array {
            $locked = InstitutionStudent::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_registered || Student::query()->where('student_number', $locked->student_number)->exists()) {
                throw ValidationException::withMessages([
                    'studentNumber' => 'This student number already has a login. Duplicate accounts are not created.',
                ]);
            }

            $resolvedEmail = $this->resolveEmail($locked, $email);
            if (User::query()->whereRaw('LOWER(email) = ?', [$resolvedEmail])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'That email is already used by another account.',
                ]);
            }

            $temporary = StudentInitialPassword::fromLastName((string) $locked->last_name);
            $user = User::query()->create([
                'name' => trim($locked->first_name.' '.$locked->last_name),
                'email' => $resolvedEmail,
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
                'email' => $resolvedEmail,
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

    public function resolveEmail(InstitutionStudent $record, ?string $email = null): string
    {
        $candidate = Str::lower(trim((string) ($email ?: $record->email)));
        if ($candidate !== '') {
            if (! filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email' => 'A valid email is required so the student can reset a forgotten password.',
                ]);
            }

            return $candidate;
        }

        $local = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim((string) $record->student_number)));
        $local = trim($local, '-');
        if ($local === '') {
            throw ValidationException::withMessages([
                'email' => 'A valid email is required so the student can reset a forgotten password.',
            ]);
        }

        $domain = (string) config('edupredict.provisioning.placeholder_email_domain', 'students.edupredict.test');

        return $local.'@'.$domain;
    }
}
