<?php

declare(strict_types=1);

namespace Tests\Feature\Questionnaire;

use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\QuestionnaireVersion;
use App\Models\Student;
use App\Services\Questionnaire\ApprovedInstrumentCatalog;
use App\Services\Questionnaire\RevisedInstrumentCatalog;
use Database\Seeders\QuestionnaireItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovedQuestionnaireReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_missing_questions_without_writing(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->delete();

        $this->artisan('edupredict:prepare-approved-questionnaire')
            ->expectsOutputToContain('Dry run. No questionnaire rows were written.')
            ->expectsOutputToContain('60')
            ->assertSuccessful();

        $this->assertSame(0, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
        $this->assertSame(3, QuestionnaireVersion::query()->count());
    }

    public function test_execute_installs_the_sixty_item_catalog_and_keeps_history(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->delete();
        $historical = $this->historicalSubmission('Historical v1 wording must stay.');
        $responses = QuestionnaireResponse::query()->count();
        $answers = (int) $historical->answers()->count();

        $this->artisan('edupredict:prepare-approved-questionnaire', [
            '--execute' => true,
            '--confirm-preserves-history' => true,
        ])->expectsOutputToContain('Post-check passed')->assertSuccessful();

        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->where('is_draft', false)->count());
        $this->assertSame(36, QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->count());
        $this->assertSame('Historical v1 wording must stay.', QuestionnaireItem::query()->findOrFail($historical->answers()->value('questionnaire_item_id'))->text);
        $this->assertSame(ApprovedInstrumentCatalog::VERSION, $historical->fresh()->definition_version);
        $this->assertSame($responses, QuestionnaireResponse::query()->count());
        $this->assertSame($answers, (int) $historical->fresh()->answers()->count());
        $this->assertSame(RevisedInstrumentCatalog::VERSION, QuestionnaireVersion::query()->where('current_slot', 1)->value('code'));
        $this->assertSame(3, QuestionnaireVersion::query()->count());

        $this->artisan('edupredict:prepare-approved-questionnaire', [
            '--execute' => true,
            '--confirm-preserves-history' => true,
        ])->assertSuccessful();
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
    }

    public function test_execute_without_confirmation_does_not_write(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->delete();

        $this->artisan('edupredict:prepare-approved-questionnaire', ['--execute' => true])
            ->expectsOutputToContain('Refusing to write')
            ->assertFailed();

        $this->assertSame(0, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
    }

    public function test_a_different_current_version_blocks_the_update(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $revised = QuestionnaireVersion::query()->where('code', RevisedInstrumentCatalog::VERSION)->firstOrFail();
        $revised->update(['current_slot' => null]);
        QuestionnaireVersion::query()->create([
            'code' => 'operator-draft',
            'name' => 'Operator draft',
            'state' => QuestionnaireVersion::STATE_PUBLISHED,
            'current_slot' => 1,
            'panel_approved' => false,
            'scoring' => $revised->scoring,
            'reverse_scoring' => false,
            'navigation' => $revised->navigation,
            'sections' => $revised->sections,
            'contract' => QuestionnaireVersion::CONTRACT_SIX_BY_TEN,
        ]);

        $this->artisan('edupredict:prepare-approved-questionnaire', [
            '--execute' => true,
            '--confirm-preserves-history' => true,
        ])->expectsOutputToContain('will not replace it')->assertFailed();

        $this->assertSame('operator-draft', QuestionnaireVersion::query()->where('current_slot', 1)->value('code'));
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
    }

    public function test_answered_wording_that_differs_from_the_catalog_is_not_rewritten(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $item = QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->where('sort_order', 1)
            ->firstOrFail();
        $item->update(['text' => 'Wording already stored with an answer.']);
        $student = Student::factory()->create();
        $response = $student->questionnaireResponses()->create([
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
        ]);
        $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 4]);

        $this->artisan('edupredict:prepare-approved-questionnaire', [
            '--execute' => true,
            '--confirm-preserves-history' => true,
        ])->expectsOutputToContain('was not rewritten')->assertFailed();

        $this->assertSame('Wording already stored with an answer.', $item->fresh()->text);
        $this->assertSame(4, (int) $response->fresh()->answers()->value('value'));
    }

    public function test_a_partial_historical_catalog_blocks_the_update(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $this->historicalSubmission('Historical v1 wording must stay.');
        QuestionnaireItem::query()
            ->where('definition_version', ApprovedInstrumentCatalog::VERSION)
            ->where('section', 'socioeconomic')
            ->where('sort_order', 6)
            ->delete();

        $this->artisan('edupredict:prepare-approved-questionnaire')
            ->expectsOutputToContain('36-item')
            ->assertFailed();
    }

    public function test_production_keeps_the_local_activator_closed_and_requires_the_explicit_release_flags(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->where('sort_order', 2)
            ->update(['is_draft' => true]);

        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $this->artisan('edupredict:activate-revised-questionnaire')
            ->expectsOutputToContain('Refusing to change questionnaire definitions in production')
            ->assertFailed();
        $this->artisan('edupredict:prepare-approved-questionnaire', ['--execute' => true])
            ->expectsOutputToContain('Refusing to write')
            ->assertFailed();
        $this->assertTrue((bool) QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->where('sort_order', 2)
            ->value('is_draft'));

        $this->artisan('edupredict:prepare-approved-questionnaire', [
            '--execute' => true,
            '--confirm-preserves-history' => true,
        ])->assertSuccessful();
        $this->assertFalse((bool) QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->where('sort_order', 2)
            ->value('is_draft'));

        $this->app['env'] = 'testing';
        config(['app.env' => 'testing']);
    }

    private function historicalSubmission(string $text): QuestionnaireResponse
    {
        $item = QuestionnaireItem::query()
            ->where('definition_version', ApprovedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->where('sort_order', 1)
            ->firstOrFail();
        $item->update(['text' => $text]);
        $student = Student::factory()->create();
        $response = $student->questionnaireResponses()->create([
            'definition_version' => ApprovedInstrumentCatalog::VERSION,
            'submitted_at' => now()->subDay(),
            'construct_scores' => ['academic_self_perception' => ['mean' => 4.0, 'items' => 6]],
        ]);
        $response->answers()->create(['questionnaire_item_id' => $item->id, 'value' => 5]);

        return $response;
    }
}
