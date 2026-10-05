<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_open_another_students_record(): void
    {
        $program = Program::factory()->create();
        $viewer = $this->makeStudent($program, '2024-10001');
        $other = $this->makeStudent($program, '2024-10002');

        $this->actingAs($viewer->user)
            ->get(route('students.show', $other))
            ->assertForbidden();
    }

    public function test_faculty_cannot_open_a_student_who_is_not_an_advisee(): void
    {
        $program = Program::factory()->create();
        $faculty = User::factory()->faculty($program)->create();
        $otherFaculty = User::factory()->faculty($program)->create();
        $advisee = $this->makeStudent($program, '2024-20001', $faculty);
        $outsider = $this->makeStudent($program, '2024-20002', $otherFaculty);

        $this->actingAs($faculty)
            ->get(route('students.show', $outsider))
            ->assertForbidden();

        $this->actingAs($faculty)
            ->get(route('students.show', $advisee))
            ->assertOk();
    }

    public function test_department_head_cannot_open_a_student_outside_their_program(): void
    {
        $ownProgram = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $head = User::factory()->departmentHead($ownProgram)->create();
        $outsider = $this->makeStudent($otherProgram, '2024-30001');

        $this->actingAs($head)
            ->get(route('students.show', $outsider))
            ->assertForbidden();

        $this->actingAs($head)
            ->get(route('department.dashboard'))
            ->assertOk();
    }

    public function test_dean_cannot_open_a_student_outside_their_college(): void
    {
        $college = College::factory()->create();
        $otherCollege = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id]);
        $otherProgram = Program::factory()->create(['college_id' => $otherCollege->id]);
        $dean = User::factory()->dean($college)->create();
        $outsider = $this->makeStudent($otherProgram, '2024-40001');
        $insider = $this->makeStudent($program, '2024-40002');

        $this->actingAs($dean)
            ->get(route('students.show', $outsider))
            ->assertForbidden();

        $this->actingAs($dean)
            ->get(route('students.show', $insider))
            ->assertOk();
    }

    public function test_administrator_cannot_use_another_roles_dashboard(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('faculty.dashboard'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_each_demo_role_is_redirected_to_its_dashboard_after_login(): void
    {
        $this->seed();

        $expected = [
            'student@edupredict.test' => route('student.dashboard', absolute: false),
            'faculty@edupredict.test' => route('faculty.dashboard', absolute: false),
            'depthead@edupredict.test' => route('department.dashboard', absolute: false),
            'dean@edupredict.test' => route('dean.dashboard', absolute: false),
            'admin@edupredict.test' => route('admin.dashboard', absolute: false),
        ];

        foreach ($expected as $email => $path) {
            $this->post('/login', [
                'email' => $email,
                'password' => 'Password123!',
            ])->assertRedirect($path);

            $this->post('/logout');
        }
    }

    private function makeStudent(Program $program, string $number, ?User $adviser = null): Student
    {
        $user = User::factory()->role(UserRole::Student)->create();

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
            'adviser_id' => $adviser?->id,
        ]);
    }
}
