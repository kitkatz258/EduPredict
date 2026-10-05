<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\AdviserAssignment;
use App\Livewire\Admin\CreateStaffForm;
use App\Livewire\Admin\InstitutionStudentImport;
use App\Livewire\Tables\UsersTable;
use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_each_staff_role(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id]);

        foreach ([
            UserRole::Faculty,
            UserRole::DepartmentHead,
            UserRole::Dean,
            UserRole::Administrator,
        ] as $role) {
            Livewire::actingAs($admin)
                ->test(CreateStaffForm::class)
                ->set('name', $role->value.' Person')
                ->set('email', $role->value.'@staff.test')
                ->set('role', $role->value)
                ->set('college_id', $college->id)
                ->set('program_id', $program->id)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('users', [
                'email' => $role->value.'@staff.test',
                'role' => $role->value,
                'must_change_password' => 1,
            ]);
        }
    }

    public function test_non_admin_cannot_create_staff_or_open_user_admin(): void
    {
        $faculty = User::factory()->faculty()->create();

        $this->actingAs($faculty)->get(route('admin.users'))->assertForbidden();

        Livewire::actingAs($faculty)
            ->test(CreateStaffForm::class)
            ->assertForbidden();
    }

    public function test_out_of_scope_user_cannot_toggle_active_on_users_table(): void
    {
        $faculty = User::factory()->faculty()->create();

        Livewire::actingAs($faculty)
            ->test(UsersTable::class)
            ->assertForbidden();
    }

    public function test_csv_import_reports_row_errors(): void
    {
        Storage::fake('local');
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['code' => 'BSIS']);

        $csv = UploadedFile::fake()->createWithContent(
            'students.csv',
            "student_number,last_name,first_name,program_code,year_level\n".
            "2024-55555,Cruz,Ana,BSIS,2\n".
            "2024-55556,Cruz,Ben,NOPE,2\n"
        );

        $this->actingAs($admin)->get(route('admin.institution-students'))->assertOk();

        Livewire::actingAs($admin)
            ->test(InstitutionStudentImport::class)
            ->set('csv', $csv)
            ->call('import')
            ->assertSet('imported', 1)
            ->assertSee('Unknown program code');

        $this->assertDatabaseHas('institution_students', [
            'student_number' => '2024-55555',
            'program_id' => $program->id,
        ]);
    }

    public function test_admin_can_activate_and_deactivate_users(): void
    {
        $admin = User::factory()->administrator()->create();
        $faculty = User::factory()->faculty()->create();

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->call('toggleActive', $faculty->id)
            ->assertHasNoErrors();

        $this->assertFalse($faculty->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->call('toggleActive', $faculty->id);

        $this->assertTrue($faculty->fresh()->is_active);
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
        $user = User::factory()->faculty()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('faculty.dashboard'))
            ->assertRedirect(route('password.forced'));

        $this->actingAs($user)
            ->post(route('password.forced.update'), [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_admin_can_assign_adviser_to_one_or_many_students(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create();
        $faculty = User::factory()->faculty($program)->create();
        $one = Student::factory()->create(['program_id' => $program->id]);
        $two = Student::factory()->create(['program_id' => $program->id]);

        Livewire::actingAs($admin)
            ->test(AdviserAssignment::class)
            ->set('facultyId', $faculty->id)
            ->set('studentIds', [$one->id])
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertSame($faculty->id, $one->fresh()->adviser_id);

        Livewire::actingAs($admin)
            ->test(AdviserAssignment::class)
            ->set('facultyId', $faculty->id)
            ->set('studentIds', [$one->id, $two->id])
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertSame($faculty->id, $two->fresh()->adviser_id);
    }

    public function test_non_admin_cannot_assign_advisers(): void
    {
        $faculty = User::factory()->faculty()->create();

        $this->actingAs($faculty)->get(route('admin.advisers'))->assertForbidden();

        Livewire::actingAs($faculty)
            ->test(AdviserAssignment::class)
            ->assertForbidden();
    }
}
