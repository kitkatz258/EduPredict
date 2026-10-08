<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Tables\UsersTable;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_top_navigation_has_only_dashboard_assessment_history_settings_and_logout(): void
    {
        $student = Student::factory()->create();

        $html = $this->actingAs($student->user)
            ->get(route('student.history'))
            ->assertOk()
            ->assertSee('aria-label="Student"', false)
            ->assertDontSee('id="staff-sidebar"', false)
            ->getContent();

        $nav = $this->between($html, '<header class="sticky top-0 z-30', '</header>');

        foreach (['student.dashboard', 'student.assessment', 'student.history', 'profile.edit'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $nav, "nav links to {$route}");
        }
        $this->assertStringContainsString('action="'.route('logout').'"', $nav);
        foreach (['student.grades', 'student.questionnaire', 'student.careers', 'student.results'] as $route) {
            $this->assertStringNotContainsString('"'.route($route).'"', $nav, "nav must not link to {$route}");
        }
        foreach (['My Grades', 'Questionnaire', 'Skills', 'Career Matches', 'About', 'Faculty'] as $label) {
            $this->assertStringNotContainsString($label, $nav);
        }
    }

    public function test_old_student_profile_url_opens_the_assessment(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student->user)
            ->get('/student/profile')
            ->assertRedirect('/student/assessment?step=questionnaire');
    }

    public function test_history_page_lists_only_the_signed_in_students_attempts(): void
    {
        $program = Program::factory()->create();
        $student = Student::factory()->create(['program_id' => $program->id]);
        Prediction::factory()->create(['student_id' => $student->id, 'model_version' => 'placeholder-heuristic-v0']);

        $this->actingAs($student->user)
            ->get(route('student.history'))
            ->assertOk()
            ->assertSee('History')
            ->assertSee('placeholder-heuristic-v0');

        foreach ([UserRole::DepartmentHead, UserRole::Dean, UserRole::Administrator] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('student.history'))
                ->assertForbidden();
        }
    }

    public function test_staff_get_the_fixed_sidebar_without_about_or_faculty(): void
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();

        $html = $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('id="staff-sidebar"', false)
            ->assertSee('lg:pl-64', false)
            ->assertSee('Open navigation')
            ->getContent();

        $sidebar = $this->between($html, 'id="staff-sidebar"', '</aside>');
        foreach (['admin.dashboard', 'admin.users', 'admin.colleges', 'admin.questionnaire', 'admin.psoc', 'admin.interventions', 'admin.audit', 'admin.deletion-requests', 'privacy', 'profile.edit'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $sidebar, "sidebar links to {$route}");
        }
        $this->assertStringNotContainsString('About', $sidebar);
        $this->assertStringNotContainsString('Faculty', $sidebar);

        $head = User::factory()->role(UserRole::DepartmentHead)->create();
        $headHtml = $this->actingAs($head)
            ->get(route('department.dashboard'))
            ->assertOk()
            ->assertSee('id="staff-sidebar"', false)
            ->assertDontSee('aria-label="Student"', false)
            ->getContent();

        $headSidebar = $this->between($headHtml, 'id="staff-sidebar"', '</aside>');
        $this->assertStringContainsString('href="'.route('department.dashboard').'"', $headSidebar);
        $this->assertStringContainsString('href="'.route('department.students').'"', $headSidebar);
        $this->assertStringNotContainsString('Faculty', $headSidebar);
        $this->assertStringNotContainsString('Adviser', $headSidebar);
    }

    public function test_guests_on_the_privacy_page_get_a_sign_in_link_instead_of_a_sidebar(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertDontSee('id="staff-sidebar"', false)
            ->assertSee('href="'.route('login').'"', false);
    }

    public function test_session_messages_render_as_toast_sources(): void
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();

        $this->actingAs($admin)
            ->withSession(['success' => 'Saved for the toast.'])
            ->get(route('admin.users'))
            ->assertSee('data-flash="success"', false)
            ->assertSee('Saved for the toast.');
    }

    public function test_deactivating_a_user_asks_for_confirmation_and_dispatches_a_toast(): void
    {
        $admin = User::factory()->role(UserRole::Administrator)->create();
        $target = User::factory()->role(UserRole::Dean)->create(['name' => 'Dina Dean']);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->assertSeeHtml('data-confirm-title="Deactivate this account?"')
            ->call('toggleActive', $target->id)
            ->assertDispatched('toast', type: 'success', message: 'Dina Dean was deactivated.');

        $this->assertFalse($target->fresh()->is_active);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        if ($from === false) {
            return '';
        }
        $to = strpos($html, $end, $from);

        return substr($html, $from, $to === false ? null : $to - $from);
    }
}
