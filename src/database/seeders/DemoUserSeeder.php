<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Consent;
use App\Models\Department;
use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\Academic\ClasStructureSynchronizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'Password123!';

    public function run(): void
    {
        $clas = College::query()->where('code', ClasStructureSynchronizer::COLLEGE_CODE)->firstOrFail();
        $computerStudies = Department::query()->where('code', 'CLAS-CS')->firstOrFail();
        $bsis = Program::query()->where('code', 'BSIS')->firstOrFail();
        $consentVersion = (string) config('edupredict.consent.current_version');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@edupredict.test'],
            [
                'name' => 'Ada Admin',
                'password' => Hash::make(self::PASSWORD),
                'role' => UserRole::Administrator,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $dean = User::query()->updateOrCreate(
            ['email' => 'dean@edupredict.test'],
            [
                'name' => 'Diego Dean',
                'password' => Hash::make(self::PASSWORD),
                'role' => UserRole::Dean,
                'college_id' => $clas->id,
                'department_id' => null,
                'program_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $head = User::query()->updateOrCreate(
            ['email' => 'depthead@edupredict.test'],
            [
                'name' => 'Hana Head',
                'password' => Hash::make(self::PASSWORD),
                'role' => UserRole::DepartmentHead,
                'college_id' => $clas->id,
                'department_id' => $computerStudies->id,
                'program_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $studentUser = User::query()->updateOrCreate(
            ['email' => 'student@edupredict.test'],
            [
                'name' => 'Sam Student',
                'password' => Hash::make(self::PASSWORD),
                'role' => UserRole::Student,
                'is_active' => true,
                'consented_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        InstitutionStudent::query()->updateOrCreate(
            ['student_number' => '2024-00001'],
            [
                'last_name' => 'Student',
                'first_name' => 'Sam',
                'program_id' => $bsis->id,
                'year_level' => 3,
                'email' => $studentUser->email,
                'is_registered' => true,
            ],
        );

        Student::query()->updateOrCreate(
            ['student_number' => '2024-00001'],
            [
                'user_id' => $studentUser->id,
                'program_id' => $bsis->id,
                'year_level' => 3,
                'enrollment_year' => 2023,
                'semesters_completed' => 4,
                'consent_version' => $consentVersion,
            ],
        );

        foreach ([$admin, $dean, $head, $studentUser] as $user) {
            Consent::query()->updateOrCreate(
                ['user_id' => $user->id, 'version' => $consentVersion],
                ['accepted_at' => now(), 'ip_address' => '127.0.0.1'],
            );
        }

        InstitutionStudent::query()->updateOrCreate(
            ['student_number' => '2024-88888'],
            [
                'last_name' => 'Applicant',
                'first_name' => 'Una',
                'program_id' => $bsis->id,
                'year_level' => 1,
                'email' => 'una.applicant@edupredict.test',
                'is_registered' => false,
            ],
        );
    }
}
