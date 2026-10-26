<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Staff\DeanReport;
use App\Livewire\Staff\DepartmentReport;
use App\Livewire\Student\QuestionnaireForm;
use App\Models\QuestionnaireItem;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\QuestionnaireItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Milestone40Test extends TestCase
{
    use RefreshDatabase;

    public function test_questionnaire_header_tracks_live_section_progress_and_saved_sections(): void
    {
        $this->seed(QuestionnaireItemSeeder::class);
        $student = Student::factory()->create();
        $academic = QuestionnaireItem::query()
            ->where('definition_version', config('edupredict.questionnaire.current_version'))
            ->where('section', 'academic')
            ->orderBy('sort_order')
            ->get();
        $this->assertGreaterThan(0, $academic->count());

        $component = Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->call('openWizard');

        $component
            ->assertSee('This section · 0 / '.$academic->count().' answered')
            ->assertSee('Overall · 0 / 6 sections saved')
            ->assertSeeHtml('role="progressbar"')
            ->assertSeeHtml('aria-label="This section"')
            ->assertSeeHtml('aria-label="Overall"')
            ->assertSeeHtml('class="wizard-progress"')
            ->assertSeeHtml('class="wizard-dots"')
            ->assertDontSeeHtml('wire:model.live="answers.');

        $answers = [];
        foreach ($academic as $item) {
            $answers[$item->id] = 5;
        }

        $html = $component->set('answers', $answers)->html();
        $this->assertStringContainsString('This section · '.$academic->count().' / '.$academic->count().' answered', $html);
        $this->assertStringContainsString('Overall · 0 / 6 sections saved', $html);

        $html = $component->call('saveDraft')->html();
        $this->assertStringContainsString('Overall · 1 / 6 sections saved', $html);
        $this->assertStringContainsString('This section · '.$academic->count().' / '.$academic->count().' answered', $html);
    }

    public function test_theme_toggle_is_on_guest_and_role_shells(): void
    {
        $student = Student::factory()->create();
        $head = User::factory()->departmentHead()->create();
        $dean = User::factory()->dean()->create();
        $admin = User::factory()->administrator()->create();

        foreach ([
            $this->get(route('login')),
            $this->get(route('privacy')),
            $this->actingAs($student->user)->get(route('student.dashboard')),
            $this->actingAs($head)->get(route('department.dashboard')),
            $this->actingAs($dean)->get(route('dean.dashboard')),
            $this->actingAs($admin)->get(route('admin.dashboard')),
        ] as $page) {
            $page->assertOk()
                ->assertSee('data-theme-toggle', false)
                ->assertSee('edupredict.theme', false);
        }
    }

    public function test_staff_dashboards_keep_nav_links_without_redundant_shortcuts(): void
    {
        $head = User::factory()->departmentHead()->create();
        $dean = User::factory()->dean()->create();

        $headPage = $this->actingAs($head)
            ->get(route('department.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('department.students').'"', false)
            ->assertSee('href="'.route('department.reports').'"', false)
            ->getContent();
        $this->assertStringNotContainsString('ui-btn ui-btn-secondary print:hidden', $headPage);
        $this->assertStringNotContainsString('ui-btn ui-btn-primary print:hidden', $headPage);

        $deanPage = $this->actingAs($dean)
            ->get(route('dean.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('dean.reports').'"', false)
            ->assertSee('College totals only')
            ->getContent();
        $this->assertStringNotContainsString('ui-btn ui-btn-primary print:hidden', $deanPage);
    }

    public function test_reports_keep_a_compact_disclosure_and_export_preamble(): void
    {
        $head = User::factory()->departmentHead()->create();
        $dean = User::factory()->dean()->create();

        $this->actingAs($head)
            ->get(route('department.reports'))
            ->assertOk()
            ->assertSee('About these estimates')
            ->assertSee('qualitative and has no percentage')
            ->assertSee('Asia/Manila');

        $this->actingAs($dean)
            ->get(route('dean.reports'))
            ->assertOk()
            ->assertSee('About these estimates')
            ->assertSee('College totals only')
            ->assertDontSee($head->name);

        $departmentExport = Livewire::actingAs($head)->test(DepartmentReport::class)->call('export');
        $departmentCsv = base64_decode((string) data_get($departmentExport->effects, 'download.content'));
        $this->assertStringContainsString('placeholder heuristic', strtolower($departmentCsv));
        $this->assertStringContainsString('estimates, not guarantees', strtolower($departmentCsv));

        $deanExport = Livewire::actingAs($dean)->test(DeanReport::class)->call('export');
        $deanCsv = base64_decode((string) data_get($deanExport->effects, 'download.content'));
        $this->assertStringContainsString('College aggregates only', $deanCsv);
        $this->assertStringContainsString('qualitative and has no percentage', $deanCsv);
    }
}
