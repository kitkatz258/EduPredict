<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Department;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\Academic\ClasStructureSynchronizer;
use Database\Seeders\CollegeProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClasStructureTest extends TestCase
{
    use RefreshDatabase;

    private const CLAS_PROGRAMS = [
        'BA Communication',
        'Bachelor of Public Administration',
        'Bachelor of Science in Computer Science',
        'Bachelor of Science in Entertainment and Multimedia Computing',
        'Bachelor of Science in Information Systems',
        'Bachelor of Science in Information Technology',
        'Bachelor of Science in Mathematics',
        'Bachelor of Science in Psychology',
    ];

    public function test_seeded_structure_is_clas_with_the_eight_pilot_programs(): void
    {
        $this->seed(CollegeProgramSeeder::class);

        $clas = College::query()->where('code', 'CLAS')->sole();
        $this->assertSame('College of Liberal Arts and Sciences', $clas->name);
        $this->assertSame(1, College::query()->count());

        $programs = Program::query()->with('department')->orderBy('name')->get();
        $this->assertSame(self::CLAS_PROGRAMS, $programs->pluck('name')->all());

        foreach ($programs as $program) {
            $this->assertTrue($program->is_active);
            $this->assertSame($clas->id, $program->college_id);
            $this->assertNotNull($program->department, "{$program->code} has a department");
            $this->assertSame($clas->id, $program->department->college_id, 'department and program share the college');
        }

        $computerStudies = Department::query()->where('code', 'CLAS-CS')->sole();
        $this->assertEqualsCanonicalizing(
            ['BSCS', 'BSEMC', 'BSIS', 'BSIT'],
            $computerStudies->programs()->pluck('code')->all(),
            'a department may hold several programs',
        );
    }

    public function test_sync_preserves_legacy_rows_and_moves_them_into_clas(): void
    {
        $ccs = College::factory()->create(['code' => 'CCS', 'name' => 'College of Computer Studies']);
        $cla = College::factory()->create(['code' => 'CLA', 'name' => 'College of Liberal Arts']);
        $cba = College::factory()->create(['code' => 'CBA', 'name' => 'College of Business Administration']);
        $bsis = $this->legacyProgram($ccs, 'BSIS');
        $bsit = $this->legacyProgram($ccs, 'BSIT');
        $comm = $this->legacyProgram($cla, 'ABCOMM');
        $bsba = $this->legacyProgram($cba, 'BSBA');

        $dean = User::factory()->create(['role' => UserRole::Dean, 'college_id' => $ccs->id]);
        $cbaDean = User::factory()->create(['role' => UserRole::Dean, 'college_id' => $cba->id]);
        $head = User::factory()->create(['role' => UserRole::DepartmentHead, 'college_id' => $ccs->id, 'program_id' => $bsis->id]);
        $faculty = User::factory()->create(['role' => UserRole::Faculty, 'college_id' => $ccs->id, 'program_id' => $bsis->id]);

        $bsisStudent = Student::factory()->create(['program_id' => $bsis->id]);
        $bsisStudent->forceFill(['adviser_id' => $faculty->id])->save();
        $bsbaStudent = Student::factory()->create(['program_id' => $bsba->id]);
        $prediction = Prediction::factory()->create(['student_id' => $bsbaStudent->id]);

        $sync = app(ClasStructureSynchronizer::class);
        $sync->sync();
        $sync->sync();

        $clas = College::query()->where('code', 'CLAS')->sole();
        $this->assertSame($cla->id, $clas->id, 'the CLA placeholder row becomes CLAS in place');
        $this->assertFalse($ccs->fresh()->is_active);
        $this->assertFalse($cba->fresh()->is_active);
        $this->assertSame(3, College::query()->count(), 'no college is deleted');

        foreach ([$bsis, $bsit, $comm] as $program) {
            $fresh = $program->fresh();
            $this->assertSame($clas->id, $fresh->college_id, "{$fresh->code} moves to CLAS with the same id");
            $this->assertTrue($fresh->is_active);
            $this->assertNotNull($fresh->department_id);
        }
        $this->assertSame('BA Communication', $comm->fresh()->name);
        $this->assertFalse($bsba->fresh()->is_active);
        $this->assertSame($cba->id, $bsba->fresh()->college_id);
        $this->assertSame(9, Program::query()->count(), '4 legacy + 5 new CLAS programs, none deleted');
        $this->assertSame(5, Department::query()->count(), 'running twice does not duplicate');

        $this->assertSame($clas->id, $dean->fresh()->college_id, 'dean of the emptied CCS follows its programs');
        $this->assertSame($cba->id, $cbaDean->fresh()->college_id);

        $head = $head->fresh();
        $this->assertSame($bsis->id, $head->program_id, 'existing head keeps the narrower program scope');
        $this->assertSame($bsis->fresh()->department_id, $head->department_id);
        $this->assertSame($clas->id, $head->college_id);

        $this->assertSame(UserRole::Faculty, $faculty->fresh()->role);
        $this->assertSame($faculty->id, $bsisStudent->fresh()->adviser_id);
        $this->assertNotNull($bsbaStudent->fresh());
        $this->assertNotNull($prediction->fresh());
    }

    public function test_legacy_programs_drop_out_of_admin_and_dean_aggregates_but_stay_listable_for_admin(): void
    {
        $this->seed(CollegeProgramSeeder::class);
        $clas = College::query()->where('code', 'CLAS')->sole();
        $legacyCollege = College::factory()->create(['is_active' => false]);
        $legacy = Program::factory()->legacy()->create(['college_id' => $legacyCollege->id, 'code' => 'BSOLD']);
        $bsis = Program::query()->where('code', 'BSIS')->sole();

        $inPilot = Student::factory()->create(['program_id' => $bsis->id]);
        $legacyStudent = Student::factory()->create(['program_id' => $legacy->id]);

        $admin = User::factory()->administrator()->create();
        $dean = User::factory()->dean($clas)->create();

        $this->assertEqualsCanonicalizing([$inPilot->id], Student::query()->aggregatableBy($admin)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$inPilot->id], Student::query()->aggregatableBy($dean)->pluck('id')->all());
        $this->assertTrue($admin->can('view', $legacyStudent), 'admins can still open legacy records');
    }

    private function legacyProgram(College $college, string $code): Program
    {
        $id = DB::table('programs')->insertGetId([
            'college_id' => $college->id,
            'department_id' => null,
            'name' => 'Legacy '.$code,
            'code' => $code,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Program::query()->findOrFail($id);
    }
}
