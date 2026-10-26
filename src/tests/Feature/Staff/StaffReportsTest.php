<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\UserRole;
use App\Livewire\Staff\DeanReport;
use App\Livewire\Staff\DepartmentReport;
use App\Livewire\Tables\ScopedStudentsTable;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_reports_are_scoped_escaped_and_audited(): void
    {
        $college = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id, 'code' => 'PROG-A']);
        $other = Program::factory()->create(['code' => 'PROG-C']);
        $head = User::factory()->departmentHead($program)->create();
        $dean = User::factory()->dean($college)->create();
        $inside = $this->student($program, 'S-HIGH', '=HYPERLINK("http://evil")', 3);
        $low = $this->student($program, 'S-LOW', 'Low Safe', 2);
        $outside = $this->student($other, 'S-OUT', 'Outside', 4);
        $this->prediction($inside, '2026-05-15 09:00:00', 40, 'high', 'program_fit');
        $this->prediction($low, '2026-01-15 09:00:00', 80, 'low', 'none');
        $this->prediction($outside, '2026-06-01 09:00:00', 99, 'high', 'none');

        $page = $this->actingAs($head)
            ->get(route('department.reports'))
            ->assertOk()
            ->assertSee('S-HIGH')
            ->assertSee('S-LOW')
            ->assertDontSee('S-OUT')
            ->assertSee('Asia/Manila')
            ->assertSee('qualitative and has no percentage')
            ->assertDontSee('id="staff-sidebar"', false);

        $this->assertStringNotContainsString(route('students.show', $inside), $page->getContent());

        $exported = Livewire::actingAs($head)
            ->test(DepartmentReport::class)
            ->set('dropoutRisk', 'high')
            ->call('export');

        $filename = (string) data_get($exported->effects, 'download.name');
        $this->assertMatchesRegularExpression('/^edupredict-department-students-\d{8}-\d{4}\.csv$/', $filename);

        $content = base64_decode((string) data_get($exported->effects, 'download.content'));
        $this->assertStringContainsString('S-HIGH', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString('placeholder heuristic', strtolower($content));
        $this->assertStringContainsString('Asia/Manila', $content);
        $this->assertStringContainsString('qualitative and has no percentage', $content);
        $this->assertStringNotContainsString('S-LOW', $content);
        $this->assertStringNotContainsString('S-OUT', $content);
        $this->assertDoesNotMatchRegularExpression('/(?<!\')=HYPERLINK/', $content);

        $log = AuditLog::query()->where('action', 'students.export')->sole();
        $this->assertSame($head->id, $log->user_id);
        $this->assertSame('department_reports', $log->meta['surface']);
        $this->assertSame(1, $log->meta['rows']);
        $this->assertSame('high', $log->meta['dropout_risk']);

        Livewire::actingAs($dean)->test(DepartmentReport::class)->assertForbidden();
        $this->actingAs($dean)->get(route('department.reports'))->assertForbidden();
        $this->actingAs($inside->user)->get(route('department.reports'))->assertForbidden();
    }

    public function test_dean_reports_export_aggregates_only(): void
    {
        $college = College::factory()->create();
        $otherCollege = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id, 'code' => 'PROG-A']);
        $other = Program::factory()->create(['college_id' => $otherCollege->id, 'code' => 'PROG-C']);
        $dean = User::factory()->dean($college)->create();
        $head = User::factory()->departmentHead($program)->create();
        $inside = $this->student($program, 'S-IN', 'Inez Insider', 2);
        $outside = $this->student($other, 'S-OUT', 'Otto Outsider', 3);
        $this->prediction($inside, '2026-05-15 09:00:00', 40, 'high', 'program_fit');
        $this->prediction($outside, '2026-06-01 09:00:00', 99, 'high', 'none');

        $this->actingAs($dean)
            ->get(route('dean.reports'))
            ->assertOk()
            ->assertSee('College totals only')
            ->assertSee('PROG-A')
            ->assertDontSee('S-IN')
            ->assertDontSee('Inez Insider')
            ->assertDontSee('S-OUT')
            ->assertDontSee('Otto Outsider')
            ->assertDontSee(route('students.show', $inside), false)
            ->assertDontSee('id="staff-sidebar"', false);

        $exported = Livewire::actingAs($dean)
            ->test(DeanReport::class)
            ->call('export');

        $filename = (string) data_get($exported->effects, 'download.name');
        $this->assertMatchesRegularExpression('/^edupredict-clas-aggregates-\d{8}-\d{4}\.csv$/', $filename);

        $content = base64_decode((string) data_get($exported->effects, 'download.content'));
        $this->assertStringContainsString('College aggregates only', $content);
        $this->assertStringContainsString('PROG-A', $content);
        $this->assertStringContainsString('program_fit_concern', $content);
        $this->assertStringContainsString('count; qualitative, no percentage', $content);
        $this->assertStringNotContainsString('S-IN', $content);
        $this->assertStringNotContainsString('Inez Insider', $content);
        $this->assertStringNotContainsString('S-OUT', $content);
        $this->assertStringNotContainsString('Otto Outsider', $content);
        $this->assertStringNotContainsString('PROG-C', $content);

        $this->assertSame('aggregates.export', AuditLog::query()->value('action'));

        Livewire::actingAs($head)->test(DeanReport::class)->assertForbidden();
        Livewire::actingAs($dean)->test(ScopedStudentsTable::class)->assertForbidden();
        $this->actingAs(User::factory()->role(UserRole::Administrator)->create())
            ->get(route('dean.reports'))
            ->assertForbidden();
    }

    private function student(Program $program, string $number, string $name, int $year): Student
    {
        $user = User::factory()->create([
            'name' => $name,
            'role' => UserRole::Student,
        ]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
            'year_level' => $year,
        ]);
    }

    private function prediction(Student $student, string $at, float $score, string $risk, string $flag): Prediction
    {
        return Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'created_at' => $at,
            'employability_score' => $score,
            'dropout_risk' => $risk,
            'program_shift_flag' => $flag,
            'model_version' => 'placeholder-heuristic-v0',
        ]);
    }
}
