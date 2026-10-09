<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Student\SkillsExperienceSection;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class UxRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_role_routes_stay_in_scope(): void
    {
        $this->assertFalse(Route::has('about'));
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/about')->assertNotFound();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Student number')
            ->assertSee('Forgot password?')
            ->assertDontSee('Faculty');

        $student = Student::factory()->create();
        $head = User::factory()->departmentHead()->create();
        $dean = User::factory()->dean()->create();
        $admin = User::factory()->administrator()->create();

        $this->assertRoutesOk($student->user, [
            'student.dashboard',
            'student.assessment',
            'student.history',
            'profile.edit',
            'privacy',
        ]);
        $this->assertRoutesForbidden($student->user, [
            'department.dashboard',
            'department.students',
            'dean.dashboard',
            'admin.dashboard',
            'admin.users',
        ]);

        $this->assertRoutesOk($head, ['department.dashboard', 'department.students', 'profile.edit']);
        $this->assertRoutesForbidden($head, ['student.dashboard', 'dean.dashboard', 'admin.users', 'student.history']);

        $this->assertRoutesOk($dean, ['dean.dashboard', 'profile.edit']);
        $this->assertRoutesForbidden($dean, ['department.students', 'admin.dashboard', 'student.dashboard', 'students.show']);

        $this->actingAs($dean)->get(route('students.show', $student))->assertForbidden();

        $this->assertRoutesOk($admin, [
            'admin.dashboard',
            'admin.users',
            'admin.institution-students',
            'admin.colleges',
            'admin.questionnaire',
            'admin.psoc',
            'admin.interventions',
            'admin.audit',
            'admin.deletion-requests',
        ]);

        $deanPage = $this->actingAs($dean)->get(route('dean.dashboard'));
        $deanPage->assertOk()->assertDontSee($student->student_number)->assertDontSee($student->user->name);
    }

    public function test_student_pages_keep_the_consolidated_navigation_and_loading_hooks(): void
    {
        $student = Student::factory()->create();

        $assessment = $this->actingAs($student->user)
            ->get(route('student.assessment'))
            ->assertOk()
            ->assertSee('Assessment workflow progress')
            ->assertSee('wire:loading', false)
            ->assertDontSee('profile completeness', false);

        $nav = $this->between($assessment->getContent(), '<header class="sticky top-0 z-30', '</header>');
        foreach (['My Grades', 'Career Matches', 'About', 'Faculty', 'Skills & Experience'] as $label) {
            $this->assertStringNotContainsString($label, $nav);
        }

        $this->actingAs($student->user)
            ->get(route('student.history'))
            ->assertOk()
            ->assertSee('History')
            ->assertSee('role="status"', false);

        Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('openCreate', 'skill')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-label="Close dialog"');
    }

    public function test_profile_status_tokens_are_not_toasted_as_raw_text(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['status' => 'profile-updated'])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('data-flash="success"', false)
            ->assertSee('Saved.');
    }

    /**
     * @param  list<string>  $routes
     */
    private function assertRoutesOk(User $user, array $routes): void
    {
        foreach ($routes as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    /**
     * @param  list<string>  $routes
     */
    private function assertRoutesForbidden(User $user, array $routes): void
    {
        foreach ($routes as $route) {
            $params = $route === 'students.show' ? [Student::factory()->create()] : [];
            $this->actingAs($user)->get(route($route, $params))->assertForbidden();
        }
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
