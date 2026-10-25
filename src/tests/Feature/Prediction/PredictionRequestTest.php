<?php

declare(strict_types=1);

namespace Tests\Feature\Prediction;

use App\Enums\UserRole;
use App\Livewire\NotificationBell;
use App\Livewire\Student\RequestPrediction;
use App\Livewire\Tables\PredictionHistoryTable;
use App\Livewire\Tables\ScopedStudentsTable;
use App\Models\AuditLog;
use App\Models\GradeReport;
use App\Models\Prediction;
use App\Models\PredictionAttemptGrant;
use App\Models\Program;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\StudentSkill;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Notifications\StudentPredictionReady;
use Carbon\Carbon;
use Database\Seeders\QuestionnaireItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Still needed')
            ->assertSee('These results are estimates, not guarantees');
    }

    public function test_a_request_inserts_history_notifies_in_scope_department_heads_and_respects_cooldown(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $program = $student->program;
        $departmentHead = User::factory()->departmentHead($program->department)->create();
        $programHead = User::factory()->departmentHead($program)->create();
        $otherHead = User::factory()->departmentHead(Program::factory()->create())->create();
        $inactiveHead = User::factory()->departmentHead($program->department)->inactive()->create();
        $legacyFaculty = User::factory()->legacyFaculty($program)->create();
        $student->forceFill(['adviser_id' => $legacyFaculty->id])->save();
        $dean = User::factory()->dean($program->college)->create();
        $this->completeProfile($student, 'below_10k');
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00', 'Asia/Manila'));

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors()
            ->assertRedirect(route('student.dashboard'));

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

        foreach ([$departmentHead, $programHead] as $reviewer) {
            $note = $reviewer->notifications()->sole();
            $this->assertSame(StudentPredictionReady::class, $note->type);
            $this->assertStringContainsString('requested a new prediction', $note->data['message']);
            $this->assertSame($student->id, $note->data['student_id']);
            $this->assertArrayNotHasKey('income_bracket', $note->data);
        }
        foreach ([$otherHead, $inactiveHead, $legacyFaculty, $dean] as $notReviewer) {
            $this->assertSame(0, $notReviewer->notifications()->count());
        }

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('placeholder-heuristic-v0')
            ->assertSee('These results are estimates, not guarantees')
            ->assertSee('What this means')
            ->assertDontSee('below_10k');

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(number_format((float) $first->employability_score, 0));

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame(2, Prediction::query()->count());

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasErrors('request');

        $this->assertSame(2, Prediction::query()->count());
        $this->assertEquals($first->employability_score, $first->fresh()->employability_score);
        $this->assertSame(2, $departmentHead->notifications()->count());
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
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Lower confidence');
    }

    public function test_student_can_request_with_no_grades_and_gets_lower_confidence(): void
    {
        $student = $this->makeStudent(semesters: 0);
        $this->completeProfile($student, 'prefer_not_to_say');
        $student->gradeReports()->delete();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $prediction = Prediction::query()->sole();
        $this->assertSame('low', $prediction->confidence);
        $this->assertNull($prediction->feature_snapshot['gwa']);
        $this->assertSame(0, $student->fresh()->semesters_completed);
    }

    public function test_a_student_with_no_department_head_still_gets_a_prediction(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $this->completeProfile($student, '20k_40k');

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame(1, Prediction::query()->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_prediction_history_stays_inside_each_role_scope(): void
    {
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $head = User::factory()->departmentHead($program->department)->create();
        $otherHead = User::factory()->departmentHead($otherProgram->department)->create();
        $dean = User::factory()->dean($program->college)->create();
        $admin = User::factory()->administrator()->create();

        $insider = $this->makeStudent(program: $program, number: 'HIST-10001');
        $outsider = $this->makeStudent(program: $otherProgram, number: 'HIST-20002');
        $this->completeProfile($insider, '20k_40k');
        $this->completeProfile($outsider, 'above_70k');

        Livewire::actingAs($insider->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        Livewire::actingAs($outsider->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();

        $insiderScore = number_format((float) $insider->predictions()->first()->employability_score, 1);

        Livewire::actingAs($head)->test(RequestPrediction::class)->assertForbidden();
        Livewire::actingAs($insider->user)->test(ScopedStudentsTable::class)->assertForbidden();

        $this->actingAs($head)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($head)->get(route('department.dashboard'))->assertOk()->assertDontSee('HIST-10001')->assertDontSee('HIST-20002');
        $this->actingAs($head)->get(route('department.students'))->assertOk()->assertSee('HIST-10001')->assertDontSee('HIST-20002');
        $this->actingAs($head)->get(route('students.show', $insider))->assertOk()->assertSee($insiderScore)->assertSee('placeholder-heuristic-v0')->assertSee('History')->assertDontSee('above_70k');
        $this->actingAs($head)->get(route('students.show', $outsider))->assertForbidden();

        Livewire::actingAs($head)->test(PredictionHistoryTable::class, ['studentId' => $outsider->id])->assertForbidden();
        Livewire::actingAs($insider->user)->test(PredictionHistoryTable::class, ['studentId' => $outsider->id])->assertForbidden();
        Livewire::actingAs($head)->test(PredictionHistoryTable::class, ['studentId' => $insider->id])->assertOk()->assertSee($insiderScore);

        $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk()->assertDontSee('HIST-10001')->assertDontSee('HIST-20002');
        $this->actingAs($dean)->get(route('students.show', $insider))->assertForbidden();
        Livewire::actingAs($dean)->test(PredictionHistoryTable::class, ['studentId' => $insider->id])->assertForbidden();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('HIST-10001')->assertDontSee('HIST-20002');
        $this->actingAs($admin)->get(route('students.show', $outsider))->assertOk()->assertDontSee('above_70k');

        $this->actingAs($outsider->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee($insiderScore);

        Livewire::actingAs($head)
            ->test(NotificationBell::class)
            ->assertSee('requested a new prediction')
            ->call('openNotification', $head->notifications()->first()->id)
            ->assertRedirect(route('department.students', ['student' => $insider->id]));

        $this->assertNotNull($head->notifications()->first()->read_at);

        Livewire::actingAs($otherHead)
            ->test(NotificationBell::class)
            ->call('openNotification', $head->notifications()->first()->id)
            ->assertNotFound();
    }

    public function test_the_scoped_student_table_filters_by_the_latest_risk(): void
    {
        $program = Program::factory()->create();
        $head = User::factory()->departmentHead($program)->create();
        $high = $this->makeStudent(program: $program, number: 'RISK-30001');
        $low = $this->makeStudent(program: $program, number: 'RISK-30002');

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

        Livewire::actingAs($head)
            ->test(ScopedStudentsTable::class)
            ->set('filters.dropout_risk', 'high')
            ->assertSee('RISK-30001')
            ->assertDontSee('RISK-30002');
    }

    public function test_a_questionnaire_without_grades_skills_or_household_data_does_not_invent_a_score(): void
    {
        $student = $this->makeStudent();
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'definition_version' => config('edupredict.questionnaire.current_version'),
            'construct_scores' => [],
        ]);

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasErrors('request');

        $this->assertSame(0, Prediction::query()->count());
        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Insufficient information for a reliable estimate')
            ->assertDontSee('Assessment workflow progress');
    }

    public function test_confirmed_grades_can_be_reused_without_reupload_or_skills(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $this->completeProfile($student, '20k_40k');
        $student->skillsExperience()->delete();
        $student->skills()->delete();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $prediction = Prediction::query()->sole();
        $this->assertNotNull($prediction->feature_snapshot['gwa']);
        $this->assertContains('skills_experience', $prediction->feature_snapshot['unavailable_inputs']);
        $this->assertSame(0, $prediction->feature_snapshot['technical_skill_count']);
    }

    public function test_a_legacy_submission_does_not_satisfy_the_current_instrument(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent(semesters: 2);
        $this->completeProfile($student, '20k_40k');
        QuestionnaireResponse::query()->where('student_id', $student->id)->update([
            'definition_version' => 'draft-v1',
        ]);

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasErrors('request');

        $this->assertSame(0, Prediction::query()->count());
    }

    public function test_two_predictions_are_allowed_per_philippine_day_and_midnight_resets_the_count(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $this->completeProfile($student, '20k_40k');
        $this->travelTo(Carbon::parse('2026-10-25 23:59:59', 'Asia/Manila'));

        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasErrors('request');
        $this->assertSame(2, Prediction::query()->count());

        $this->travelTo(Carbon::parse('2026-10-26 00:00:00', 'Asia/Manila'));

        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        $this->assertSame(3, Prediction::query()->count());
        $this->assertSame(
            2,
            $student->predictions()->where('created_at', '<', Carbon::parse('2026-10-26 00:00:00', 'Asia/Manila'))->count(),
        );
    }

    public function test_a_rejected_request_does_not_consume_a_daily_prediction(): void
    {
        $student = $this->makeStudent();
        $this->travelTo(Carbon::parse('2026-10-25 09:00:00', 'Asia/Manila'));

        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasErrors('request');
        $this->assertSame(0, Prediction::query()->count());

        $this->completeProfile($student, '20k_40k');
        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        $this->assertSame(2, Prediction::query()->count());
    }

    public function test_an_extra_attempt_grant_adds_to_the_daily_limit_without_changing_saved_predictions(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $this->completeProfile($student, '20k_40k');
        $this->travelTo(Carbon::parse('2026-10-25 12:00:00', 'Asia/Manila'));
        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        $before = Prediction::query()->orderBy('id')->get();

        PredictionAttemptGrant::query()->create([
            'student_id' => $student->id,
            'manila_date' => '2026-10-25',
            'extra_attempts' => 1,
            'reason' => 'Defense demonstration',
        ]);

        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();
        $this->assertSame(3, Prediction::query()->count());
        foreach ($before as $saved) {
            $fresh = $saved->fresh();
            $this->assertSame($saved->employability_score, $fresh->employability_score);
            $this->assertSame($saved->model_version, $fresh->model_version);
            $this->assertSame($saved->factors, $fresh->factors);
        }
    }

    public function test_a_new_prediction_without_a_household_profile_omits_those_factors(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $this->completeProfile($student, '20k_40k');
        $student->socioeconomicProfile()->delete();

        Livewire::actingAs($student->user)->test(RequestPrediction::class)->call('request')->assertHasNoErrors();

        $prediction = Prediction::query()->sole();
        $labels = collect($prediction->factors['employability'])->pluck('label')
            ->merge(collect($prediction->factors['dropout'])->pluck('label'));
        $this->assertFalse($labels->contains('Scholarship'));
        $this->assertFalse($labels->contains('Household income band'));
        $this->assertFalse($labels->contains('Study space'));
        $this->assertContains('scholarship_status', $prediction->feature_snapshot['unavailable_inputs']);
        $this->assertContains('income_bracket', $prediction->feature_snapshot['unavailable_inputs']);
        $this->assertNull($prediction->feature_snapshot['income_bracket']);
        $this->assertSame('placeholder-heuristic-v0', $prediction->model_version);
    }

    public function test_missing_consent_blocks_a_new_prediction(): void
    {
        $student = $this->makeStudent(semesters: 2);
        $student->user->forceFill(['consented_at' => null])->save();
        $this->completeProfile($student, '20k_40k');

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasErrors('request');

        $this->assertSame(0, Prediction::query()->count());
    }

    private function makeStudent(
        int $semesters = 0,
        ?Program $program = null,
        ?string $number = null,
    ): Student {
        $program ??= Program::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Student]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
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
        SkillsExperience::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'SQL']);
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
