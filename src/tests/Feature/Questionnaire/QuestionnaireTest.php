<?php

namespace Tests\Feature\Questionnaire;

use App\Enums\UserRole;
use App\Livewire\Admin\QuestionnaireItemForm;
use App\Livewire\Student\AssessmentWizard;
use App\Livewire\Student\QuestionnaireForm;
use App\Livewire\Tables\QuestionnaireItemsTable;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\Student;
use App\Models\User;
use App\Services\Prediction\AttemptSnapshot;
use App\Services\Prediction\FeatureBuilder;
use App\Services\Profile\AssessmentProgress;
use App\Services\Questionnaire\ApprovedInstrumentCatalog;
use App\Services\Questionnaire\QuestionnaireScorer;
use App\Services\Questionnaire\QuestionnaireSnapshot;
use App\Services\Questionnaire\RevisedInstrumentCatalog;
use App\Services\Questionnaire\SectionScorePresenter;
use Database\Seeders\QuestionnaireItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_scale_has_four_draft_items_per_construct_and_reverse_items(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);

        $this->assertSame(116, QuestionnaireItem::query()->count());
        $this->assertSame(36, QuestionnaireItem::query()->where('is_draft', false)->count());
        $this->assertSame(20, QuestionnaireItem::query()->where('definition_version', 'draft-v1')->where('section', 'academic_behavior')->count());
        $this->assertSame(RevisedInstrumentCatalog::VERSION, config('edupredict.questionnaire.current_version'));
        $this->assertSame(36, QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->count());
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
        $this->assertSame(0, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->where('reverse_scored', true)->count());
        $this->assertSame(0, QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->where('reverse_scored', true)->count());
        foreach (ApprovedInstrumentCatalog::items() as $item) {
            $this->assertDatabaseHas('questionnaire_items', [
                'definition_version' => ApprovedInstrumentCatalog::VERSION,
                'section' => $item['section'],
                'sort_order' => $item['sort_order'],
                'text' => $item['text'],
                'reverse_scored' => 0,
                'is_draft' => 0,
            ]);
        }
        $this->assertSame(20, QuestionnaireItem::query()->where('definition_version', 'draft-v1')->where('is_draft', true)->count());
        foreach (['study_habits', 'time_management', 'motivation', 'procrastination', 'engagement'] as $construct) {
            $this->assertSame(4, QuestionnaireItem::query()->where('definition_version', 'draft-v1')->where('construct', $construct)->count(), $construct);
            $this->assertTrue(
                QuestionnaireItem::query()->where('construct', $construct)->where('reverse_scored', true)->exists(),
                $construct,
            );
        }
    }

    public function test_reverse_scoring_and_submission_feed_feature_builder(): void
    {
        $student = $this->makeStudent();
        $student->questionnaireResponses()->create([
            'definition_version' => 'draft-v1',
            'submitted_at' => null,
        ]);
        $normal = QuestionnaireItem::factory()->create([
            'definition_version' => 'draft-v1',
            'construct' => 'study_habits',
            'reverse_scored' => false,
            'sort_order' => 1,
        ]);
        $reverse = QuestionnaireItem::factory()->create([
            'definition_version' => 'draft-v1',
            'construct' => 'study_habits',
            'text' => 'I cram at the last minute.',
            'reverse_scored' => true,
            'sort_order' => 2,
        ]);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->set('answers', [
                $normal->id => 5,
                $reverse->id => 1,
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Thank you for answering this questionnaire.')
            ->assertSee('Questionnaire submitted');

        $response = QuestionnaireResponse::query()->where('student_id', $student->id)->first();
        $this->assertNotNull($response->submitted_at);
        $this->assertSame('draft-v1', $response->definition_version);
        $this->assertEquals(100, $response->construct_scores['study_habits']);
        $this->assertSame(1, (int) $response->answers()->where('questionnaire_item_id', $reverse->id)->value('value'));

        $features = app(FeatureBuilder::class)->build($student->fresh());
        $this->assertEquals(100, $features->constructScores()['study_habits']);
        $this->assertTrue(app(AssessmentProgress::class)->for($student->fresh())['sections']['questionnaire']);
    }

    public function test_a_single_reverse_item_answered_low_scores_high(): void
    {
        $student = $this->makeStudent();
        $student->questionnaireResponses()->create([
            'definition_version' => 'draft-v1',
            'submitted_at' => null,
        ]);
        $reverse = QuestionnaireItem::factory()->create([
            'definition_version' => 'draft-v1',
            'construct' => 'procrastination',
            'reverse_scored' => true,
        ]);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->set('answers.'.$reverse->id, 1)
            ->call('submit')
            ->assertHasNoErrors();

        $scores = QuestionnaireResponse::query()->where('student_id', $student->id)->value('construct_scores');
        $this->assertEquals(100, $scores['procrastination']);
    }

    public function test_retake_keeps_history_and_feature_builder_uses_the_latest(): void
    {
        $student = $this->makeStudent();
        $item = QuestionnaireItem::factory()->create([
            'definition_version' => 'draft-v1',
            'construct' => 'motivation',
            'reverse_scored' => false,
        ]);
        $first = $student->questionnaireResponses()->create([
            'definition_version' => 'draft-v1',
            'submitted_at' => now()->subDay(),
            'construct_scores' => ['motivation' => 0],
        ]);
        $first->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 1]);
        $second = $student->questionnaireResponses()->create([
            'definition_version' => 'draft-v1',
            'submitted_at' => now(),
            'construct_scores' => ['motivation' => 100],
        ]);
        $second->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 5]);

        $responses = QuestionnaireResponse::query()->where('student_id', $student->id)->orderBy('id')->get();
        $this->assertCount(2, $responses);
        $this->assertEquals(0, $responses[0]->construct_scores['motivation']);
        $this->assertEquals(100, $responses[1]->construct_scores['motivation']);
        $this->assertEquals(100, app(FeatureBuilder::class)->build($student->fresh())->constructScores()['motivation']);
    }

    public function test_questionnaire_is_step_based_and_a_versioned_draft_resumes(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $studyItems = QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->orderBy('sort_order')
            ->get();

        $component = Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', RevisedInstrumentCatalog::VERSION)
            ->assertSet('section', 'academic')
            ->assertSee('0 / 60 answers saved')
            ->assertSee('This section · 0 / 10 answered')
            ->assertSee('Overall · 0 / 6 sections saved')
            ->assertSee('Section 1 of 6')
            ->assertSee('I am satisfied with my current academic performance.')
            ->assertSee('I know which subjects I need to improve in and what I need to do about it.')
            ->assertSee('Strongly Disagree')
            ->assertSeeInOrder(['Strongly Agree', 'Agree', 'Neutral', 'Disagree', 'Strongly Disagree']);

        foreach ($studyItems as $item) {
            $component->set('answers.'.$item->id, 4);
        }

        $component->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('section', 'socioeconomic');

        $response = QuestionnaireResponse::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertNull($response->submitted_at);
        $this->assertSame(RevisedInstrumentCatalog::VERSION, $response->definition_version);
        $this->assertCount(10, $response->answers);
        $this->assertDatabaseHas('questionnaire_items', [
            'definition_version' => 'draft-v1',
            'text' => 'I review my notes after class.',
        ]);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('section', 'socioeconomic')
            ->assertSet('answers.'.$studyItems->first()->id, 4)
            ->assertSee('10 / 60 answers saved')
            ->assertSee('Overall · 1 / 6 sections saved')
            ->assertSee('My available budget is sufficient to cover my school-related expenses.');
    }

    public function test_definitions_with_student_answers_are_locked_instead_of_rewritten(): void
    {
        $student = $this->makeStudent();
        $item = QuestionnaireItem::factory()->create(['text' => 'Original wording.']);
        $response = $student->questionnaireResponses()->create([
            'definition_version' => 'draft-v1',
            'submitted_at' => null,
        ]);
        $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 3]);
        $admin = User::factory()->role(UserRole::Administrator)->create();

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemForm::class, ['itemId' => $item->id])
            ->set('text', 'Rewritten wording.')
            ->call('save')
            ->assertHasErrors('item');

        $this->assertSame('Original wording.', $item->fresh()->text);

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->call('toggleActive', $item->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertTrue($item->fresh()->is_active);
    }

    public function test_other_roles_cannot_use_the_student_or_admin_questionnaire_actions(): void
    {
        $student = $this->makeStudent();
        $item = QuestionnaireItem::factory()->create();
        $head = User::factory()->departmentHead()->create();
        $admin = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($student->user)
            ->get(route('student.questionnaire'))
            ->assertRedirect('/student/assessment?step=questionnaire');
        $this->actingAs($student->user)
            ->get('/student/assessment?step=questionnaire')
            ->assertOk()
            ->assertSee('not a clinical or diagnostic assessment');
        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->call('goTo', 'employability')
            ->assertSet('step', 'questionnaire')
            ->assertSee('Draft instrument')
            ->assertDontSee('planning categories only')
            ->assertDontSee('Mental alertness');
        $this->actingAs($head)->get(route('student.questionnaire'))->assertForbidden();
        $this->actingAs($student->user)->get(route('admin.questionnaire'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.questionnaire'))->assertOk()->assertSee('Questionnaire items');

        Livewire::actingAs($head)->test(QuestionnaireForm::class)->assertForbidden();
        Livewire::actingAs($student->user)->test(QuestionnaireItemsTable::class)->assertForbidden();
        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->call('toggleActive', $item->id)
            ->assertHasNoErrors();

        $this->assertFalse($item->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemForm::class)
            ->call('startCreate')
            ->set('definition_version', 'draft-v1')
            ->set('section', 'academic_behavior')
            ->set('text', 'I ask questions when I am stuck.')
            ->set('construct', 'engagement')
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('success-dialog', title: 'Successfully added.');

        $this->assertDatabaseHas('questionnaire_items', [
            'text' => 'I ask questions when I am stuck.',
            'construct' => 'engagement',
            'is_draft' => 1,
        ]);
    }

    public function test_category_choices_follow_the_section_and_invalid_pairs_cannot_save(): void
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemForm::class)
            ->call('startCreate')
            ->set('definition_version', 'draft-v1')
            ->set('section', 'academic_behavior')
            ->assertSee('Category')
            ->assertDontSee('Construct')
            ->assertSee('Study habits')
            ->set('construct', 'procrastination')
            ->set('section', 'socioeconomic')
            ->assertSet('construct', 'socioeconomic_context')
            ->assertSee('Socioeconomic factors')
            ->assertDontSee('No categories are configured for this section yet.')
            ->set('section', 'academic_behavior')
            ->assertSet('construct', 'study_habits')
            ->set('section', 'competence')
            ->set('construct', 'motivation')
            ->set('text', 'An employability item that should not save.')
            ->call('save')
            ->assertHasErrors('construct')
            ->assertSee('Choose a category configured for this questionnaire section.');

        $this->assertDatabaseMissing('questionnaire_items', [
            'text' => 'An employability item that should not save.',
        ]);
    }

    public function test_approved_section_means_follow_the_scoring_reference_and_do_not_enter_the_predictor(): void
    {
        $student = $this->makeStudent();
        $this->seedApprovedItems(['competence', 'psychological_capital', 'self_perceived_employability', 'academic']);
        $response = $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
        ]);
        $this->answerSection($response, 'competence', [4, 5, 4, 3, 4, 4, 5, 4]);
        $this->answerSection($response, 'psychological_capital', [4, 4, 5, 4, 3, 4]);
        $this->answerSection($response, 'self_perceived_employability', [4, 4, 3, 4]);
        $academic = QuestionnaireItem::query()->where('section', 'academic')->orderBy('sort_order')->firstOrFail();
        $academic->update(['reverse_scored' => true]);
        $response->answers()->create(['questionnaire_item_id' => $academic->id, 'value' => 1]);
        foreach (QuestionnaireItem::query()->where('section', 'academic')->where('id', '!=', $academic->id)->get() as $item) {
            $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 1]);
        }

        $scores = app(QuestionnaireScorer::class)->score($response->fresh('answers.item'));
        $this->assertSame(4.125, $scores['competence']['mean']);
        $this->assertSame('High', $scores['competence']['level']);
        $this->assertSame(78.13, $scores['competence']['readiness_percent']);
        $this->assertFalse($scores['competence']['context_flag']);
        $this->assertSame('not_connected', $scores['competence']['role']);
        $this->assertSame(4.0, $scores['psychological_capital']['mean']);
        $this->assertSame(75.0, $scores['psychological_capital']['readiness_percent']);
        $this->assertSame(3.75, $scores['self_perceived_employability']['mean']);
        $this->assertSame(68.75, $scores['self_perceived_employability']['readiness_percent']);
        $this->assertSame('descriptive', $scores['self_perceived_employability']['role']);
        $this->assertSame(1.0, $scores['academic_self_perception']['mean']);
        $this->assertSame('Very Low', $scores['academic_self_perception']['level']);
        $this->assertTrue($scores['academic_self_perception']['context_flag']);

        $response->construct_scores = $scores;
        $response->save();
        $features = app(FeatureBuilder::class)->build($student->fresh());
        $this->assertNull($features->studyHabits);
        $this->assertNull($features->engagement);
        $this->assertNotContains(4.125, $features->constructScores());

        $scorer = app(QuestionnaireScorer::class);
        $this->assertSame(['level' => 'Very Low', 'context_flag' => true], $scorer->interpret(1.80, true));
        $this->assertSame(['level' => 'Low', 'context_flag' => true], $scorer->interpret(1.805, true));
        $this->assertSame(['level' => 'Low', 'context_flag' => true], $scorer->interpret(2.60, true));
        $this->assertSame(['level' => 'Moderate', 'context_flag' => false], $scorer->interpret(2.605, true));
        $this->assertSame(['level' => 'High', 'context_flag' => false], $scorer->interpret(4.20, false));
        $this->assertSame(['level' => 'Very High', 'context_flag' => false], $scorer->interpret(4.21, false));

        $stored = app(SectionScorePresenter::class)->rows([
            'academic_self_perception' => ['mean' => 1.5, 'label' => 'Academic factors', 'code' => 'A'],
        ]);
        $this->assertNull($stored[0]['level']);
        $this->assertSame(1.5, $stored[0]['mean']);
    }

    public function test_socioeconomic_answers_are_encrypted_and_omitted_from_prediction_snapshots(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => null,
        ]);
        $items = QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->orderBy('id')->get();
        $answers = [];
        foreach ($items as $item) {
            $answers[$item->id] = $item->section === 'socioeconomic' ? 2 : 4;
        }

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->set('answers', $answers)
            ->call('submit')
            ->assertHasNoErrors();

        $response = QuestionnaireResponse::query()->where('student_id', $student->id)->firstOrFail();
        $sensitive = $response->answers()->whereHas('item', fn ($query) => $query->where('section', 'socioeconomic'))->firstOrFail();
        $raw = DB::table('questionnaire_answers')->where('id', $sensitive->id)->first();
        $this->assertSame(0, (int) $raw->value);
        $this->assertNotSame('2', $raw->protected_value);
        $this->assertGreaterThan(20, strlen((string) $raw->protected_value));
        $this->assertSame(2, $sensitive->fresh()->responseValue());

        $snapshot = app(QuestionnaireSnapshot::class)->for($student->fresh());
        $encoded = json_encode($snapshot);
        $this->assertSame(6, $snapshot['sensitive_answers_omitted']);
        $this->assertStringNotContainsString('My available budget is sufficient to cover my school-related expenses.', $encoded);
        $this->assertStringContainsString('I am satisfied with my current academic performance.', $encoded);
        $this->assertEquals(4.0, $snapshot['construct_scores']['academic_self_perception']['mean']);

        $prediction = Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'assessment_snapshot' => [
                'questionnaire' => [
                    'submitted' => true,
                    'definition_version' => 'draft-v1',
                    'construct_scores' => ['study_habits' => 12],
                    'answers' => [['text' => 'Saved at the time.', 'value' => 2]],
                ],
            ],
            'feature_snapshot' => ['construct_scores' => ['study_habits' => 12]],
        ]);
        $response->construct_scores = ['study_habits' => 99];
        $response->save();

        $summary = app(AttemptSnapshot::class)->constructSummary($prediction->fresh());
        $this->assertSame('percent', $summary[0]['presentation']);
        $this->assertSame(12.0, $summary[0]['score']);
        $this->assertSame(12, $prediction->fresh()->assessment_snapshot['questionnaire']['construct_scores']['study_habits']);

        $head = User::factory()->departmentHead()->create();
        $this->assertFalse(Gate::forUser($head)->allows('view', $response));
    }

    public function test_reseed_does_not_rewrite_answered_definitions_or_duplicate_items(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $item = QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->where('sort_order', 1)->where('section', 'academic')->firstOrFail();
        $student = $this->makeStudent();
        $response = $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
        ]);
        $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 5]);
        $item->update(['text' => 'Temporarily changed.']);

        $this->seed(QuestionnaireItemSeeder::class);

        $this->assertSame(116, QuestionnaireItem::query()->count());
        $this->assertSame('Temporarily changed.', $item->fresh()->text);
        $this->assertSame('I review my notes after class.', QuestionnaireItem::query()->where('definition_version', 'draft-v1')->where('construct', 'study_habits')->orderBy('sort_order')->value('text'));
    }

    public function test_retake_opens_the_current_instrument_and_keeps_the_submitted_response(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $items = QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->get();
        $answers = [];
        foreach ($items as $item) {
            $answers[$item->id] = 3;
        }

        $component = Livewire::actingAs($student->user)->test(QuestionnaireForm::class);
        $component->set('answers', $answers)->call('submit')->assertHasNoErrors();
        $storedMean = QuestionnaireResponse::query()->where('student_id', $student->id)->firstOrFail()->construct_scores['competence']['mean'];
        $component->call('retake')->assertSet('definitionVersion', RevisedInstrumentCatalog::VERSION);

        $responses = QuestionnaireResponse::query()->where('student_id', $student->id)->orderBy('id')->get();
        $this->assertCount(2, $responses);
        $this->assertNotNull($responses[0]->submitted_at);
        $this->assertNull($responses[1]->submitted_at);
        $this->assertEquals($storedMean, $responses[0]->fresh()->construct_scores['competence']['mean']);
        $this->assertEquals(3.0, $responses[0]->construct_scores['competence']['mean']);
        $this->assertSame([], $component->get('answers'));
    }

    /**
     * @param  list<string>  $sections
     */
    private function seedApprovedItems(array $sections): void
    {
        foreach (ApprovedInstrumentCatalog::items() as $item) {
            if (! in_array($item['section'], $sections, true)) {
                continue;
            }
            QuestionnaireItem::query()->create([
                'definition_version' => ApprovedInstrumentCatalog::VERSION,
                'section' => $item['section'],
                'construct' => $item['construct'],
                'text' => $item['text'],
                'reverse_scored' => false,
                'is_active' => true,
                'is_draft' => false,
                'sort_order' => $item['sort_order'],
            ]);
        }
    }

    public function test_a_current_version_draft_can_be_discarded_without_deleting_a_submission(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $item = QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->firstOrFail();
        $submitted = $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => now()->subDay(),
            'construct_scores' => ['academic' => ['mean' => 4]],
        ]);
        $submitted->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 4]);
        $draft = $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => null,
        ]);
        $draft->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 2]);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSee('Discard draft')
            ->assertSee('Section 1 of 6')
            ->call('discardDraft')
            ->assertSet('wizardOpen', false);

        $this->assertDatabaseMissing('questionnaire_responses', ['id' => $draft->id]);
        $this->assertNotNull($submitted->fresh()->submitted_at);
        $this->assertSame(4, (int) $submitted->fresh()->answers()->value('value'));
    }

    public function test_the_approved_instrument_stays_at_36_items_without_a_seventh_behavioral_item(): void
    {
        $items = ApprovedInstrumentCatalog::items();
        $codes = array_column($items, 'code');

        $this->assertCount(36, $items);
        $this->assertNotContains('B7', $codes);
        $this->assertCount(6, array_filter($codes, fn (string $code): bool => str_starts_with($code, 'B')));
        $this->assertSame(['A' => 6, 'S' => 6, 'B' => 6, 'C' => 8, 'P' => 6, 'E' => 4], [
            'A' => count(array_filter($codes, fn (string $code): bool => str_starts_with($code, 'A'))),
            'S' => count(array_filter($codes, fn (string $code): bool => str_starts_with($code, 'S'))),
            'B' => count(array_filter($codes, fn (string $code): bool => str_starts_with($code, 'B'))),
            'C' => count(array_filter($codes, fn (string $code): bool => str_starts_with($code, 'C'))),
            'P' => count(array_filter($codes, fn (string $code): bool => str_starts_with($code, 'P'))),
            'E' => count(array_filter($codes, fn (string $code): bool => str_starts_with($code, 'E'))),
        ]);
    }

    /**
     * @param  list<int>  $values
     */
    private function answerSection(QuestionnaireResponse $response, string $section, array $values): void
    {
        $items = QuestionnaireItem::query()->where('section', $section)->orderBy('sort_order')->get();
        foreach ($items as $index => $item) {
            $response->answers()->create([
                'questionnaire_item_id' => $item->id,
                'value' => $values[$index],
            ]);
        }
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
