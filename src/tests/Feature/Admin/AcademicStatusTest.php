<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CollegeForm;
use App\Livewire\Admin\DepartmentForm;
use App\Livewire\Admin\ProgramForm;
use App\Livewire\Tables\AuditLogsTable;
use App\Livewire\Tables\DepartmentsTable;
use App\Livewire\Tables\ProgramsTable;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Department;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\Academic\AcademicActivation;
use App\Services\Academic\ClasStructureSynchronizer;
use App\Support\AuditRecordLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_clas_college_and_the_last_active_program_cannot_be_turned_off(): void
    {
        $clas = College::factory()->create([
            'code' => ClasStructureSynchronizer::COLLEGE_CODE,
            'name' => ClasStructureSynchronizer::COLLEGE_NAME,
            'is_active' => true,
        ]);
        $legacy = College::factory()->create(['code' => 'CBA', 'is_active' => false]);
        $only = Program::factory()->create(['college_id' => $clas->id, 'is_active' => true]);
        $student = Student::factory()->create(['program_id' => $only->id]);
        $prediction = Prediction::factory()->create(['student_id' => $student->id]);
        $activation = app(AcademicActivation::class);

        try {
            $activation->setCollegeActive($clas, false);
            $this->fail('CLAS deactivation should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue($clas->fresh()->is_active);
        }

        try {
            $activation->setCollegeActive($legacy, true);
            $this->fail('A non-CLAS college should stay outside the pilot.');
        } catch (ValidationException) {
            $this->assertFalse($legacy->fresh()->is_active);
        }

        try {
            $activation->setProgramActive($only, false);
            $this->fail('The last active program should stay active.');
        } catch (ValidationException) {
            $this->assertTrue($only->fresh()->is_active);
        }

        $this->assertSame($only->id, $student->fresh()->program_id);
        $this->assertNotNull($prediction->fresh());
    }

    public function test_deactivating_a_program_keeps_students_and_predictions(): void
    {
        $admin = User::factory()->administrator()->create();
        $keep = Program::factory()->create(['is_active' => true, 'code' => 'KEEP']);
        $drop = Program::factory()->create(['is_active' => true, 'code' => 'DROP']);
        $student = Student::factory()->create(['program_id' => $drop->id]);
        $prediction = Prediction::factory()->create(['student_id' => $student->id]);

        Livewire::actingAs($admin)
            ->test(ProgramsTable::class)
            ->call('setProgramActive', $drop->id)
            ->assertHasNoErrors();

        $this->assertFalse($drop->fresh()->is_active);
        $this->assertTrue($keep->fresh()->is_active);
        $this->assertSame($drop->id, $student->fresh()->program_id);
        $this->assertSame($prediction->id, Prediction::query()->find($prediction->id)?->id);
        $this->assertSame(1, Program::query()->whereKey($drop->id)->count());

        Livewire::actingAs($admin)
            ->test(ProgramsTable::class)
            ->call('setProgramActive', $drop->id)
            ->assertHasNoErrors();

        $this->assertFalse($drop->fresh()->is_active);

        Livewire::actingAs(User::factory()->departmentHead()->create())
            ->test(ProgramsTable::class)
            ->assertForbidden();
    }

    public function test_the_last_clas_department_stays_active_and_another_can_change(): void
    {
        $admin = User::factory()->administrator()->create();
        $clas = College::factory()->create(['code' => ClasStructureSynchronizer::COLLEGE_CODE, 'is_active' => true]);
        $keep = Department::factory()->create(['college_id' => $clas->id, 'is_active' => true, 'code' => 'KEEP-D']);
        $drop = Department::factory()->create(['college_id' => $clas->id, 'is_active' => true, 'code' => 'DROP-D']);
        $program = Program::factory()->create([
            'college_id' => $clas->id,
            'department_id' => $drop->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(DepartmentsTable::class)
            ->call('setDepartmentActive', $drop->id);

        $this->assertFalse($drop->fresh()->is_active);
        $this->assertSame($drop->id, $program->fresh()->department_id);

        Livewire::actingAs($admin)
            ->test(DepartmentsTable::class)
            ->call('setDepartmentActive', $keep->id);

        $this->assertTrue($keep->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(DepartmentForm::class)
            ->call('startEdit', $keep->id)
            ->set('isActive', false)
            ->call('save')
            ->assertHasErrors('is_active');

        $this->assertTrue($keep->fresh()->is_active);
    }

    public function test_new_colleges_and_programs_stay_outside_the_pilot(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create();

        Livewire::actingAs($admin)
            ->test(CollegeForm::class)
            ->call('startCreate')
            ->set('name', 'College of Extra')
            ->set('code', 'EXT')
            ->call('confirmSave')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('colleges', ['code' => 'EXT', 'is_active' => 0]);

        Livewire::actingAs($admin)
            ->test(ProgramForm::class)
            ->call('startCreate')
            ->set('collegeId', $college->id)
            ->set('name', 'Bachelor of Extra')
            ->set('code', 'BSEX')
            ->call('confirmSave')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('programs', ['code' => 'BSEX', 'is_active' => 0]);
    }

    public function test_activity_descriptions_are_readable_and_the_filter_keeps_the_stored_action(): void
    {
        $admin = User::factory()->administrator()->create();
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'meta' => [],
            'ip' => '10.0.0.8',
        ]);

        $this->assertSame('Signed in', AuditRecordLabel::action('login'));
        $this->assertSame('Missing subject', AuditRecordLabel::action('missing_subject'));

        Livewire::actingAs($admin)
            ->test(AuditLogsTable::class)
            ->assertSee('Signed in')
            ->assertSee('value="login"', false)
            ->set('actionFilter', 'login')
            ->assertSee('Signed in')
            ->assertDontSee('Eligible students imported');
    }
}
