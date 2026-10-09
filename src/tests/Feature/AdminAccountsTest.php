<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\CreateStaffForm;
use App\Livewire\Admin\InstitutionStudentImport;
use App\Livewire\Tables\UsersTable;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_each_active_staff_role(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create();
        $department = Department::factory()->create(['college_id' => $college->id]);

        Livewire::actingAs($admin)
            ->test(CreateStaffForm::class)
            ->set('name', 'Head Person')
            ->set('email', 'head@staff.test')
            ->set('role', UserRole::DepartmentHead->value)
            ->set('department_id', $department->id)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($admin)
            ->test(CreateStaffForm::class)
            ->set('name', 'Dean Person')
            ->set('email', 'dean@staff.test')
            ->set('role', UserRole::Dean->value)
            ->set('college_id', $college->id)
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($admin)
            ->test(CreateStaffForm::class)
            ->set('name', 'Admin Person')
            ->set('email', 'admin2@staff.test')
            ->set('role', UserRole::Administrator->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'head@staff.test',
            'role' => 'department_head',
            'department_id' => $department->id,
            'college_id' => $college->id,
            'program_id' => null,
            'must_change_password' => 1,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'dean@staff.test', 'role' => 'dean', 'college_id' => $college->id, 'department_id' => null]);
        $this->assertDatabaseHas('users', ['email' => 'admin2@staff.test', 'role' => 'administrator', 'college_id' => null]);
    }

    public function test_faculty_is_not_an_assignable_role(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateStaffForm::class)
            ->call('startCreate')
            ->assertDontSee('<option value="faculty"', false)
            ->set('name', 'Faculty Person')
            ->set('email', 'faculty@staff.test')
            ->set('role', 'faculty')
            ->set('program_id', $program->id)
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'faculty@staff.test']);

        $this->expectException(HttpException::class);
        app(StaffAccountService::class)->create([
            'name' => 'Faculty Person',
            'email' => 'faculty@staff.test',
            'role' => 'faculty',
        ], $admin);
    }

    public function test_department_head_needs_a_department_and_an_in_department_program(): void
    {
        $admin = User::factory()->administrator()->create();
        $department = Department::factory()->create();
        $inside = Program::factory()->create(['college_id' => $department->college_id, 'department_id' => $department->id]);
        $outside = Program::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateStaffForm::class)
            ->set('name', 'No Department')
            ->set('email', 'nodept@staff.test')
            ->set('role', UserRole::DepartmentHead->value)
            ->call('save')
            ->assertHasErrors(['department_id']);

        Livewire::actingAs($admin)
            ->test(CreateStaffForm::class)
            ->set('name', 'Wrong Program')
            ->set('email', 'wrong@staff.test')
            ->set('role', UserRole::DepartmentHead->value)
            ->set('department_id', $department->id)
            ->set('program_id', $outside->id)
            ->call('save')
            ->assertHasErrors(['program_id']);

        Livewire::actingAs($admin)
            ->test(CreateStaffForm::class)
            ->set('name', 'Program Head')
            ->set('email', 'narrow@staff.test')
            ->set('role', UserRole::DepartmentHead->value)
            ->set('department_id', $department->id)
            ->set('program_id', $inside->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('users', ['email' => 'nodept@staff.test']);
        $this->assertDatabaseMissing('users', ['email' => 'wrong@staff.test']);
        $this->assertDatabaseHas('users', ['email' => 'narrow@staff.test', 'department_id' => $department->id, 'program_id' => $inside->id]);
    }

    public function test_non_admin_cannot_create_staff_or_open_user_admin(): void
    {
        $head = User::factory()->departmentHead()->create();

        $this->actingAs($head)->get(route('admin.users'))->assertForbidden();

        Livewire::actingAs($head)
            ->test(CreateStaffForm::class)
            ->assertForbidden();
    }

    public function test_out_of_scope_user_cannot_toggle_active_on_users_table(): void
    {
        $dean = User::factory()->dean()->create();

        Livewire::actingAs($dean)
            ->test(UsersTable::class)
            ->assertForbidden();
    }

    public function test_csv_import_reports_row_errors_and_rejects_programs_outside_the_pilot(): void
    {
        Storage::fake('local');
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['code' => 'BSIS']);
        Program::factory()->legacy()->create(['code' => 'BSBA']);

        $csv = UploadedFile::fake()->createWithContent(
            'students.csv',
            "student_number,last_name,first_name,program_code,year_level\n".
            "2024-55555,Cruz,Ana,BSIS,2\n".
            "2024-55556,Cruz,Ben,NOPE,2\n".
            "2024-55557,Cruz,Cai,BSBA,2\n"
        );

        $this->actingAs($admin)->get(route('admin.institution-students'))->assertOk();

        Livewire::actingAs($admin)
            ->test(InstitutionStudentImport::class)
            ->set('csv', $csv)
            ->call('import')
            ->assertSet('imported', 1)
            ->assertSee('Unknown program code')
            ->assertSee('outside the CLAS pilot scope');

        $this->assertDatabaseHas('institution_students', [
            'student_number' => '2024-55555',
            'program_id' => $program->id,
        ]);
        $this->assertDatabaseMissing('institution_students', ['student_number' => '2024-55557']);
    }

    public function test_admin_can_activate_and_deactivate_users(): void
    {
        $admin = User::factory()->administrator()->create();
        $head = User::factory()->departmentHead()->create();

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->call('toggleActive', $head->id)
            ->assertHasNoErrors();

        $this->assertFalse($head->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->call('toggleActive', $head->id);

        $this->assertTrue($head->fresh()->is_active);
    }

    public function test_users_table_labels_legacy_faculty_accounts(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->legacyFaculty()->create(['name' => 'Old Faculty Member']);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->assertSee('Old Faculty Member')
            ->assertSee('Faculty (legacy)');
    }

    public function test_administrator_cannot_deactivate_self(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->call('toggleActive', $admin->id)
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_staff_must_change_temporary_password_before_using_the_app(): void
    {
        $user = User::factory()->departmentHead()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('department.dashboard'))
            ->assertRedirect(route('password.forced'));

        $this->actingAs($user)
            ->post(route('password.forced.update'), [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
