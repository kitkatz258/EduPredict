<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\InstitutionStudentForm;
use App\Livewire\Admin\InstitutionStudentImport;
use App\Livewire\Tables\CollegesTable;
use App\Livewire\Tables\InstitutionStudentsTable;
use App\Livewire\Tables\InterventionsTable;
use App\Models\College;
use App\Models\InstitutionStudent;
use App\Models\Intervention;
use App\Models\Program;
use App\Models\User;
use App\Services\Academic\ClasStructureSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class Milestone31Test extends TestCase
{
    use RefreshDatabase;

    public function test_new_eligible_student_years_must_be_integers_from_1_to_4(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create();
        $existing = InstitutionStudent::factory()->create([
            'program_id' => $program->id,
            'student_number' => '2020-00005',
            'year_level' => 5,
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->set('studentNumber', '2024-44001')
            ->set('lastName', 'Ramos')
            ->set('firstName', 'Liza')
            ->set('programId', $program->id)
            ->set('yearLevel', 5)
            ->call('save')
            ->assertHasErrors('yearLevel');

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->set('studentNumber', '2024-44001')
            ->set('lastName', 'Ramos')
            ->set('firstName', 'Liza')
            ->set('programId', $program->id)
            ->set('yearLevel', 3.5)
            ->call('save')
            ->assertHasErrors('yearLevel');

        $this->assertDatabaseMissing('institution_students', ['student_number' => '2024-44001']);
        $this->assertSame(5, $existing->fresh()->year_level);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentsTable::class)
            ->assertSee('2020-00005')
            ->assertSee('left unchanged');

        $csv = UploadedFile::fake()->createWithContent(
            'years.csv',
            "student_number,last_name,first_name,program_code,year_level\n".
            "2024-44002,Cruz,Ana,{$program->code},4\n".
            "2024-44003,Cruz,Ben,{$program->code},6\n".
            "2024-44004,Cruz,Cai,{$program->code},3.5\n".
            "2024-44005,Cruz,Dina,{$program->code},\n"
        );

        Livewire::actingAs($admin)
            ->test(InstitutionStudentImport::class)
            ->set('csv', $csv)
            ->call('import')
            ->assertSet('imported', 1)
            ->assertSee('Year level must be an integer from 1 to 4.');

        $this->assertDatabaseHas('institution_students', ['student_number' => '2024-44002', 'year_level' => 4]);
        $this->assertDatabaseMissing('institution_students', ['student_number' => '2024-44003']);
        $this->assertSame(5, $existing->fresh()->year_level);
    }

    public function test_intervention_activation_persists_and_keeps_the_risk_field(): void
    {
        $admin = User::factory()->administrator()->create();
        $intervention = Intervention::factory()->create([
            'code' => 'library_hours',
            'min_risk_level' => 'high',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(InterventionsTable::class)
            ->assertSee('Eligible from risk level')
            ->assertSee('Moderate applies to Moderate and High')
            ->call('setInterventionActive', $intervention->id)
            ->assertSee('Inactive');

        $this->assertFalse($intervention->fresh()->is_active);
        $this->assertSame('high', $intervention->fresh()->min_risk_level);

        Livewire::actingAs($admin)
            ->test(InterventionsTable::class)
            ->call('setInterventionActive', $intervention->id);

        $this->assertTrue($intervention->fresh()->is_active);
    }

    public function test_clas_college_explains_why_it_stays_active_and_the_deletion_title_is_specific(): void
    {
        $admin = User::factory()->administrator()->create();
        College::factory()->create([
            'code' => ClasStructureSynchronizer::COLLEGE_CODE,
            'name' => 'College of Liberal Arts and Sciences',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CollegesTable::class)
            ->assertSee('The CLAS pilot college stays active');

        $this->actingAs($admin)
            ->get(route('admin.deletion-requests'))
            ->assertOk()
            ->assertSee('Account deletion requests —', false);
    }
}
