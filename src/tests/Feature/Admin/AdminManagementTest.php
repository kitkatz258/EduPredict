<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CreateStaffForm;
use App\Livewire\Admin\DepartmentForm;
use App\Livewire\Admin\EditUserForm;
use App\Livewire\Admin\PsocOccupationForm;
use App\Livewire\Analytics\DashboardAnalytics;
use App\Livewire\Tables\DeletionRequestsTable;
use App\Livewire\Tables\UsersTable;
use App\Models\AccountDeletionRequest;
use App\Models\College;
use App\Models\Department;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\PsocOccupation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dean_dashboard_filters_aggregates_and_hides_student_rows(): void
    {
        Carbon::setTestNow('2026-10-09 12:00:00');

        $college = College::factory()->create(['name' => 'College of Liberal Arts and Sciences', 'code' => 'CLAS']);
        $department = Department::factory()->create(['college_id' => $college->id, 'name' => 'Computer Studies']);
        $otherDepartment = Department::factory()->create(['college_id' => $college->id, 'name' => 'Psychology']);
        $outside = Department::factory()->create();
        $programA = Program::factory()->create([
            'college_id' => $college->id,
            'department_id' => $department->id,
            'code' => 'BSIS',
        ]);
        $programB = Program::factory()->create([
            'college_id' => $college->id,
            'department_id' => $otherDepartment->id,
            'code' => 'BSPSY',
        ]);
        $dean = User::factory()->dean($college)->create();
        $recent = Student::factory()->create(['program_id' => $programA->id, 'student_number' => '2024-91001', 'year_level' => 2]);
        $older = Student::factory()->create(['program_id' => $programB->id, 'student_number' => '2024-91002', 'year_level' => 4]);
        Prediction::factory()->create([
            'student_id' => $recent->id,
            'employability_score' => 80,
            'dropout_risk' => 'low',
            'created_at' => '2026-05-01 09:00:00',
        ]);
        Prediction::factory()->create([
            'student_id' => $older->id,
            'employability_score' => 40,
            'dropout_risk' => 'high',
            'created_at' => '2024-05-01 09:00:00',
        ]);

        $this->actingAs($dean)->get(route('dean.dashboard'))
            ->assertOk()
            ->assertSee('CLAS dashboard')
            ->assertSee('Department')
            ->assertSee('Program')
            ->assertSee('Year')
            ->assertSee('Period')
            ->assertSee('Computer Studies')
            ->assertDontSee('2024-91001')
            ->assertDontSee('2024-91002')
            ->assertDontSee(route('students.show', $recent), false);

        Livewire::actingAs($dean)->test(DashboardAnalytics::class)
            ->set('departmentId', (string) $department->id)
            ->assertSee('Average employability is 80.0')
            ->assertDontSee('BSPSY')
            ->set('departmentId', (string) $outside->id)
            ->assertSee('Average employability is 60.0')
            ->set('period', 'this_year')
            ->assertSee('Average employability is 80.0')
            ->assertSee('Students in period');
    }

    public function test_admin_dashboard_does_not_render_the_student_table(): void
    {
        $admin = User::factory()->administrator()->create();
        $student = Student::factory()->create(['student_number' => '2024-92001']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Eligible students are managed on their own page.')
            ->assertDontSee('2024-92001')
            ->assertDontSee($student->user->name);
    }

    public function test_users_page_uses_role_filters_and_add_edit_modals(): void
    {
        $admin = User::factory()->administrator()->create();
        $head = User::factory()->departmentHead()->create(['name' => 'Head Person', 'email' => 'head-edit@staff.test']);
        $student = Student::factory()->create();
        User::factory()->legacyFaculty()->create(['name' => 'Old Faculty Member']);

        $this->actingAs($admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Add user')
            ->assertSee('Eligible students')
            ->assertSee('Activity Log')
            ->assertDontSee('Audit log')
            ->assertDontSee('Institution students')
            ->assertDontSee('id="staff-name"', false)
            ->assertDontSee('>Faculty<', false);

        Livewire::actingAs($admin)->test(CreateStaffForm::class)
            ->assertDontSee('id="staff-name"', false)
            ->call('startCreate')
            ->assertSee('Add user')
            ->assertDontSee('<option value="faculty"', false);

        Livewire::actingAs($admin)->test(UsersTable::class)
            ->assertSee('Old Faculty Member')
            ->assertSee('Head Person')
            ->call('setRoleFilter', 'department_head')
            ->assertSee('Head Person')
            ->assertDontSee('Old Faculty Member')
            ->assertDontSee($student->user->name)
            ->call('setRoleFilter', 'faculty')
            ->assertSee('Old Faculty Member');

        Livewire::actingAs($admin)->test(EditUserForm::class)
            ->call('open', $head->id)
            ->set('name', 'Updated Head')
            ->set('resetPassword', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Temporary password');

        $password = Livewire::actingAs($admin)->test(EditUserForm::class)
            ->call('open', $head->id)
            ->set('resetPassword', true)
            ->call('save')
            ->get('temporaryPassword');

        $this->assertSame('Updated Head', $head->fresh()->name);
        $this->assertIsString($password);
        $this->assertTrue(Hash::check((string) $password, (string) $head->fresh()->password));
        $this->assertTrue($head->fresh()->must_change_password);

        Livewire::actingAs($admin)->test(EditUserForm::class)
            ->call('open', $student->user_id)
            ->set('role', 'administrator')
            ->set('name', 'Still Student')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('student', $student->user->fresh()->role->value);
        $this->assertSame('Still Student', $student->user->fresh()->name);
    }

    public function test_academic_structure_questionnaire_psoc_and_interventions_open_in_modals(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create();
        $occupation = PsocOccupation::factory()->create(['title' => 'Visible occupation', 'major_group' => 'Testing']);

        $this->actingAs($admin)->get(route('admin.colleges'))
            ->assertOk()
            ->assertSee('Add college')
            ->assertSee('Add department')
            ->assertSee('Add program')
            ->assertDontSee('id="college-name"', false);

        Livewire::actingAs($admin)->test(DepartmentForm::class)
            ->set('collegeId', $college->id)
            ->set('name', 'Department of Testing')
            ->set('code', 'dot')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Department added.');

        $this->assertDatabaseHas('departments', ['code' => 'DOT', 'college_id' => $college->id]);

        $this->actingAs($admin)->get(route('admin.questionnaire'))
            ->assertOk()
            ->assertSee('Add item')
            ->assertDontSee('id="item-text"', false);

        $this->actingAs($admin)->get(route('admin.psoc'))
            ->assertOk()
            ->assertSee('Add occupation')
            ->assertDontSee('id="psoc-title"', false);

        Livewire::actingAs($admin)->test(PsocOccupationForm::class)
            ->call('startView', $occupation->id)
            ->assertSet('readOnly', true)
            ->assertSet('title', 'Visible occupation')
            ->assertSee('View occupation')
            ->assertDontSee('Save occupation')
            ->set('title', 'Changed in view')
            ->call('save');

        $this->assertSame('Visible occupation', $occupation->fresh()->title);

        $this->actingAs($admin)->get(route('admin.interventions'))
            ->assertOk()
            ->assertSee('Add intervention')
            ->assertDontSee('id="intervention-title"', false);
    }

    public function test_activity_log_and_deletion_requests_use_the_new_labels_and_review_modal(): void
    {
        $admin = User::factory()->administrator()->create();
        $request = AccountDeletionRequest::factory()->create(['reason' => 'Please close this login.']);

        $this->actingAs($admin)->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('Activity Log')
            ->assertDontSee('Audit log');

        $this->actingAs($admin)->get(route('admin.deletion-requests'))
            ->assertOk()
            ->assertSee('account deletion requests')
            ->assertSee('Pending')
            ->assertSee('Approved')
            ->assertSee('Rejected')
            ->assertSee('All');

        $this->actingAs($admin)->get(route('admin.institution-students'))
            ->assertOk()
            ->assertSee('Eligible students')
            ->assertSee('has not already been claimed');

        Livewire::actingAs($admin)->test(DeletionRequestsTable::class)
            ->assertSee('Please close this login.')
            ->call('setStatus', 'approved')
            ->assertDontSee('Please close this login.')
            ->call('setStatus', 'pending')
            ->call('openReview', $request->id)
            ->assertSee('Review account deletion request')
            ->assertSee('Please close this login.');
    }
}
