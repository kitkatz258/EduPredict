<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\PredictionGrantForm;
use App\Livewire\Student\QuestionnaireForm;
use App\Livewire\Tables\AuditLogsTable;
use App\Livewire\Tables\UsersTable;
use App\Models\AuditLog;
use App\Models\Prediction;
use App\Models\PredictionAttemptGrant;
use App\Models\Student;
use App\Models\User;
use App\Services\Prediction\PredictionAttemptPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Milestone38Test extends TestCase
{
    use RefreshDatabase;

    public function test_dean_and_department_head_cannot_open_admin_endpoints(): void
    {
        $dean = User::factory()->dean()->create();
        $head = User::factory()->departmentHead()->create();

        foreach (['admin.users', 'admin.departments', 'admin.programs', 'admin.prediction-grants', 'admin.audit'] as $route) {
            $this->actingAs($dean)->get(route($route))->assertForbidden();
            $this->actingAs($head)->get(route($route))->assertForbidden();
        }

        Livewire::actingAs($dean)->test(PredictionGrantForm::class)->assertForbidden();
        Livewire::actingAs($head)->test(AuditLogsTable::class)->assertForbidden();
    }

    public function test_users_default_to_newest_first_with_dropdown_filters(): void
    {
        $admin = User::factory()->administrator()->create(['name' => 'Older Admin']);
        $newer = User::factory()->departmentHead()->create(['name' => 'Newer Head']);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->assertSet('sortField', 'id')
            ->assertSet('sortDirection', 'desc')
            ->assertSee('user-role-')
            ->assertDontSee('setRoleFilter')
            ->assertSeeInOrder(['Newer Head', 'Older Admin']);

        $this->assertTrue($newer->id > $admin->id);
    }

    public function test_activity_log_filters_by_validated_date_range_without_household_contents(): void
    {
        $admin = User::factory()->administrator()->create();
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'meta' => [],
            'ip' => '10.0.0.1',
            'created_at' => Carbon::parse('2026-10-01 08:00:00', config('app.timezone')),
        ]);
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'meta' => ['household_size' => 'SECRET-HOUSEHOLD'],
            'ip' => '10.0.0.2',
            'created_at' => Carbon::parse('2026-10-20 08:00:00', config('app.timezone')),
        ]);

        Livewire::actingAs($admin)
            ->test(AuditLogsTable::class)
            ->set('startDate', '2026-10-15')
            ->set('endDate', '2026-10-21')
            ->assertSee('10.0.0.2')
            ->assertDontSee('10.0.0.1')
            ->assertDontSee('SECRET-HOUSEHOLD');
    }

    public function test_an_administrator_can_grant_extra_prediction_slots_without_creating_a_prediction(): void
    {
        $admin = User::factory()->administrator()->create();
        $student = Student::factory()->create(['student_number' => '2024-33333']);

        Livewire::actingAs($admin)
            ->test(PredictionGrantForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-33333')
            ->set('manilaDate', '2026-10-26')
            ->set('extraAttempts', 2)
            ->set('reason', 'Defense demonstration slot')
            ->call('save')
            ->assertHasNoErrors();

        $grant = PredictionAttemptGrant::query()->sole();
        $this->assertSame($student->id, $grant->student_id);
        $this->assertSame(2, $grant->extra_attempts);
        $this->assertSame($admin->id, $grant->granted_by);
        $this->assertSame(0, Prediction::query()->count());
        $this->assertSame(
            2,
            app(PredictionAttemptPolicy::class)->extraAttempts($student, Carbon::parse('2026-10-26 09:00:00', 'Asia/Manila')),
        );
        $this->assertTrue(AuditLog::query()->where('action', 'prediction_grant_created')->exists());
    }

    public function test_questionnaire_wizard_shows_answered_and_saved_progress(): void
    {
        $student = Student::factory()->create();

        Livewire::actingAs($student->user)
            ->test(QuestionnaireForm::class)
            ->call('openWizard')
            ->assertSee('This section ·')
            ->assertSee('Overall ·')
            ->assertSee('sections saved')
            ->assertSee('aria-label="This section"', false)
            ->assertSee('aria-label="Overall"', false)
            ->assertSee('aria-label="Questionnaire sections"', false);
    }

    public function test_login_page_uses_the_split_layout(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('auth-split', false)
            ->assertSee('University of Caloocan City')
            ->assertDontSee('Create Account')
            ->assertDontSee('Spacer');
    }

    public function test_faculty_is_not_offered_as_a_creatable_role(): void
    {
        $admin = User::factory()->administrator()->create();

        $html = $this->actingAs($admin)->get(route('admin.users'))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="faculty"', $html);
        $this->assertContains(UserRole::Student, UserRole::active());
        $this->assertNotContains(UserRole::Faculty, UserRole::staffAssignable());
    }
}
