<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\CollegeForm;
use App\Livewire\Admin\DepartmentForm;
use App\Livewire\Admin\InterventionForm;
use App\Livewire\Student\QuestionnaireForm;
use App\Livewire\Tables\PsocOccupationsTable;
use App\Livewire\Tables\QuestionnaireItemsTable;
use App\Models\CareerMatch;
use App\Models\College;
use App\Models\Department;
use App\Models\InstitutionStudent;
use App\Models\Intervention;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\PsocOccupation;
use App\Models\QuestionnaireItem;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\StudentAccountProvisioner;
use App\Services\Career\CareerMatchBuilder;
use App\Services\Questionnaire\ApprovedInstrumentCatalog;
use App\Services\Questionnaire\QuestionnaireScorer;
use App\Services\Questionnaire\RevisedInstrumentCatalog;
use Database\Seeders\QuestionnaireItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class Milestone41Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_add_and_edit_close_the_modal_and_dispatch_a_centered_success_dialog(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create();

        Livewire::actingAs($admin)
            ->test(DepartmentForm::class)
            ->call('startCreate')
            ->set('collegeId', $college->id)
            ->call('save')
            ->assertHasErrors()
            ->assertSet('show', true)
            ->assertNotDispatched('success-dialog');

        Livewire::actingAs($admin)
            ->test(DepartmentForm::class)
            ->call('startCreate')
            ->set('collegeId', $college->id)
            ->set('name', 'Department of Milestone Testing')
            ->set('code', 'dmt')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('show', true)
            ->assertNotDispatched('success-dialog');

        $this->assertDatabaseMissing('departments', ['code' => 'DMT']);

        Livewire::actingAs($admin)
            ->test(DepartmentForm::class)
            ->call('startCreate')
            ->set('collegeId', $college->id)
            ->set('name', 'Department of Milestone Testing')
            ->set('code', 'dmt')
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('success-dialog', title: 'Successfully added.');

        $department = Department::query()->where('code', 'DMT')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(DepartmentForm::class)
            ->call('startEdit', $department->id)
            ->set('name', 'Department of Milestone Testing Updated')
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('success-dialog', title: 'Changes saved successfully.');

        Livewire::actingAs($admin)
            ->test(CollegeForm::class)
            ->call('startEdit', $college->id)
            ->set('name', 'Renamed College')
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('success-dialog', title: 'Changes saved successfully.');

        $intervention = Intervention::factory()->create(['code' => 'office_hours_m41']);

        Livewire::actingAs($admin)
            ->test(InterventionForm::class)
            ->call('startEdit', $intervention->id)
            ->set('title', 'Office hours updated')
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('success-dialog', title: 'Changes saved successfully.');
    }

    public function test_the_revised_60_item_instrument_is_seeded_without_changing_the_36_item_version(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);

        $this->assertSame(RevisedInstrumentCatalog::VERSION, config('edupredict.questionnaire.current_version'));
        $this->assertSame('draft', config('edupredict.questionnaire.versions.'.RevisedInstrumentCatalog::VERSION.'.status'));
        $this->assertFalse((bool) config('edupredict.questionnaire.versions.'.RevisedInstrumentCatalog::VERSION.'.reverse_scoring'));
        $this->assertCount(60, RevisedInstrumentCatalog::items());
        $this->assertCount(36, ApprovedInstrumentCatalog::items());

        foreach (RevisedInstrumentCatalog::items() as $item) {
            $this->assertDatabaseHas('questionnaire_items', [
                'definition_version' => RevisedInstrumentCatalog::VERSION,
                'section' => $item['section'],
                'sort_order' => $item['sort_order'],
                'text' => $item['text'],
                'reverse_scored' => 0,
                'is_draft' => 1,
                'is_active' => 1,
            ]);
        }

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

        $this->assertSame(10, QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->where('section', 'academic')->count());
        $this->assertSame(6, QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->where('section', 'academic')->count());
    }

    public function test_a_complete_60_item_section_uses_a_mean_of_ten_without_reverse_coding(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = Student::factory()->create();
        $response = $student->questionnaireResponses()->create([
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'submitted_at' => now(),
        ]);
        $items = QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->orderBy('sort_order')
            ->get();
        $this->assertCount(10, $items);
        foreach ($items as $index => $item) {
            $response->answers()->create([
                'questionnaire_item_id' => $item->id,
                'value' => $index < 5 ? 5 : 1,
            ]);
        }

        $scores = app(QuestionnaireScorer::class)->score($response->fresh('answers.item'));
        $this->assertEquals(3.0, $scores['academic_self_perception']['mean']);
        $this->assertEquals(30, $scores['academic_self_perception']['sum']);
        $this->assertSame(10, $scores['academic_self_perception']['items']);
        $this->assertEquals(50.0, $scores['academic_self_perception']['readiness_percent']);
    }

    public function test_questionnaire_save_draft_and_close_persists_answers_and_does_not_render_cancel(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = Student::factory()->create();
        $item = QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->where('section', 'academic')
            ->orderBy('sort_order')
            ->firstOrFail();

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->call('openWizard')
            ->assertSee('Save draft & close')
            ->assertSee('data-dialog-close="saveDraftAndClose"', false)
            ->assertSee('return () => this.destroy();', false)
            ->assertDontSeeHtml('>Cancel</button>')
            ->assertDontSee('Reverse')
            ->set('answers.'.$item->id, 4)
            ->call('saveDraftAndClose')
            ->assertSet('wizardOpen', false)
            ->assertDispatched('success-dialog');

        $this->assertDatabaseHas('questionnaire_answers', [
            'questionnaire_item_id' => $item->id,
            'value' => 4,
        ]);
    }

    public function test_admin_questionnaire_list_drops_reverse_and_keeps_three_actions_on_one_row(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->set('versionFilter', RevisedInstrumentCatalog::VERSION)
            ->assertDontSee('>Reverse<', false)
            ->assertSeeHtml('flex min-w-[7.5rem] flex-nowrap')
            ->assertSee('Edit')
            ->assertSee('Deactivate')
            ->assertSee('Mark published');
    }

    public function test_inactive_psoc_occupations_are_excluded_from_new_matches_but_historical_rows_stay(): void
    {
        $admin = User::factory()->administrator()->create();
        $active = PsocOccupation::factory()->create([
            'psoc_code' => '2512',
            'title' => 'Software Developers',
            'is_active' => true,
        ]);
        $inactive = PsocOccupation::factory()->create([
            'psoc_code' => '2411',
            'title' => 'Accountants',
            'is_active' => true,
            'related_program_codes' => ['BSA'],
        ]);

        Livewire::actingAs($admin)
            ->test(PsocOccupationsTable::class)
            ->call('setOccupationActive', $inactive->id)
            ->assertHasNoErrors();

        $this->assertFalse($inactive->fresh()->is_active);

        $student = Student::factory()->create();
        $prediction = Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'feature_snapshot' => ['gwa' => 1.75],
        ]);

        $matches = app(CareerMatchBuilder::class)->ensure($prediction->fresh());
        $this->assertTrue($matches->contains(fn (CareerMatch $match): bool => $match->psoc_occupation_id === $active->id));
        $this->assertFalse($matches->contains(fn (CareerMatch $match): bool => $match->psoc_occupation_id === $inactive->id));

        $historical = Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'feature_snapshot' => ['gwa' => 1.75],
        ]);
        CareerMatch::query()->create([
            'prediction_id' => $historical->id,
            'psoc_occupation_id' => $inactive->id,
            'compatibility_score' => 80,
            'matched_skills' => ['accounting'],
            'missing_skills' => [],
            'explanation' => 'Stored before deactivation.',
            'explanation_source' => 'template',
        ]);

        $kept = app(CareerMatchBuilder::class)->ensure($historical->fresh());
        $this->assertCount(1, $kept);
        $this->assertSame($inactive->id, $kept->first()->psoc_occupation_id);
        $this->assertSame('Stored before deactivation.', $kept->first()->explanation);
    }

    public function test_initial_student_passwords_use_the_uppercase_last_name(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['is_active' => true]);
        $record = InstitutionStudent::factory()->create([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'email' => 'lina-m41@example.com',
            'is_registered' => false,
        ]);

        $result = app(StudentAccountProvisioner::class)->provision($record, $admin, 'lina-m41@example.com');
        $this->assertSame('DELA CRUZ', $result['temporary_password']);
        $this->assertTrue($result['user']->must_change_password);
        $this->assertTrue(Hash::check('DELA CRUZ', (string) $result['user']->password));
        $this->assertFalse(Hash::check('Dela Cruz', (string) $result['user']->password));
    }
}
