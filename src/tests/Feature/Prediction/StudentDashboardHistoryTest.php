<?php

declare(strict_types=1);

namespace Tests\Feature\Prediction;

use App\Enums\UserRole;
use App\Livewire\Student\RequestPrediction;
use App\Livewire\Tables\PredictionHistoryTable;
use App\Models\GradeReport;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\StudentSkill;
use App\Models\SubjectGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentDashboardHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_results_and_career_urls_open_the_dashboard(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student->user)->get('/student/results')->assertRedirect('/student/dashboard');
        $this->actingAs($student->user)->get('/student/career-matches')->assertRedirect('/student/dashboard#career-matches');

        $this->actingAs(User::factory()->role(UserRole::DepartmentHead)->create())
            ->get('/student/results')
            ->assertForbidden();
    }

    public function test_dashboard_is_the_results_overview_with_careers_actions_and_model_disclosure(): void
    {
        $student = $this->readyStudent();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors()
            ->assertRedirect(route('student.dashboard'));

        $prediction = Prediction::query()->sole();

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(number_format((float) $prediction->employability_score, 0))
            ->assertSee('Academic wellness')
            ->assertSee('Why these scores?')
            ->assertSee('Program-shift indicator')
            ->assertSee('id="career-matches"', false)
            ->assertSee('Career matches')
            ->assertSee('Update Assessment')
            ->assertSee('Update Grades')
            ->assertSee('View Career Details')
            ->assertSee('Request new prediction')
            ->assertSee('placeholder-heuristic-v0')
            ->assertSee('not a trained or validated machine-learning model')
            ->assertSee('not a validated probability')
            ->assertDontSee('Estimated dropout probability')
            ->assertDontSee('Retake');
    }

    public function test_dashboard_without_a_prediction_shows_an_empty_state_and_next_steps(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('No prediction yet')
            ->assertSee('No career matches yet')
            ->assertSee('Still needed')
            ->assertSee('Update Assessment');
    }

    public function test_history_view_shows_the_saved_snapshot_and_ignores_later_changes(): void
    {
        $student = $this->readyStudent();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $prediction = Prediction::query()->sole();
        $questionnaire = $prediction->assessment_snapshot['questionnaire'];
        $this->assertTrue($questionnaire['submitted']);
        $this->assertSame('draft-v1', $questionnaire['definition_version']);
        $this->assertSame('I review my notes after every class.', $questionnaire['answers'][0]['text']);
        $this->assertSame(4, $questionnaire['answers'][0]['value']);

        QuestionnaireItem::query()->update(['text' => 'Changed later wording.']);
        $student->skills()->update(['name' => 'Changed Skill']);

        $this->actingAs($student->user)
            ->get(route('student.history'))
            ->assertOk()
            ->assertSee('Date and time')
            ->assertSee('Status')
            ->assertSee('Latest')
            ->assertSee('View')
            ->assertDontSee('Partial snapshot');

        Livewire::actingAs($student->user)
            ->test(PredictionHistoryTable::class, ['studentId' => $student->id])
            ->call('openView', $prediction->id)
            ->assertSee('Saved attempt')
            ->assertSee('read-only')
            ->assertSee('I review my notes after every class.')
            ->assertDontSee('Changed later wording.')
            ->assertSee('Laravel')
            ->assertDontSee('Changed Skill')
            ->assertSee('CCS 101')
            ->assertSee('#'.$prediction->id)
            ->assertSee('placeholder-heuristic-v0')
            ->assertDontSee('Not available for this attempt')
            ->call('closeView')
            ->assertDontSee('Saved attempt');
    }

    public function test_old_attempts_without_a_snapshot_say_not_available_instead_of_using_current_data(): void
    {
        $student = $this->readyStudent();
        $old = Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'created_at' => now()->subMonth(),
            'assessment_snapshot' => null,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.history'))
            ->assertOk()
            ->assertSee('Partial snapshot');

        Livewire::actingAs($student->user)
            ->test(PredictionHistoryTable::class, ['studentId' => $student->id])
            ->call('openView', $old->id)
            ->assertSee('Not available for this attempt')
            ->assertDontSee('Laravel')
            ->assertDontSee('I review my notes after every class.')
            ->assertDontSee('CCS 101');
    }

    public function test_only_the_owning_student_can_open_a_saved_attempt(): void
    {
        $student = $this->readyStudent();
        $prediction = Prediction::factory()->create(['student_id' => $student->id, 'requested_by' => $student->user_id]);
        $other = Student::factory()->create();
        $head = User::factory()->departmentHead($student->program->department)->create();

        Livewire::actingAs($other->user)
            ->test(PredictionHistoryTable::class, ['studentId' => $other->id])
            ->call('openView', $prediction->id)
            ->assertNotFound();

        Livewire::actingAs($head)
            ->test(PredictionHistoryTable::class, ['studentId' => $student->id])
            ->assertOk()
            ->assertDontSeeHtml('wire:click="openView(')
            ->call('openView', $prediction->id)
            ->assertForbidden();
    }

    private function readyStudent(): Student
    {
        $program = Program::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Student]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'year_level' => 3,
            'semesters_completed' => 2,
        ]);

        foreach (['First', 'Second'] as $index => $semester) {
            $report = GradeReport::factory()->create([
                'student_id' => $student->id,
                'school_year' => '2024-2025',
                'semester' => $semester,
                'status' => 'confirmed',
                'confirmed_at' => now(),
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

        SocioeconomicProfile::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        SkillsExperience::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'Laravel']);

        $item = QuestionnaireItem::factory()->create([
            'definition_version' => 'draft-v1',
            'text' => 'I review my notes after every class.',
        ]);
        $response = QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'definition_version' => 'draft-v1',
            'submitted_at' => now(),
            'construct_scores' => ['study_habits' => 75],
        ]);
        $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 4]);

        return $student;
    }
}
