<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\InstitutionStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentRegistrationService
{
    /**
     * @param  array{student_number: string, last_name: string, first_name: string, program_id: int, email: string, password: string, ip?: string|null}  $data
     */
    public function register(array $data): User
    {
        $record = InstitutionStudent::query()
            ->where('student_number', $data['student_number'])
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'student_number' => 'This student number is not on the institution list.',
            ]);
        }

        if ($record->is_registered) {
            throw ValidationException::withMessages([
                'student_number' => 'This student number is already registered.',
            ]);
        }

        $last = mb_strtolower(trim($record->last_name));
        $first = mb_strtolower(trim($record->first_name));

        if ($last !== mb_strtolower(trim($data['last_name'])) || $first !== mb_strtolower(trim($data['first_name']))) {
            throw ValidationException::withMessages([
                'last_name' => 'The name does not match the institution student list.',
            ]);
        }

        if ((int) $record->program_id !== (int) $data['program_id']) {
            throw ValidationException::withMessages([
                'program_id' => 'The program does not match the institution student list.',
            ]);
        }

        return DB::transaction(function () use ($data, $record): User {
            $user = User::query()->create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Student,
                'is_active' => true,
                'consented_at' => now(),
                'email_verified_at' => now(),
            ]);

            Student::query()->create([
                'user_id' => $user->id,
                'student_number' => $record->student_number,
                'program_id' => $record->program_id,
                'year_level' => $record->year_level,
                'enrollment_year' => (int) now('Asia/Manila')->year,
                'semesters_completed' => 0,
                'consent_version' => config('edupredict.consent.current_version'),
            ]);

            $record->update(['is_registered' => true]);

            Consent::query()->create([
                'user_id' => $user->id,
                'version' => config('edupredict.consent.current_version'),
                'accepted_at' => now(),
                'ip_address' => $data['ip'] ?? null,
            ]);

            AuditLog::query()->create([
                'user_id' => $user->id,
                'action' => 'student_registered',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'meta' => ['student_number' => $record->student_number],
                'ip' => $data['ip'] ?? null,
            ]);

            return $user;
        });
    }
}
