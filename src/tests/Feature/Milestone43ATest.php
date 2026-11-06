<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Student\QuestionnaireForm;
use App\Livewire\Tables\QuestionnaireItemsTable;
use App\Models\Program;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\Student;
use App\Models\User;
use App\Services\Questionnaire\ApprovedInstrumentCatalog;
use App\Services\Questionnaire\QuestionnaireScorer;
use App\Services\Questionnaire\RevisedInstrumentActivator;
use App\Services\Questionnaire\RevisedInstrumentCatalog;
use Database\Seeders\QuestionnaireItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Milestone43ATest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_catalog_is_sixty_approved_items_in_six_sections_of_ten(): void
    {
        $items = RevisedInstrumentCatalog::items();
        $codes = array_column($items, 'code');

        $this->assertSame(RevisedInstrumentCatalog::VERSION, config('edupredict.questionnaire.current_version'));
        $this->assertSame('panel_approved', config('edupredict.questionnaire.versions.'.RevisedInstrumentCatalog::VERSION.'.status'));
        $this->assertFalse((bool) config('edupredict.questionnaire.versions.'.RevisedInstrumentCatalog::VERSION.'.psychometrically_validated'));
        $this->assertFalse((bool) config('edupredict.questionnaire.versions.'.RevisedInstrumentCatalog::VERSION.'.reverse_scoring'));
        $this->assertCount(60, $items);
        $this->assertCount(36, ApprovedInstrumentCatalog::items());
        $this->assertSame(['A' => 10, 'S' => 10, 'B' => 10, 'C' => 10, 'P' => 10, 'E' => 10], $this->codeCounts($codes));
        $this->assertSame(
            config('edupredict.questionnaire.versions.'.RevisedInstrumentCatalog::VERSION.'.item_counts'),
            ['academic' => 10, 'socioeconomic' => 10, 'behavioral' => 10, 'competence' => 10, 'psychological_capital' => 10, 'self_perceived_employability' => 10],
        );

        $byCode = [];
        foreach ($items as $item) {
            $byCode[$item['code']] = $item['text'];
        }

        $this->assertSame('I am satisfied with my current academic performance.', $byCode['A1']);
        $this->assertSame('I know which subjects I need to improve in and what I need to do about it.', $byCode['A10']);
        $this->assertSame('I can handle unexpected expenses without having to stop or delay my studies.', $byCode['S7']);
        $this->assertSame('I stay focused during class and avoid distractions such as my phone.', $byCode['B10']);
        $this->assertSame('I show responsibility and professional behavior when working on tasks or with others.', $byCode['C9']);
        $this->assertSame('I can think of many ways to reach my career goals.', $byCode['P7']);
        $this->assertSame('I have a plan for what I will do after graduation.', $byCode['E10']);
        $this->assertSame('I can accept feedback and use it to improve my work.', $byCode['C10']);
    }

    public function test_activation_is_idempotent_and_does_not_duplicate_or_rewrite_v1_history(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $v1Item = QuestionnaireItem::query()
            ->where('definition_version', ApprovedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->where('sort_order', 1)
            ->firstOrFail();
        $historical = $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => now()->subDay(),
            'construct_scores' => ['academic_self_perception' => ['mean' => 4.0, 'items' => 6]],
        ]);
        $historical->answers()->create(['questionnaire_item_id' => $v1Item->id, 'value' => 5]);
        $v1Item->update(['text' => 'Historical v1 wording must stay.']);

        $first = app(RevisedInstrumentActivator::class)->activate();
        $second = app(RevisedInstrumentActivator::class)->activate();

        $this->assertSame(116, QuestionnaireItem::query()->count());
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
        $this->assertSame(0, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->where('is_draft', true)->count());
        $this->assertSame(36, QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->count());
        $this->assertSame('Historical v1 wording must stay.', $v1Item->fresh()->text);
        $this->assertSame(5, (int) $historical->fresh()->answers()->value('value'));
        $this->assertSame(ApprovedInstrumentCatalog::VERSION, $historical->fresh()->definition_version);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(60, $second['skipped_unchanged'] + $second['published_locked']);
        $this->assertSame(60, $first['total']);
    }

    public function test_the_activator_command_refuses_production_and_dry_run_does_not_write(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->where('sort_order', 10)
            ->update(['is_draft' => true]);

        config(['app.env' => 'production']);
        $this->app['env'] = 'production';
        $this->artisan('edupredict:activate-revised-questionnaire')
            ->expectsOutputToContain('Refusing to change questionnaire definitions in production')
            ->assertFailed();
        $this->assertTrue(
            (bool) QuestionnaireItem::query()
                ->where('definition_version', RevisedInstrumentCatalog::VERSION)
                ->where('section', 'academic')
                ->where('sort_order', 10)
                ->value('is_draft'),
        );

        $this->app['env'] = 'testing';
        config(['app.env' => 'testing']);
        $this->artisan('edupredict:activate-revised-questionnaire', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertTrue(
            (bool) QuestionnaireItem::query()
                ->where('definition_version', RevisedInstrumentCatalog::VERSION)
                ->where('section', 'academic')
                ->where('sort_order', 10)
                ->value('is_draft'),
        );

        $this->artisan('edupredict:activate-revised-questionnaire')->assertSuccessful();
        $this->assertFalse(
            (bool) QuestionnaireItem::query()
                ->where('definition_version', RevisedInstrumentCatalog::VERSION)
                ->where('section', 'academic')
                ->where('sort_order', 10)
                ->value('is_draft'),
        );
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
    }

    public function test_a_new_student_assessment_uses_the_published_60_item_instrument(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $academic = QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->orderBy('sort_order')
            ->get();
        $this->assertCount(10, $academic);

        $component = Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', RevisedInstrumentCatalog::VERSION)
            ->assertSee('Panel-approved · not psychometrically validated.')
            ->assertSee('UCC panel-approved 60-item instrument v2')
            ->assertDontSee('pending panel reapproval')
            ->assertSee('0 / 60 answers saved')
            ->assertSee('This section · 0 / 10 answered')
            ->assertSee('I know which subjects I need to improve in and what I need to do about it.')
            ->assertSee('A1')
            ->assertSee('A10');

        $component->set('answers.'.$academic[0]->id, 5)->call('submit');
        $component->assertHasErrors('answers.'.$academic[1]->id);
        $this->assertNull(QuestionnaireResponse::query()->where('student_id', $student->id)->whereNotNull('submitted_at')->first());
        $this->assertDatabaseMissing('questionnaire_answers', [
            'questionnaire_item_id' => $academic[1]->id,
            'value' => 3,
        ]);
    }

    public function test_an_old_v1_submission_and_open_v1_draft_keep_the_36_item_wording(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $v1Items = QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->orderBy('id')->get();
        $this->assertCount(36, $v1Items);
        $submitted = $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => now()->subDay(),
            'construct_scores' => ['academic_self_perception' => ['mean' => 4.167, 'items' => 6]],
        ]);
        foreach ($v1Items as $item) {
            $submitted->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 4]);
        }

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->assertSee('ucc-panel-approved-v1')
            ->assertSee('Questionnaire submitted')
            ->assertDontSee('I know which subjects I need to improve in and what I need to do about it.');

        $draftStudent = $this->makeStudent();
        $draft = $draftStudent->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => null,
        ]);
        $draft->answers()->create(['questionnaire_item_id' => $v1Items->first()->id, 'value' => 2]);

        Livewire::actingAs($draftStudent->user)
            ->test(QuestionnaireForm::class)
            ->assertSet('definitionVersion', ApprovedInstrumentCatalog::VERSION)
            ->assertSee('I am confident that I can complete my degree program.')
            ->assertDontSee('I know which subjects I need to improve in and what I need to do about it.')
            ->assertSee('1 / 36 answers saved');
    }

    public function test_admin_list_defaults_to_the_current_published_version_and_other_roles_are_forbidden(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $admin = User::factory()->role(UserRole::Administrator)->create();
        $head = User::factory()->departmentHead()->create();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->get(route('admin.questionnaire'))
            ->assertOk()
            ->assertSee('UCC panel-approved 60-item instrument v2')
            ->assertSee('UCC 60-Item · v2 · Active')
            ->assertSee('UCC 36-Item · v1 · Historical')
            ->assertSee('60 items')
            ->assertSee('36 items')
            ->assertDontSee('Questionnaire items here are a draft research scale');

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->assertSet('versionFilter', RevisedInstrumentCatalog::VERSION)
            ->set('search', 'I know which subjects I need to improve in')
            ->assertSee('Published')
            ->assertDontSee('I review my notes after class.')
            ->assertSee('I know which subjects I need to improve in and what I need to do about it.');

        $this->actingAs($head)->get(route('admin.questionnaire'))->assertForbidden();
        $this->actingAs($student->user)->get(route('admin.questionnaire'))->assertForbidden();
        Livewire::actingAs($head)->test(QuestionnaireItemsTable::class)->assertForbidden();
    }

    public function test_a_complete_60_item_submission_stores_section_means_of_ten_without_reverse_coding(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = $this->makeStudent();
        $items = QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->orderBy('id')->get();
        $this->assertCount(60, $items);
        $answers = [];
        foreach ($items as $item) {
            $answers[$item->id] = $item->section === 'academic' ? 5 : 4;
        }

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->set('answers', $answers)
            ->call('submit')
            ->assertHasNoErrors();

        $response = QuestionnaireResponse::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(RevisedInstrumentCatalog::VERSION, $response->definition_version);
        $this->assertNotNull($response->submitted_at);
        $this->assertSame(10, $response->construct_scores['academic_self_perception']['items']);
        $this->assertEquals(5.0, $response->construct_scores['academic_self_perception']['mean']);
        $this->assertEquals(50, $response->construct_scores['academic_self_perception']['sum']);
        $this->assertSame(10, $response->construct_scores['competence']['items']);
        $this->assertEquals(4.0, $response->construct_scores['competence']['mean']);

        $scored = app(QuestionnaireScorer::class)->score($response->fresh('answers.item'));
        $this->assertEquals($response->construct_scores['academic_self_perception']['mean'], $scored['academic_self_perception']['mean']);
        $this->assertArrayNotHasKey('overall', $scored);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    private function codeCounts(array $codes): array
    {
        $counts = ['A' => 0, 'S' => 0, 'B' => 0, 'C' => 0, 'P' => 0, 'E' => 0];
        foreach ($codes as $code) {
            $counts[$code[0]]++;
        }

        return $counts;
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
