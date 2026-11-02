<?php

namespace Tests\Feature\Questionnaire;

use App\Enums\UserRole;
use App\Livewire\Admin\QuestionnaireItemForm;
use App\Livewire\Admin\QuestionnaireVersionManager;
use App\Livewire\Tables\QuestionnaireItemsTable;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireVersion;
use App\Models\Student;
use App\Models\User;
use App\Services\Questionnaire\QuestionnaireVersionCreator;
use App\Services\Questionnaire\QuestionnaireVersionPublisher;
use App\Services\Questionnaire\RevisedInstrumentActivator;
use App\Services\Questionnaire\RevisedInstrumentCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionnaireVersionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_copy_the_active_instrument_into_an_isolated_draft(): void
    {
        app(RevisedInstrumentActivator::class)->activate();
        $admin = User::factory()->role(UserRole::Administrator)->create();
        $v2Ids = QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $student = $this->makeStudent();
        $response = $student->questionnaireResponses()->create([
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'submitted_at' => null,
        ]);
        $response->answers()->create([
            'questionnaire_item_id' => $v2Ids[0],
            'value' => 4,
        ]);

        Livewire::actingAs($admin)
            ->test(QuestionnaireVersionManager::class)
            ->call('openCreate')
            ->set('name', 'Capstone trial copy')
            ->set('summary', 'Copied for review.')
            ->call('closeCreate');

        $this->assertDatabaseMissing('questionnaire_versions', ['name' => 'Capstone trial copy']);

        $component = Livewire::actingAs($admin)
            ->test(QuestionnaireVersionManager::class)
            ->call('openCreate')
            ->set('name', 'Capstone trial copy')
            ->set('mode', 'copy_active')
            ->set('summary', 'Copied for review.')
            ->call('confirmCreate')
            ->assertHasNoErrors();

        $draft = QuestionnaireVersion::query()->where('name', 'Capstone trial copy')->firstOrFail();
        $component->assertSet('selectedCode', $draft->code);
        $this->assertSame(QuestionnaireVersion::STATE_DRAFT, $draft->state);
        $this->assertNull($draft->current_slot);
        $this->assertFalse($draft->panel_approved);
        $this->assertNull($draft->approval_evidence);
        $this->assertSame(RevisedInstrumentCatalog::VERSION, $draft->source_code);
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', $draft->code)->count());
        $this->assertSame(0, $response->answers()->whereHas('item', fn ($query) => $query->where('definition_version', $draft->code))->count());
        $this->assertSame($v2Ids, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->orderBy('id')->pluck('id')->all());
        $this->assertSame(RevisedInstrumentCatalog::VERSION, $response->fresh()->definition_version);
        $this->assertTrue(AuditLog::query()->where('action', 'questionnaire_version_created')->where('subject_id', $draft->id)->exists());
        $this->assertSame(1, QuestionnaireVersion::query()->where('current_slot', 1)->count());
    }

    public function test_publishing_requires_new_approval_and_leaves_history_on_its_original_version(): void
    {
        app(RevisedInstrumentActivator::class)->activate();
        $admin = User::factory()->role(UserRole::Administrator)->create();
        $student = $this->makeStudent();
        $kept = $student->questionnaireResponses()->create([
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
            'construct_scores' => ['academic_self_perception' => ['mean' => 4.5, 'items' => 10]],
        ]);
        $v2Text = QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->orderBy('id')->value('text');

        $draft = app(QuestionnaireVersionCreator::class)->create($admin, 'Ready v3 trial', 'copy_active', 'Section wording checked.');
        $result = app(QuestionnaireVersionPublisher::class)->publish($draft, $admin, 'too short', true);
        $this->assertFalse($result['published']);
        $this->assertSame(QuestionnaireVersion::STATE_DRAFT, $draft->fresh()->state);
        $this->assertSame(RevisedInstrumentCatalog::VERSION, QuestionnaireVersion::query()->where('current_slot', 1)->value('code'));

        $empty = app(QuestionnaireVersionCreator::class)->create($admin, 'Empty trial', 'empty', null);
        $blocked = app(QuestionnaireVersionPublisher::class)->publish($empty, $admin, 'Explicit approval for a structure that is not ready yet.', true);
        $this->assertFalse($blocked['published']);
        $this->assertSame(QuestionnaireVersion::STATE_DRAFT, $empty->fresh()->state);

        Livewire::actingAs($admin)
            ->test(QuestionnaireVersionManager::class)
            ->call('selectVersion', $draft->code)
            ->set('approvalEvidence', 'Department panel approved this copied 60-item instrument on 2 Nov 2026.')
            ->set('acknowledgeActivation', true)
            ->call('confirmPublish')
            ->assertHasNoErrors()
            ->assertSet('publishErrors', []);

        $draft->refresh();
        $previous = QuestionnaireVersion::query()->where('code', RevisedInstrumentCatalog::VERSION)->firstOrFail();
        $this->assertSame(QuestionnaireVersion::STATE_PUBLISHED, $draft->state);
        $this->assertTrue($draft->panel_approved);
        $this->assertSame(1, $draft->current_slot);
        $this->assertNull($previous->current_slot);
        $this->assertSame(QuestionnaireVersion::STATE_PUBLISHED, $previous->state);
        $this->assertSame(1, QuestionnaireVersion::query()->where('current_slot', 1)->count());
        $this->assertSame(RevisedInstrumentCatalog::VERSION, $kept->fresh()->definition_version);
        $this->assertSame(['academic_self_perception' => ['mean' => 4.5, 'items' => 10]], $kept->fresh()->construct_scores);
        $this->assertSame($v2Text, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->orderBy('id')->value('text'));
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->count());
        $this->assertSame(60, QuestionnaireItem::query()->where('definition_version', $draft->code)->count());

        $again = app(QuestionnaireVersionPublisher::class)->publish($draft->fresh(), $admin, 'Department panel approved this copied 60-item instrument on 2 Nov 2026.', true);
        $this->assertFalse($again['published']);
        $this->assertSame(1, QuestionnaireVersion::query()->where('current_slot', 1)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'questionnaire_version_published')->where('subject_id', $draft->id)->exists());
    }

    public function test_draft_questions_can_be_reordered_and_published_questions_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();
        $draft = app(QuestionnaireVersionCreator::class)->create($admin, 'Reorder draft', 'empty', null);
        $first = $this->question($draft->code, 'academic', 1, 'First academic question.');
        $second = $this->question($draft->code, 'academic', 2, 'Second academic question.');
        $third = $this->question($draft->code, 'academic', 3, 'Third academic question.');

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->set('versionFilter', $draft->code)
            ->call('moveDown', $first->id);

        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(3, $third->fresh()->sort_order);
        $this->assertSame('A2', $first->fresh()->displayCode());
        $this->assertSame(
            [$first->id, $second->id, $third->id],
            QuestionnaireItem::query()->whereIn('id', [$first->id, $second->id, $third->id])->orderBy('id')->pluck('id')->all(),
        );

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->set('versionFilter', $draft->code)
            ->call('removeItem', $third->id);

        $this->assertDatabaseMissing('questionnaire_items', ['id' => $third->id]);
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);

        app(RevisedInstrumentActivator::class)->activate();
        $published = QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->orderBy('id')->firstOrFail();
        $original = $published->text;
        $order = $published->sort_order;

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemForm::class, ['itemId' => $published->id])
            ->set('text', 'Rewritten published wording.')
            ->call('save')
            ->assertHasErrors('item');

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->call('moveDown', $published->id);

        $this->assertSame($original, $published->fresh()->text);
        $this->assertSame($order, $published->fresh()->sort_order);
    }

    public function test_other_roles_cannot_create_or_publish_a_questionnaire_version(): void
    {
        $student = $this->makeStudent();
        $head = User::factory()->departmentHead()->create();
        $dean = User::factory()->dean()->create();

        foreach ([$student->user, $head, $dean] as $user) {
            $this->actingAs($user)->get(route('admin.questionnaire'))->assertForbidden();
            Livewire::actingAs($user)->test(QuestionnaireVersionManager::class)->assertForbidden();
            Livewire::actingAs($user)->test(QuestionnaireItemsTable::class)->assertForbidden();
        }

        $this->assertSame(0, QuestionnaireVersion::query()->where('state', QuestionnaireVersion::STATE_DRAFT)->where('code', '!=', 'draft-v1')->count());
    }

    private function question(string $version, string $section, int $position, string $text): QuestionnaireItem
    {
        return QuestionnaireItem::query()->create([
            'section' => $section,
            'definition_version' => $version,
            'construct' => 'academic_self_perception',
            'text' => $text,
            'reverse_scored' => false,
            'is_active' => true,
            'is_draft' => true,
            'sort_order' => $position,
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
