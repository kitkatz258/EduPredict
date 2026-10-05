<?php

namespace Tests\Feature\Grades;

use App\Livewire\Student\GradeReportForm;
use App\Livewire\Tables\GradeReportsTable;
use App\Models\GradeReport;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\Grades\AcademicSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GradeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_manually_enter_and_confirm_a_term(): void
    {
        $student = $this->makeStudent();
        $rows = $this->sheetARows();

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->set('school_year', '2024-2025')
            ->set('semester', 'Second')
            ->set('source', 'manual')
            ->set('rows', $rows)
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grade_reports', [
            'student_id' => $student->id,
            'school_year' => '2024-2025',
            'semester' => 'Second',
            'status' => 'confirmed',
            'source' => 'manual',
        ]);

        $summary = app(AcademicSummary::class)->for($student->fresh());
        $this->assertSame(1.44, $summary['rounded_gwa']);
        $this->assertSame(0, $summary['failed_subjects']);
        $this->assertSame(1, $summary['semesters_completed']);
        $this->assertTrue($summary['limited_history']);
        $this->assertSame(1, $student->fresh()->semesters_completed);
    }

    public function test_paste_path_confirms_sheet_b_inc_and_failed_flags(): void
    {
        $student = $this->makeStudent();
        $paste = (string) file_get_contents(base_path('tests/Fixtures/grade-reports/paste-b.txt'));

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->set('pastedText', $paste)
            ->call('parsePaste')
            ->assertSet('school_year', '2025-2026')
            ->assertSet('semester', 'First')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subject_grades', [
            'subject_code' => 'IS 106',
            'is_failed' => 1,
            'remarks' => 'FAILED',
        ]);
        $this->assertDatabaseHas('subject_grades', [
            'subject_code' => 'IS 103',
            'is_failed' => 0,
            'final_grade' => 'INC',
            'remarks' => 'INCOMPLETE',
        ]);

        $summary = app(AcademicSummary::class)->for($student->fresh());
        $this->assertSame(2.33, $summary['rounded_gwa']);
        $this->assertSame(1, $summary['failed_subjects']);
    }

    public function test_student_can_open_grades_page(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student->user)
            ->get(route('student.grades'))
            ->assertOk()
            ->assertSee('Paste from portal')
            ->assertSee('Confirm');
    }

    public function test_faculty_cannot_open_or_mutate_student_grade_entry(): void
    {
        $faculty = User::factory()->faculty()->create();

        $this->actingAs($faculty)->get(route('student.grades'))->assertForbidden();

        Livewire::actingAs($faculty)
            ->test(GradeReportForm::class)
            ->assertForbidden();

        Livewire::actingAs($faculty)
            ->test(GradeReportsTable::class)
            ->assertForbidden();
    }

    public function test_student_cannot_delete_another_students_report_via_livewire(): void
    {
        $program = Program::factory()->create();
        $owner = $this->makeStudent($program, '2024-61001');
        $other = $this->makeStudent($program, '2024-61002');
        $report = GradeReport::factory()->create([
            'student_id' => $owner->id,
            'status' => 'confirmed',
        ]);

        Livewire::actingAs($other->user)
            ->test(GradeReportsTable::class)
            ->call('deleteReport', $report->id)
            ->assertNotFound();

        $this->assertDatabaseHas('grade_reports', ['id' => $report->id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sheetARows(): array
    {
        $expected = json_decode((string) file_get_contents(base_path('tests/Fixtures/grade-reports/sheet-a.expected.json')), true);

        return array_map(fn (array $row) => $row + ['needs_review' => false, 'is_major_subject' => false, 'warnings' => []], $expected['rows']);
    }

    private function makeStudent(?Program $program = null, string $number = '2024-61000'): Student
    {
        $program ??= Program::factory()->create();
        $user = User::factory()->create();

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
            'semesters_completed' => 0,
        ]);
    }
}
