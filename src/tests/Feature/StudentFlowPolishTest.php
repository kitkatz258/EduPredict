<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Student\AssessmentWizard;
use App\Livewire\Student\QuestionnaireForm;
use App\Livewire\Tables\GradeReportsTable;
use App\Livewire\Tables\PredictionHistoryTable;
use App\Models\GradeReport;
use App\Models\Prediction;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentFlowPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_and_create_account_share_one_fixed_width_card_with_a_toggle(): void
    {
        foreach (['/login' => 'login', '/register' => 'register'] as $url => $mode) {
            $page = $this->get($url)
                ->assertOk()
                ->assertSee('role="tablist"', false)
                ->assertSee('id="auth-tab-login"', false)
                ->assertSee('id="auth-tab-register"', false)
                ->assertSee('id="auth-panel-login"', false)
                ->assertSee('id="auth-panel-register"', false)
                ->assertSee('max-w-xl', false)
                ->assertDontSee('max-w-2xl', false)
                ->assertDontSee('New student?')
                ->assertDontSee('Already registered?')
                ->assertSee('Informed consent')
                ->assertSee('Forgot password?');

            $html = $page->getContent();
            $hidden = $mode === 'login' ? 'auth-panel-register' : 'auth-panel-login';
            $this->assertMatchesRegularExpression('/id="'.$hidden.'"[^>]*style="display: none"/s', $html);
        }
    }

    public function test_a_failed_registration_reopens_the_create_account_panel_even_from_the_sign_in_url(): void
    {
        $this->from('/login')
            ->post('/register', ['_auth_form' => 'register', 'student_number' => ''])
            ->assertRedirect('/login');

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('id="student_number-error"', $html);
        $this->assertMatchesRegularExpression('/id="auth-panel-login"[^>]*style="display: none"/s', $html);
        $this->assertStringNotContainsString('id="login-error"', $html);
    }

    public function test_student_number_sign_in_still_works_from_the_shared_card(): void
    {
        $student = Student::factory()->create(['student_number' => '2024-55501']);

        $this->post('/login', ['_auth_form' => 'login', 'login' => '2024-55501', 'password' => 'password'])
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student->user);
    }

    public function test_assessment_continue_and_back_follow_the_six_section_sequence(): void
    {
        $student = Student::factory()->create();
        $sequence = ['academic_behavior', 'socioeconomic', 'employability', 'skills', 'grades', 'review'];

        $wizard = Livewire::actingAs($student->user)->test(AssessmentWizard::class)->assertSet('step', 'academic_behavior');
        foreach (array_slice($sequence, 1) as $expected) {
            $wizard->call('next')->assertSet('step', $expected);
        }
        $wizard->call('next')->assertSet('step', 'review');
        foreach (array_reverse(array_slice($sequence, 0, -1)) as $expected) {
            $wizard->call('back')->assertSet('step', $expected);
        }
        $wizard->call('back')->assertSet('step', 'academic_behavior')
            ->call('goTo', 'nonsense')->assertSet('step', 'academic_behavior')
            ->dispatch('questionnaire-submitted')->assertSet('step', 'socioeconomic');

        Livewire::actingAs($student->user)
            ->withQueryParams(['step' => 'questionnaire'])
            ->test(AssessmentWizard::class)
            ->assertSet('step', 'academic_behavior');
    }

    public function test_submitting_the_questionnaire_moves_the_wizard_on(): void
    {
        $student = Student::factory()->create();
        $item = QuestionnaireItem::factory()->create(['definition_version' => 'draft-v1', 'construct' => 'study_habits']);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->set('answers.'.$item->id, 4)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('questionnaire-submitted');
    }

    public function test_assessment_uses_one_wizard_and_no_history_blocks(): void
    {
        $student = Student::factory()->create();
        QuestionnaireItem::factory()->create(['definition_version' => 'draft-v1']);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'definition_version' => 'draft-v1',
            'submitted_at' => now()->subWeek(),
            'construct_scores' => ['study_habits' => 50],
        ]);
        GradeReport::factory()->create(['student_id' => $student->id, 'status' => 'confirmed', 'confirmed_at' => now()]);

        $this->actingAs($student->user)
            ->get(route('student.assessment'))
            ->assertOk()
            ->assertSee('Step 1 of 6')
            ->assertSee('aria-label="Assessment sections"', false)
            ->assertSeeInOrder(['Academic Behavior', 'Socioeconomic Factors', 'Employability Assessment', 'Skills &amp; Experience', 'Grades', 'Review &amp; Run'], false)
            ->assertSee('Questionnaire submitted')
            ->assertDontSee('Assessment workflow progress')
            ->assertDontSee('aria-label="Assessment steps"', false)
            ->assertDontSee('Previous questionnaire submissions');

        $this->actingAs($student->user)
            ->get('/student/assessment?step=grades')
            ->assertOk()
            ->assertSee('Use latest confirmed grades')
            ->assertDontSee('Saved grade reports')
            ->assertDontSeeLivewire(GradeReportsTable::class);
    }

    public function test_grade_drafts_can_be_continued_or_deleted_only_by_their_owner(): void
    {
        $student = Student::factory()->create();
        $other = Student::factory()->create();
        $draft = GradeReport::factory()->create(['student_id' => $student->id, 'status' => 'draft', 'confirmed_at' => null]);
        $confirmed = GradeReport::factory()->create(['student_id' => $student->id, 'status' => 'confirmed', 'confirmed_at' => now()]);

        Livewire::actingAs($other->user)
            ->test(AssessmentWizard::class)
            ->call('deleteGradeDraft', $draft->id)
            ->assertNotFound();

        Livewire::actingAs($student->user)
            ->withQueryParams(['step' => 'grades'])
            ->test(AssessmentWizard::class)
            ->call('chooseGrades', 'update')
            ->assertSee('Terms on file')
            ->assertSee('Update term')
            ->call('deleteGradeDraft', $confirmed->id)
            ->assertForbidden();

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->call('deleteGradeDraft', $draft->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($draft);
        $this->assertModelExists($confirmed);
    }

    public function test_history_lists_confirmed_grade_versions_read_only(): void
    {
        $student = Student::factory()->create();
        $confirmed = GradeReport::factory()->create(['student_id' => $student->id, 'status' => 'confirmed', 'confirmed_at' => now(), 'school_year' => '2023-2024']);
        GradeReport::factory()->create(['student_id' => $student->id, 'status' => 'draft', 'confirmed_at' => null, 'school_year' => '2025-2026']);

        $this->actingAs($student->user)
            ->get(route('student.history'))
            ->assertOk()
            ->assertSee('Saved grade report versions')
            ->assertSeeLivewire(GradeReportsTable::class);

        Livewire::actingAs($student->user)
            ->test(GradeReportsTable::class, ['readOnly' => true])
            ->assertSee('2023-2024')
            ->assertDontSee('2025-2026')
            ->assertDontSee('Update term')
            ->call('openView', $confirmed->id)
            ->assertSee('read-only')
            ->call('replaceReport', $confirmed->id)
            ->assertForbidden();
    }

    public function test_results_show_the_stored_construct_summary_not_current_scores(): void
    {
        $student = Student::factory()->create();
        $prediction = Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'factors' => [
                'employability' => [
                    ['feature' => 'gwa', 'label' => 'Strong grades', 'direction' => '+', 'magnitude' => 0.5],
                    ['feature' => 'skills', 'label' => 'Few skills listed', 'direction' => '-', 'magnitude' => 0.3],
                ],
                'dropout' => [],
            ],
            'assessment_snapshot' => [
                'questionnaire' => [
                    'submitted' => true,
                    'definition_version' => 'draft-v1',
                    'construct_scores' => ['study_habits' => 73, 'procrastination' => 41],
                    'answers' => [],
                ],
            ],
        ]);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'definition_version' => 'draft-v1',
            'submitted_at' => now(),
            'construct_scores' => ['study_habits' => 12, 'engagement' => 99],
        ]);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Self-report category summary')
            ->assertSee('Study habits')
            ->assertSee('73')
            ->assertDontSee('Engagement</span>', false)
            ->assertSee('Lifting the score')
            ->assertSee('Strong grades')
            ->assertSee('Few skills listed')
            ->assertSee('Next steps');

        Livewire::actingAs($student->user)
            ->test(PredictionHistoryTable::class, ['studentId' => $student->id])
            ->call('openView', $prediction->id)
            ->assertSee('Category summary')
            ->assertSee('Study habits')
            ->assertSee('Procrastination')
            ->assertDontSee('Engagement</span>', false);
    }

    public function test_attempts_without_stored_category_scores_say_not_available(): void
    {
        $student = Student::factory()->create();
        $old = Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'assessment_snapshot' => null,
        ]);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'definition_version' => 'draft-v1',
            'submitted_at' => now(),
            'construct_scores' => ['study_habits' => 88],
        ]);

        Livewire::actingAs($student->user)
            ->test(PredictionHistoryTable::class, ['studentId' => $student->id])
            ->call('openView', $old->id)
            ->assertSee('Category summary')
            ->assertSee('Not available for this attempt')
            ->assertDontSee('Study habits');
    }

    public function test_staff_cannot_drive_the_student_wizard(): void
    {
        $head = User::factory()->role(UserRole::DepartmentHead)->create();

        Livewire::actingAs($head)->test(AssessmentWizard::class)->assertForbidden();
    }
}
