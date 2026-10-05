<?php

declare(strict_types=1);

namespace Tests\Feature\Prediction;

use App\Enums\UserRole;
use App\Livewire\NotificationBell;
use App\Livewire\Student\RequestPrediction;
use App\Livewire\Tables\PredictionHistoryTable;
use App\Livewire\Tables\ScopedStudentsTable;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\GradeReport;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Notifications\AdviseePredictionReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PredictionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_incomplete_profile_cannot_request_a_prediction(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasErrors('request');

        $this->assertSame(0, Prediction::query()->count());
        $this->actingAs($student->user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Still needed')
            ->assertSee('These results are estimates, not guarantees');
    }

    public function test_a_request_inserts_history_notifies_the_adviser_and_respects_cooldown(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $this->completeProfile($student, 'below_10k');
        $start = now()->copy();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors()
            ->assertRedirect(route('student.results'));

        $first = Prediction::query()->sole();
        $this->assertSame('placeholder-heuristic-v0', $first->model_version);
        $this->assertSame('none', $first->program_shift_flag);
        $this->assertSame('normal', $first->confidence);
        $this->assertArrayHasKey('employability', $first->factors);
        $this->assertArrayHasKey('dropout', $first->factors);
        $this->assertArrayNotHasKey('student_number', $first->feature_snapshot);
        $this->assertArrayNotHasKey('email', $first->feature_snapshot);
        $this->assertSame('below_10k', $first->feature_snapshot['income_bracket']);

        $requested = AuditLog::query()->where('action', 'prediction_requested')->sole();
        $this->assertSame($first->id, $requested->subject_id);
        $this->assertSame('placeholder-heuristic-v0', $requested->meta['model_version']);
        $this->assertArrayNotHasKey('income_bracket', $requested->meta);
        $this->assertArrayNotHasKey('email', $requested->meta);

        $note = $student->adviser->notifications()->first();
        $this->assertNotNull($note);
        $this->assertSame(AdviseePredictionReady::class, $note->type);
        $this->assertStringContainsString('requested a new prediction', $note->data['message']);
        $this->assertSame($student->id, $note->data['student_id']);
        $this->assertArrayNotHasKey('income_bracket', $note->data);
        $this->assertSame(0, User::query()->where('email', 'other-faculty@edupredict.test')->first()?->notifications()->count() ?? 0);

        $this->actingAs($student->user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('placeholder-heuristic-v0')
            ->assertSee('These results are estimates, not guarantees')
            ->assertSee('What this means')
            ->assertDontSee('below_10k');

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(number_format((float) $first->employability_score, 0));

        $this->travelTo($start->copy()->addHour());

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasErrors('request');

        $this->assertSame(1, Prediction::query()->count());

        $this->travelTo($start->copy()->addHours(25));

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame(2, Prediction::query()->count());
        $this->assertEquals($first->employability_score, $first->fresh()->employability_score);
        $this->assertSame(2, $student->adviser->notifications()->count());
    }

    public function test_limited_history_is_stored_as_lower_confidence(): void
    {
        $student = $this->makeStudent(semesters: 1);
        $this->completeProfile($student, '20k_40k');

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame('low', Prediction::query()->sole()->confidence);
        $this->actingAs($student->user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Lower confidence');
    }

    public function test_a_student_without_an_adviser_still_gets_a_prediction(): void
    {
        $student = $this->makeStudent(semesters: 2, withAdviser: false);
        $this->completeProfile($student, '20k_40k');

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame(1, Prediction::query()->count());
        $this->assertSame(0, $student->user->notifications()->count());
    }

    public function test_prediction_history_stays_inside_each_role_scope(): void
    {
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $faculty = User::factory()->faculty($program)->create();
        $otherFaculty = User::factory()->faculty($program)->create();
        $head = User::factory()->departmentHead($program)->create();
        $dean = User::factory()->dean($program->college)->create();
        $otherDean = User::factory()->dean(College::factory()->create())->create();
        $admin = User::factory()->administrator()->create();

        $advisee = $this->makeStudent(program: $program, adviser: $faculty, number: 'HIST-10001');
        $outsider = $this->makeStudent(program: $otherProgram, adviser: $otherFaculty, number: 'HIST-20002');
        $this->completeProfile($advisee, '20k_40k');
        $this->completeProfile($outsider, 'above_70k');

        Livewire::actingAs($advisee->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        Livewire::actingAs($outsider->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();

        $adviseeScore = number_format((float) $advisee->predictions()->first()->employability_score, 1);

        Livewire::actingAs($faculty)->test(RequestPrediction::class)->assertForbidden();
        Livewire::actingAs($advisee->user)->test(ScopedStudentsTable::class)->assertForbidden();

        $this->actingAs($faculty)->get(route('student.results'))->assertForbidden();
        $this->actingAs($faculty)->get(route('faculty.dashboard'))->assertOk()->assertSee('HIST-10001')->assertDontSee('HIST-20002');
        $this->actingAs($faculty)->get(route('students.show', $advisee))->assertOk()->assertSee($adviseeScore)->assertSee('placeholder-heuristic-v0')->assertDontSee('above_70k');
        $this->actingAs($faculty)->get(route('students.show', $outsider))->assertForbidden();

        Livewire::actingAs($faculty)->test(PredictionHistoryTable::class, ['studentId' => $outsider->id])->assertForbidden();
        Livewire::actingAs($advisee->user)->test(PredictionHistoryTable::class, ['studentId' => $outsider->id])->assertForbidden();
        Livewire::actingAs($faculty)->test(PredictionHistoryTable::class, ['studentId' => $advisee->id])->assertOk()->assertSee($adviseeScore);

        $this->actingAs($head)->get(route('department.dashboard'))->assertOk()->assertSee('HIST-10001')->assertDontSee('HIST-20002');
        $this->actingAs($head)->get(route('students.show', $advisee))->assertOk()->assertSee('History');
        $this->actingAs($head)->get(route('students.show', $outsider))->assertForbidden();

        $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk()->assertSee('HIST-10001')->assertDontSee('HIST-20002');
        $this->actingAs($dean)->get(route('students.show', $advisee))->assertOk();
        $this->actingAs($otherDean)->get(route('students.show', $advisee))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('HIST-10001')->assertSee('HIST-20002');
        $this->actingAs($admin)->get(route('students.show', $outsider))->assertOk()->assertDontSee('above_70k');

        $this->actingAs($outsider->user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertDontSee($adviseeScore);

        Livewire::actingAs($faculty)
            ->test(NotificationBell::class)
            ->assertSee('requested a new prediction')
            ->call('openNotification', $faculty->notifications()->first()->id)
            ->assertRedirect(route('students.show', $advisee));

        $this->assertNotNull($faculty->notifications()->first()->read_at);

        Livewire::actingAs($otherFaculty)
            ->test(NotificationBell::class)
            ->call('openNotification', $faculty->notifications()->first()->id)
            ->assertNotFound();
    }

    public function test_the_scoped_student_table_filters_by_the_latest_risk(): void
    {
        $program = Program::factory()->create();
        $faculty = User::factory()->faculty($program)->create();
        $high = $this->makeStudent(program: $program, adviser: $faculty, number: 'RISK-30001');
        $low = $this->makeStudent(program: $program, adviser: $faculty, number: 'RISK-30002');

        Prediction::factory()->create([
            'student_id' => $high->id,
            'requested_by' => $high->user_id,
            'dropout_risk' => 'high',
            'employability_score' => 41,
        ]);
        Prediction::factory()->create([
            'student_id' => $low->id,
            'requested_by' => $low->user_id,
            'dropout_risk' => 'low',
            'employability_score' => 88,
        ]);

        Livewire::actingAs($faculty)
            ->test(ScopedStudentsTable::class)
            ->set('filters.dropout_risk', 'high')
            ->assertSee('RISK-30001')
            ->assertDontSee('RISK-30002');
    }

    private function makeStudent(
        int $semesters = 0,
        bool $withAdviser = true,
        ?Program $program = null,
        ?User $adviser = null,
        ?string $number = null,
    ): Student {
        $program ??= Program::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Student]);
        $adviser ??= $withAdviser
            ? User::factory()->faculty($program)->create(['email' => 'adviser-'.$user->id.'@edupredict.test'])
            : null;

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'adviser_id' => $adviser?->id,
            'student_number' => $number ?? 'REQ-'.$user->id,
            'year_level' => 3,
            'semesters_completed' => $semesters,
        ]);
    }

    private function completeProfile(Student $student, string $income): void
    {
        foreach (['First', 'Second'] as $index => $semester) {
            if ($student->semesters_completed === 1 && $index === 1) {
                break;
            }

            $report = GradeReport::factory()->create([
                'student_id' => $student->id,
                'school_year' => '2024-2025',
                'semester' => $semester,
                'status' => 'confirmed',
            ]);
            SubjectGrade::factory()->create([
                'grade_report_id' => $report->id,
                'subject_code' => 'CCS 10'.($index + 1),
                'subject_name' => 'Sample subject',
                'units' => 3,
                'final_grade' => '2.00',
                'remarks' => 'PASSED',
                'is_failed' => false,
                'is_major_subject' => false,
            ]);
        }

        SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'household_income_bracket' => $income,
            'household_size' => '4',
            'scholarship_status' => 'none',
            'employment_status' => 'unemployed',
            'living_arrangement' => 'with_family',
            'has_internet' => 'yes',
            'has_device' => 'yes',
            'has_study_space' => 'yes',
            'is_draft' => false,
        ]);
        SkillsExperience::factory()->create([
            'student_id' => $student->id,
            'technical_skills' => ['SQL'],
            'certifications' => [],
            'internships' => [],
            'projects' => [],
            'work_experience' => [],
            'is_draft' => false,
        ]);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'construct_scores' => [
                'study_habits' => 60,
                'time_management' => 60,
                'motivation' => 60,
                'procrastination' => 40,
                'engagement' => 60,
            ],
        ]);
    }
}
