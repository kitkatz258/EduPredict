<?php

namespace Tests\Feature\Questionnaire;

use App\Livewire\Student\AssessmentWizard;
use App\Livewire\Student\QuestionnaireForm;
use App\Models\Program;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\QuestionnaireVersion;
use App\Models\Student;
use App\Models\User;
use App\Services\Prediction\FeatureBuilder;
use App\Services\Questionnaire\ApprovedInstrumentCatalog;
use App\Services\Questionnaire\QuestionnaireScorer;
use App\Services\Questionnaire\QuestionnaireVersionCreator;
use App\Services\Questionnaire\QuestionnaireVersionPublisher;
use App\Services\Questionnaire\RevisedInstrumentActivator;
use App\Services\Questionnaire\RevisedInstrumentCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentAssessmentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(RevisedInstrumentActivator::class)->activate();
    }

    public function test_v2_section_means_use_raw_answers_and_the_published_boundaries(): void
    {
        $cases = [
            [array_fill(0, 10, 1), 1.0, 'Very Low', true],
            [[2, 2, 2, 2, 2, 2, 2, 2, 1, 1], 1.8, 'Very Low', true],
            [[2, 2, 2, 2, 2, 2, 2, 2, 2, 1], 1.9, 'Low', true],
            [[3, 3, 3, 3, 3, 3, 2, 2, 2, 2], 2.6, 'Low', true],
            [[3, 3, 3, 3, 3, 3, 3, 2, 2, 2], 2.7, 'Moderate', false],
            [[4, 4, 4, 4, 3, 3, 3, 3, 3, 3], 3.4, 'Moderate', false],
            [[4, 4, 4, 4, 4, 3, 3, 3, 3, 3], 3.5, 'High', false],
            [[4, 4, 4, 4, 4, 4, 4, 4, 5, 5], 4.2, 'High', false],
            [[4, 4, 4, 4, 4, 4, 4, 5, 5, 5], 4.3, 'Very High', false],
            [array_fill(0, 10, 5), 5.0, 'Very High', false],
        ];

        foreach ($cases as [$values, $mean, $level, $flag]) {
            $scores = $this->scoreSection('academic', $values);
            $this->assertEquals($mean, $scores['academic_self_perception']['mean'], 'mean '.$mean);
            $this->assertSame($level, $scores['academic_self_perception']['level']);
            $this->assertSame($flag, $scores['academic_self_perception']['context_flag']);
            $this->assertSame(array_sum($values), $scores['academic_self_perception']['sum']);
            $this->assertSame(10, $scores['academic_self_perception']['items']);
            $this->assertArrayNotHasKey('overall', $scores);
            $this->assertCount(1, $scores);
        }

        $behavioral = $this->scoreSection('behavioral', array_fill(0, 10, 5));
        $this->assertEquals(5.0, $behavioral['behavioral_engagement']['mean']);
        $this->assertFalse($behavioral['behavioral_engagement']['context_flag']);
        $this->assertSame('context', $behavioral['behavioral_engagement']['role']);
    }

    public function test_missing_answers_are_not_imputed_and_reverse_scoring_is_not_applied(): void
    {
        $items = $this->items('academic');
        $response = $this->response();
        foreach ($items->take(9) as $item) {
            $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 3]);
        }
        foreach ($this->items('behavioral') as $item) {
            $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 4]);
        }

        $scores = app(QuestionnaireScorer::class)->score($response->fresh('answers.item'));
        $this->assertArrayNotHasKey('academic_self_perception', $scores);
        $this->assertEquals(4.0, $scores['behavioral_engagement']['mean']);
        $this->assertArrayNotHasKey('overall', $scores);

        $items->first()->update(['reverse_scored' => true]);
        $reversed = $this->response();
        foreach ($items as $item) {
            $reversed->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 1]);
        }
        $raw = app(QuestionnaireScorer::class)->score($reversed->fresh('answers.item'));
        $this->assertEquals(1.0, $raw['academic_self_perception']['mean']);
        $this->assertSame(10, $raw['academic_self_perception']['sum']);
    }

    public function test_a_student_can_move_between_sections_save_a_draft_and_submit_once(): void
    {
        $student = $this->makeStudent();
        $academic = $this->items('academic');
        $component = Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', RevisedInstrumentCatalog::VERSION)
            ->assertSee('0 / 60 answers saved')
            ->assertSee('Section 1 of 6')
            ->assertSee('A10')
            ->assertSee('I know which subjects I need to improve in and what I need to do about it.');

        foreach ($academic as $item) {
            $component->set('answers.'.$item->id, 4);
        }

        $component->call('saveAndContinue')
            ->assertSet('section', 'socioeconomic')
            ->assertSee('S1')
            ->assertSee('My available budget is sufficient to cover my school-related expenses.');

        $component->set('section', 'academic')
            ->call('saveDraftAndClose')
            ->assertSet('wizardOpen', false);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('section', 'socioeconomic')
            ->assertSet('answers.'.$academic->first()->id, 4)
            ->assertSee('10 / 60 answers saved')
            ->set('section', 'self_perceived_employability')
            ->assertSee('E10')
            ->assertSee('I have a plan for what I will do after graduation.')
            ->set('answers.'.$academic->last()->id, '')
            ->call('submit')
            ->assertHasErrors()
            ->assertSee('Answer every statement before submitting.');

        $this->assertNull(QuestionnaireResponse::query()->where('student_id', $student->id)->value('submitted_at'));

        $answers = [];
        foreach (QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->get() as $item) {
            $answers[$item->id] = $item->section === 'behavioral' ? 5 : 3;
        }

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->set('answers', $answers)
            ->call('submit')
            ->assertHasNoErrors()
            ->call('submit')
            ->assertSee('Start a new response before answering again.');

        $this->assertSame(1, QuestionnaireResponse::query()->where('student_id', $student->id)->whereNotNull('submitted_at')->count());
        $stored = QuestionnaireResponse::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(RevisedInstrumentCatalog::VERSION, $stored->definition_version);
        $this->assertEquals(5.0, $stored->construct_scores['behavioral_engagement']['mean']);
        $this->assertFalse($stored->construct_scores['behavioral_engagement']['context_flag']);
        $this->assertEquals(3.0, $stored->construct_scores['academic_self_perception']['mean']);
        $this->assertCount(6, $stored->construct_scores);
        $this->assertArrayNotHasKey('overall', $stored->construct_scores);

        $features = app(FeatureBuilder::class)->build($student->fresh());
        $this->assertNotContains(5.0, $features->constructScores());
        $this->assertNotContains(3.0, $features->constructScores());
    }

    public function test_historical_v1_results_stay_labeled_and_are_not_recalculated(): void
    {
        $student = $this->makeStudent();
        $stored = ['academic_self_perception' => ['mean' => 4.167, 'items' => 6, 'label' => 'Academic factors', 'code' => 'A', 'level' => 'High', 'context_flag' => false, 'role' => 'context', 'sum' => 25, 'readiness_percent' => 79.18]];
        $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
            'construct_scores' => $stored,
        ]);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSee('Historical 36-item instrument')
            ->assertSee('ucc-panel-approved-v1')
            ->assertDontSee('I know which subjects I need to improve in and what I need to do about it.');

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->call('goTo', 'review')
            ->assertSee('Historical 36-item result')
            ->assertSee('Historical 36-item instrument');

        $this->assertSame($stored, $student->questionnaireResponses()->firstOrFail()->fresh()->construct_scores);
    }

    public function test_support_flag_copy_does_not_treat_behavioral_engagement_as_dropout_risk(): void
    {
        $student = $this->makeStudent();
        $student->questionnaireResponses()->create([
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
            'construct_scores' => [
                'academic_self_perception' => [
                    'mean' => 2.6,
                    'items' => 10,
                    'label' => 'Academic factors',
                    'code' => 'A',
                    'level' => 'Low',
                    'context_flag' => true,
                    'role' => 'context',
                    'sum' => 26,
                    'readiness_percent' => 40.0,
                ],
            ],
        ]);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSee('Support flag only: stored mean is 2.60 or below.')
            ->assertSee('A higher behavioral mean means more engagement, not a higher dropout risk.')
            ->assertDontSee('employability probability')
            ->assertDontSee('dropout probability');
    }

    public function test_version_changes_do_not_move_existing_drafts_or_show_unpublished_questions(): void
    {
        $admin = User::factory()->administrator()->create();
        $sentinel = 'Students must not see this unpublished draft question.';
        $unpublished = app(QuestionnaireVersionCreator::class)->create($admin, 'Hidden student draft', 'empty', null);
        QuestionnaireItem::query()->create([
            'section' => 'academic',
            'definition_version' => $unpublished->code,
            'construct' => 'academic_self_perception',
            'text' => $sentinel,
            'reverse_scored' => false,
            'is_active' => true,
            'is_draft' => true,
            'sort_order' => 1,
        ]);

        $continuing = $this->makeStudent();
        $academic = $this->items('academic');
        $draft = $continuing->questionnaireResponses()->create([
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'submitted_at' => null,
        ]);
        $draft->answers()->create(['questionnaire_item_id' => $academic[0]->id, 'value' => 2]);
        $draft->answers()->create(['questionnaire_item_id' => $academic[1]->id, 'value' => 5]);

        Livewire::actingAs($continuing->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', RevisedInstrumentCatalog::VERSION)
            ->assertDontSee($sentinel)
            ->assertSee('2 / 60 answers saved');

        $replacement = app(QuestionnaireVersionCreator::class)->create($admin, 'Isolated published successor', 'copy_active', 'Test-only activation.');
        $published = app(QuestionnaireVersionPublisher::class)->publish(
            $replacement,
            $admin,
            'Isolated test approval for the copied 60-item contract only.',
            true,
        );
        $this->assertTrue($published['published']);

        Livewire::actingAs($continuing->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', RevisedInstrumentCatalog::VERSION)
            ->assertSet('answers.'.$academic[0]->id, 2)
            ->assertSet('answers.'.$academic[1]->id, 5)
            ->assertDontSee($sentinel)
            ->assertSee('2 / 60 answers saved');

        $newcomer = $this->makeStudent();
        Livewire::actingAs($newcomer->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', $replacement->code)
            ->assertDontSee($sentinel)
            ->assertSee('0 / 60 answers saved')
            ->assertSee('I am satisfied with my current academic performance.');

        $this->assertSame(RevisedInstrumentCatalog::VERSION, $draft->fresh()->definition_version);
        $this->assertSame(1, QuestionnaireVersion::query()->where('current_slot', 1)->count());
        $this->assertSame(QuestionnaireVersion::STATE_DRAFT, $unpublished->fresh()->state);
        $this->assertNull($unpublished->fresh()->current_slot);
    }

    private function scoreSection(string $section, array $values): array
    {
        $response = $this->response();
        foreach ($this->items($section) as $index => $item) {
            $response->answers()->create([
                'questionnaire_item_id' => $item->id,
                'value' => $values[$index],
            ]);
        }

        return app(QuestionnaireScorer::class)->score($response->fresh('answers.item'));
    }

    private function items(string $section)
    {
        return QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', $section)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function response(): QuestionnaireResponse
    {
        return $this->makeStudent()->questionnaireResponses()->create([
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
        ]);
    }

    private function makeStudent(): Student
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => '2024-'.random_int(10000, 99999),
        ]);
    }
}
