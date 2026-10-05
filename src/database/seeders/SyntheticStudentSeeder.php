<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\GradeReport;
use App\Models\InstitutionStudent;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\Interventions\RecommendedActionBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SyntheticStudentSeeder extends Seeder
{
    public function run(): void
    {
        $programs = Program::query()->with('college')->get();
        $faculty = User::query()->where('email', 'faculty@edupredict.test')->firstOrFail();
        $bsis = Program::query()->where('code', 'BSIS')->firstOrFail();

        $otherFaculty = User::query()->updateOrCreate(
            ['email' => 'faculty.other@edupredict.test'],
            [
                'name' => 'Omar Other (synthetic faculty)',
                'password' => Hash::make(DemoUserSeeder::PASSWORD),
                'role' => UserRole::Faculty,
                'college_id' => $programs->firstWhere('code', 'BSBA')?->college_id,
                'program_id' => $programs->firstWhere('code', 'BSBA')?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $subjects = [
            ['CCS 106', 'Applications Development', 5, true],
            ['GEE 002', 'Living in the IT Era', 3, false],
            ['PATHFIT 4', 'Sports and Fitness', 2, false],
            ['PR 002', 'Quantitative Methods', 3, false],
            ['IS 106', 'IS Major Elective 1', 3, true],
        ];

        for ($i = 1; $i <= 60; $i++) {
            $program = $programs[$i % $programs->count()];
            $year = ($i % 4) + 1;
            $isBsis = $program->id === $bsis->id;
            $adviser = $isBsis ? $faculty : $otherFaculty;
            $number = sprintf('SYN-%04d', $i);

            $storyNames = [
                1 => ['Mara', 'Bautista'],
                2 => ['Nico', 'Reyes'],
                3 => ['Bea', 'Navarro'],
                4 => ['Carlo', 'Lim'],
                5 => ['Rico', 'Dela Cruz'],
                6 => ['Paolo', 'Cruz'],
                10 => ['Liza', 'Ramos'],
            ];
            $firstName = $storyNames[$i][0] ?? "Synthetic{$i}";
            $lastName = $storyNames[$i][1] ?? 'Student';

            $user = User::query()->updateOrCreate(
                ['email' => "synthetic{$i}@edupredict.test"],
                [
                    'name' => trim($firstName.' '.$lastName),
                    'password' => Hash::make(DemoUserSeeder::PASSWORD),
                    'role' => UserRole::Student,
                    'is_active' => true,
                    'consented_at' => now(),
                    'email_verified_at' => now(),
                ],
            );

            InstitutionStudent::query()->updateOrCreate(
                ['student_number' => $number],
                [
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'program_id' => $program->id,
                    'year_level' => $year,
                    'email' => $user->email,
                    'is_registered' => true,
                ],
            );

            $student = Student::query()->updateOrCreate(
                ['student_number' => $number],
                [
                    'user_id' => $user->id,
                    'program_id' => $program->id,
                    'year_level' => $year,
                    'adviser_id' => $adviser->id,
                    'enrollment_year' => 2027 - $year,
                    'semesters_completed' => max(0, ($year - 1) * 2),
                    'consent_version' => 'synthetic-v1',
                ],
            );

            $bucket = $i % 5;
            $risk = match ($bucket) {
                0 => 'high',
                1 => 'moderate',
                default => 'low',
            };
            $failed = $risk === 'high';
            $limited = $year === 1;

            $report = GradeReport::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'school_year' => '2024-2025',
                    'semester' => 'Second',
                ],
                [
                    'source' => 'manual',
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ],
            );

            foreach ($subjects as $offset => [$code, $name, $units, $major]) {
                $grade = $failed && $offset === 4 ? '5.00' : ($risk === 'moderate' && $offset === 0 ? '2.75' : '1.75');
                SubjectGrade::query()->updateOrCreate(
                    [
                        'grade_report_id' => $report->id,
                        'subject_code' => $code,
                    ],
                    [
                        'subject_name' => $name,
                        'units' => $units,
                        'midterm_grade' => $grade,
                        'final_exam_grade' => $grade,
                        'final_grade' => $grade,
                        'remarks' => $grade === '5.00' ? 'FAILED' : 'PASSED',
                        'is_failed' => $grade === '5.00',
                        'is_major_subject' => $major,
                        'needs_review' => false,
                    ],
                );
            }

            SocioeconomicProfile::query()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'household_income_bracket' => $risk === 'high' ? 'below_10k' : '20k_40k',
                    'household_size' => '4',
                    'scholarship_status' => $i % 3 === 0 ? 'partial' : 'none',
                    'employment_status' => 'unemployed',
                    'living_arrangement' => 'with_family',
                    'has_internet' => 'yes',
                    'has_device' => 'yes',
                    'has_study_space' => $risk === 'high' ? 'no' : 'yes',
                    'is_draft' => false,
                ],
            );

            SkillsExperience::query()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'technical_skills' => $program->code === 'BSIS' || $program->code === 'BSIT' || $program->code === 'BSCS'
                        ? ['PHP', 'SQL', 'HTML']
                        : ['communication', 'research'],
                    'certifications' => [],
                    'internships' => $year >= 3 ? [['title' => 'OJT', 'hours' => 200]] : [],
                    'projects' => [],
                    'work_experience' => [],
                    'is_draft' => false,
                ],
            );

            $prediction = Prediction::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'model_version' => 'placeholder-heuristic-v0',
                ],
                [
                    'requested_by' => $user->id,
                    'employability_score' => match ($risk) {
                        'high' => 48.0,
                        'moderate' => 62.0,
                        default => 81.0,
                    },
                    'dropout_probability' => match ($risk) {
                        'high' => 0.72,
                        'moderate' => 0.41,
                        default => 0.12,
                    },
                    'dropout_risk' => $risk,
                    'confidence' => $limited ? 'low' : 'normal',
                    'program_shift_flag' => match ($bucket) {
                        0 => 'disengagement',
                        1 => 'program_fit',
                        2 => 'mixed',
                        default => 'none',
                    },
                    'factors' => [
                        [
                            'feature' => 'gwa',
                            'label' => 'General weighted average',
                            'direction' => $risk === 'low' ? '+' : '-',
                            'magnitude' => 0.35,
                        ],
                        [
                            'feature' => 'failed_subjects',
                            'label' => 'Failed subjects',
                            'direction' => $failed ? '-' : '+',
                            'magnitude' => 0.25,
                        ],
                    ],
                    'created_at' => now()->subMonths((($i - 1) % 6) * 4)->startOfMonth(),
                    'feature_snapshot' => [
                        'gwa_band' => $risk === 'high' ? '2.75-3.00' : '1.75-2.00',
                        'failed_subjects' => $failed ? 1 : 0,
                        'year_level' => $year,
                        'synthetic' => true,
                    ],
                ],
            );

            app(RecommendedActionBuilder::class)->ensure($prediction, false);
        }
    }
}
