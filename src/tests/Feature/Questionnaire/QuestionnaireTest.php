<?php

namespace Tests\Feature\Questionnaire;

use App\Enums\UserRole;
use App\Livewire\Admin\QuestionnaireItemForm;
use App\Livewire\Student\AssessmentWizard;
use App\Livewire\Student\QuestionnaireForm;
use App\Livewire\Tables\QuestionnaireItemsTable;
use App\Models\Program;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\Student;
use App\Models\User;
use App\Services\Prediction\FeatureBuilder;
use App\Services\Profile\AssessmentProgress;
use Database\Seeders\QuestionnaireItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_scale_has_four_draft_items_per_construct_and_reverse_items(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);

        $this->assertSame(20, QuestionnaireItem::query()->count());
        $this->assertSame(0, QuestionnaireItem::query()->where('is_draft', false)->count());
        $this->assertSame(20, QuestionnaireItem::query()->where('definition_version', 'draft-v1')->where('section', 'academic_behavior')->count());
        foreach (array_keys(config('edupredict.questionnaire.constructs')) as $construct) {
            $this->assertSame(4, QuestionnaireItem::query()->where('construct', $construct)->count(), $construct);
            $this->assertTrue(
                QuestionnaireItem::query()->where('construct', $construct)->where('reverse_scored', true)->exists(),
                $construct,
            );
        }
    }

    public function test_reverse_scoring_and_submission_feed_feature_builder(): void
    {
        $student = $this->makeStudent();
        $normal = QuestionnaireItem::factory()->create([
            'construct' => 'study_habits',
            'reverse_scored' => false,
            'sort_order' => 1,
        ]);
        $reverse = QuestionnaireItem::factory()->create([
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
        $reverse = QuestionnaireItem::factory()->create([
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
            'construct' => 'motivation',
            'reverse_scored' => false,
        ]);

        $component = Livewire::actingAs($student->user)->test(QuestionnaireForm::class);
        $component->set('answers.'.$item->id, 1)->call('submit')->assertHasNoErrors();
        $component->call('retake')->set('answers.'.$item->id, 5)->call('submit')->assertHasNoErrors();

        $responses = QuestionnaireResponse::query()->where('student_id', $student->id)->orderBy('id')->get();
        $this->assertCount(2, $responses);
        $this->assertNotNull($responses[0]->submitted_at);
        $this->assertEquals(0, $responses[0]->construct_scores['motivation']);
        $this->assertEquals(100, $responses[1]->construct_scores['motivation']);
        $this->assertEquals(100, app(FeatureBuilder::class)->build($student->fresh())->constructScores()['motivation']);
    }

    public function test_questionnaire_is_step_based_and_a_versioned_draft_resumes(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $studyItems = QuestionnaireItem::query()->where('construct', 'study_habits')->orderBy('sort_order')->get();

        $component = Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', 'draft-v1')
            ->assertSet('construct', 'study_habits')
            ->assertSee('0 / 20 answered')
            ->assertSee('Step 1 of 5');

        foreach ($studyItems as $item) {
            $component->set('answers.'.$item->id, 4);
        }

        $component->call('saveAndContinue')
            ->assertHasNoErrors()
            ->assertSet('construct', 'time_management');

        $response = QuestionnaireResponse::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertNull($response->submitted_at);
        $this->assertSame('draft-v1', $response->definition_version);
        $this->assertCount(4, $response->answers);

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('answers.'.$studyItems->first()->id, 4)
            ->assertSee('4 / 20 answered');
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
            ->call('selectQuestionnaireSection', 'employability')
            ->assertSee('Mental alertness')
            ->assertSee('planning categories only');
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
            ->set('text', 'I ask questions when I am stuck.')
            ->set('construct', 'engagement')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('questionnaire_items', [
            'text' => 'I ask questions when I am stuck.',
            'construct' => 'engagement',
            'is_draft' => 1,
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
