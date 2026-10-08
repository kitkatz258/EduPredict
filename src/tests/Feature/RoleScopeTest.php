<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Analytics\DashboardAnalytics;
use App\Livewire\Staff\RecommendedActions;
use App\Livewire\Tables\PredictionHistoryTable;
use App\Livewire\Tables\ScopedStudentsTable;
use App\Models\College;
use App\Models\Department;
use App\Models\GradeReport;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
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

    public function test_legacy_faculty_cannot_sign_in_or_reach_student_data(): void
    {
        $program = Program::factory()->create();
        $faculty = User::factory()->legacyFaculty($program)->create(['email' => 'old.faculty@edupredict.test']);
        $formerAdvisee = $this->makeStudent($program, '2024-20001');
        $formerAdvisee->forceFill(['adviser_id' => $faculty->id])->save();

        $this->post('/login', ['email' => 'old.faculty@edupredict.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($faculty)
            ->get(route('students.show', $formerAdvisee))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        Livewire::actingAs($faculty)->test(ScopedStudentsTable::class)->assertForbidden();
        Livewire::actingAs($faculty)->test(DashboardAnalytics::class)->assertForbidden();
        Livewire::actingAs($faculty)->test(PredictionHistoryTable::class, ['studentId' => $formerAdvisee->id])->assertForbidden();
        Livewire::actingAs($faculty)->test(RecommendedActions::class, ['studentId' => $formerAdvisee->id])->assertForbidden();

        $this->assertFalse(Route::has('faculty.dashboard'));
        $this->assertFalse(Route::has('admin.advisers'));
        $this->assertSame($faculty->id, $formerAdvisee->fresh()->adviser_id, 'legacy adviser link is preserved');
    }

    public function test_department_head_reviews_every_program_in_their_department_only(): void
    {
        $department = Department::factory()->create();
        $programA = Program::factory()->create(['college_id' => $department->college_id, 'department_id' => $department->id]);
        $programB = Program::factory()->create(['college_id' => $department->college_id, 'department_id' => $department->id]);
        $sameCollegeOtherDepartment = Program::factory()->create(['college_id' => $department->college_id]);
        $head = User::factory()->departmentHead($department)->create();

        $inA = $this->makeStudent($programA, '2024-30001', 'Ana Inside');
        $inB = $this->makeStudent($programB, '2024-30002', 'Ben Inside');
        $outsider = $this->makeStudent($sameCollegeOtherDepartment, '2024-30003', 'Oscar Outside');

        $this->actingAs($head)->get(route('students.show', $inA))->assertOk();
        $this->actingAs($head)->get(route('students.show', $inB))->assertOk();
        $this->actingAs($head)->get(route('students.show', $outsider))->assertForbidden();

        Livewire::actingAs($head)->test(ScopedStudentsTable::class)
            ->assertSee('2024-30001')
            ->assertSee('2024-30002')
            ->assertDontSee('2024-30003')
            ->assertDontSee('Oscar Outside');

        Livewire::actingAs($head)->test(PredictionHistoryTable::class, ['studentId' => $outsider->id])->assertForbidden();
        Livewire::actingAs($head)->test(RecommendedActions::class, ['studentId' => $outsider->id])->assertForbidden();

        Livewire::actingAs($head)->test(ScopedStudentsTable::class)
            ->set('filters.program_id', (string) $sameCollegeOtherDepartment->id)
            ->assertDontSee('2024-30003');

        $this->actingAs($head)->get(route('department.dashboard'))->assertOk()->assertSee($department->name);
    }

    public function test_program_narrowed_department_head_cannot_open_a_sibling_program(): void
    {
        $department = Department::factory()->create();
        $own = Program::factory()->create(['college_id' => $department->college_id, 'department_id' => $department->id]);
        $sibling = Program::factory()->create(['college_id' => $department->college_id, 'department_id' => $department->id]);
        $head = User::factory()->departmentHead($own)->create();

        $insider = $this->makeStudent($own, '2024-31001');
        $siblingStudent = $this->makeStudent($sibling, '2024-31002');

        $this->actingAs($head)->get(route('students.show', $insider))->assertOk();
        $this->actingAs($head)->get(route('students.show', $siblingStudent))->assertForbidden();
    }

    public function test_department_head_without_an_assignment_sees_no_students(): void
    {
        $program = Program::factory()->create();
        $student = $this->makeStudent($program, '2024-32001');
        $head = User::factory()->create(['role' => UserRole::DepartmentHead, 'department_id' => null, 'program_id' => null]);

        $this->actingAs($head)->get(route('students.show', $student))->assertForbidden();
        Livewire::actingAs($head)->test(ScopedStudentsTable::class)->assertDontSee('2024-32001');
    }

    public function test_dean_cannot_open_any_individual_student_endpoint(): void
    {
        $college = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id]);
        $dean = User::factory()->dean($college)->create();
        $insider = $this->makeStudent($program, '2024-40002', 'Inez Insider');
        $prediction = Prediction::factory()->create(['student_id' => $insider->id]);
        $report = GradeReport::factory()->create(['student_id' => $insider->id]);

        $this->actingAs($dean)->get(route('students.show', $insider))->assertForbidden();

        Livewire::actingAs($dean)->test(ScopedStudentsTable::class)->assertForbidden();
        Livewire::actingAs($dean)->test(PredictionHistoryTable::class, ['studentId' => $insider->id])->assertForbidden();
        Livewire::actingAs($dean)->test(RecommendedActions::class, ['studentId' => $insider->id])->assertForbidden();

        $this->assertFalse($dean->can('viewAny', Student::class));
        $this->assertFalse($dean->can('view', $insider));
        $this->assertFalse($dean->can('view', $prediction));
        $this->assertFalse($dean->can('view', $report));
        $this->assertSame(0, Student::query()->visibleTo($dean)->count());
    }

    public function test_dean_dashboard_is_aggregate_only_and_limited_to_their_college(): void
    {
        $college = College::factory()->create();
        $otherCollege = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id, 'code' => 'BSIN']);
        $otherProgram = Program::factory()->create(['college_id' => $otherCollege->id, 'code' => 'BSOUT']);
        $dean = User::factory()->dean($college)->create();

        $insider = $this->makeStudent($program, '2024-41001', 'Inez Insider');
        $outsider = $this->makeStudent($otherProgram, '2024-41002', 'Otto Outsider');
        Prediction::factory()->create(['student_id' => $insider->id, 'dropout_risk' => 'high']);
        Prediction::factory()->create(['student_id' => $outsider->id, 'dropout_risk' => 'high']);

        $this->actingAs($dean)->get(route('dean.dashboard'))
            ->assertOk()
            ->assertSee('BSIN')
            ->assertDontSee('BSOUT')
            ->assertDontSee('Inez Insider')
            ->assertDontSee('2024-41001')
            ->assertDontSee(route('students.show', $insider), false);

        Livewire::actingAs($dean)->test(DashboardAnalytics::class)
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['students'] === 1 && $stats['high_risk'] === 1)
            ->set('programId', (string) $otherProgram->id)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['students'] === 1);
    }

    public function test_administrator_cannot_use_another_roles_dashboard(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('dean.dashboard'))
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

        $this->assertSame(0, User::query()->where('role', UserRole::Faculty)->count());
    }

    private function makeStudent(Program $program, string $number, ?string $name = null): Student
    {
        $user = User::factory()->role(UserRole::Student)->create($name ? ['name' => $name] : []);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
        ]);
    }
}
