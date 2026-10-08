<?php

declare(strict_types=1);

namespace Tests\Feature\Career;

use App\Enums\UserRole;
use App\Livewire\Tables\PsocOccupationsTable;
use App\Models\CareerMatch;
use App\Models\GradeReport;
use App\Models\Program;
use App\Models\PsocOccupation;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\StudentSkill;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Livewire\Student\RequestPrediction;
use Database\Seeders\PsocOccupationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CareerMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_starter_set_covers_the_required_groups(): void
    {
        $this->seed(PsocOccupationSeeder::class);

        $count = PsocOccupation::query()->count();
        $this->assertGreaterThanOrEqual(40, $count);
        $this->assertLessThanOrEqual(60, $count);

        foreach ([
            'Information and communications technology',
            'Business and administration',
            'Education',
            'Engineering and technology',
            'Health',
        ] as $group) {
            $this->assertTrue(PsocOccupation::query()->where('major_group', $group)->exists(), $group);
        }
    }

    public function test_a_student_sees_five_deterministic_template_matches_when_ai_is_off(): void
    {
        $this->occupations();
        $student = $this->student('Sam Visible', 'BSIS');

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame(5, CareerMatch::query()->count());
        $this->assertSame(['template'], CareerMatch::query()->distinct()->pluck('explanation_source')->all());

        $scores = CareerMatch::query()->orderByDesc('compatibility_score')->pluck('compatibility_score')->all();
        $this->actingAs($student->user)
            ->get(route('student.careers'))
            ->assertOk()
            ->assertSee('Software Developers')
            ->assertSee('87%')
            ->assertSee('programming')
            ->assertSee('Missing: algorithms')
            ->assertSee('AI unavailable, using standard text')
            ->assertSee('broad occupational categories, not job offers')
            ->assertDontSee('Accountants');

        $this->assertSame($scores, CareerMatch::query()->orderByDesc('compatibility_score')->pluck('compatibility_score')->all());
        $this->assertSame(5, CareerMatch::query()->count());

        $other = $this->student('Other Person');
        $this->actingAs($other->user)
            ->get(route('student.careers'))
            ->assertOk()
            ->assertDontSee('Software Developers');

        $this->actingAs(User::factory()->departmentHead()->create())
            ->get(route('student.careers'))
            ->assertForbidden();
    }

    public function test_ai_explanations_are_deidentified_and_invalid_codes_are_ignored(): void
    {
        $this->occupations();
        $student = $this->student('Unique Secretname', 'BSIS');

        config([
            'edupredict.ai.api_key' => 'test-key',
            'edupredict.ai.model' => 'test/free',
        ]);

        Http::fake(function ($request) use ($student) {
            $body = $request->body();
            $this->assertStringNotContainsString($student->user->name, $body);
            $this->assertStringNotContainsString($student->student_number, $body);
            $this->assertStringNotContainsString('Secretname', $body);
            $this->assertStringContainsString('2512', $body);

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'explanations' => [
                                ['psoc_code' => '2512', 'text' => 'Software work lines up with the program and the listed programming and sql tags. This is a broad category, not a job offer.'],
                                ['psoc_code' => '2514', 'text' => 'Application programming overlaps the programming tag. This is a broad category, not a job offer.'],
                                ['psoc_code' => '2521', 'text' => 'Database work overlaps the sql tag. This is a broad category, not a job offer.'],
                                ['psoc_code' => '2513', 'text' => 'Web development overlaps the programming tag. This is a broad category, not a job offer.'],
                                ['psoc_code' => '3512', 'text' => 'Support work is a broader technology path. This is a broad category, not a job offer.'],
                                ['psoc_code' => '9999', 'text' => 'This code is not one of the matches and must be ignored.'],
                            ],
                        ]),
                    ],
                ]],
            ]);
        });

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame(5, CareerMatch::query()->where('explanation_source', 'ai')->count());
        $this->assertSame(0, CareerMatch::query()->where('explanation', 'like', '%9999%')->count());

        $this->actingAs($student->user)
            ->get(route('student.careers'))
            ->assertOk()
            ->assertSee('AI-assisted explanations')
            ->assertSee('broad category, not a job offer')
            ->assertDontSee('AI unavailable, using standard text');

        Http::assertSentCount(1);
    }

    public function test_only_administrators_can_open_the_psoc_directory(): void
    {
        $this->seed(PsocOccupationSeeder::class);
        $admin = User::factory()->administrator()->create();
        $head = User::factory()->departmentHead()->create();

        $this->actingAs($admin)
            ->get(route('admin.psoc'))
            ->assertOk()
            ->assertSee('Starter set, verify against the official PSA PSOC 2012 before final submission.')
            ->assertSee('PSOC code');

        $this->actingAs($head)->get(route('admin.psoc'))->assertForbidden();

        Livewire::actingAs($head)->test(PsocOccupationsTable::class)->assertForbidden();
        Livewire::actingAs($admin)
            ->test(PsocOccupationsTable::class)
            ->set('search', 'Software')
            ->assertSee('2512')
            ->assertSee('Software Developers');
    }

    private function occupations(): void
    {
        $rows = [
            ['2512', 'Software Developers', ['programming', 'algorithms', 'sql'], ['BSIS', 'BSIT', 'BSCS']],
            ['2514', 'Applications Programmers', ['programming', 'debugging'], ['BSIS', 'BSIT']],
            ['2521', 'Database Designers and Administrators', ['sql', 'database'], ['BSIS']],
            ['2513', 'Web and Multimedia Developers', ['html', 'programming', 'web'], ['BSIS']],
            ['3512', 'ICT User Support Technicians', ['support', 'troubleshooting'], ['BSIS']],
            ['2411', 'Accountants', ['accounting', 'audit'], ['BSA']],
        ];

        foreach ($rows as [$code, $title, $skills, $programs]) {
            PsocOccupation::query()->create([
                'psoc_code' => $code,
                'title' => $title,
                'major_group' => 'Information and communications technology',
                'description' => 'Starter category.',
                'skill_tags' => $skills,
                'related_program_codes' => $programs,
            ]);
        }
    }

    private function student(string $name, ?string $programCode = null): Student
    {
        $program = Program::factory()->create([
            'code' => $programCode ?? ('P'.substr(md5($name), 0, 6)),
            'name' => 'Bachelor of Science in Information Systems',
        ]);
        $user = User::factory()->create([
            'name' => $name,
            'role' => UserRole::Student,
        ]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => 'CAR-'.substr(md5($name), 0, 8),
            'year_level' => 3,
            'semesters_completed' => 2,
        ]);

        foreach (['First', 'Second'] as $semester) {
            $report = GradeReport::factory()->create([
                'student_id' => $student->id,
                'school_year' => '2024-2025',
                'semester' => $semester,
                'status' => 'confirmed',
            ]);
            if ($semester === 'First') {
                SubjectGrade::factory()->create([
                    'grade_report_id' => $report->id,
                    'subject_code' => 'CCS 101',
                    'units' => 3,
                    'final_grade' => '1.50',
                    'remarks' => 'PASSED',
                    'is_failed' => false,
                    'is_major_subject' => false,
                ]);
            }
        }

        SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'is_draft' => false,
        ]);
        SkillsExperience::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'SQL']);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'Programming']);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'construct_scores' => [
                'study_habits' => 70,
                'time_management' => 70,
                'motivation' => 70,
                'procrastination' => 30,
                'engagement' => 70,
            ],
        ]);

        return $student->fresh();
    }
}
