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
            'is_incomplete' => 1,
            'final_grade' => 'INC',
            'remarks' => 'INCOMPLETE',
        ]);

        $summary = app(AcademicSummary::class)->for($student->fresh());
        $this->assertSame(1.93, $summary['rounded_gwa']);
        $this->assertSame(1, $summary['failed_subjects']);
        $this->assertSame(1, $summary['incomplete_subjects']);
        $this->assertTrue($summary['gwa_provisional']);
    }

    public function test_manual_tokens_show_remarks_immediately_and_keep_gwa_rules(): void
    {
        $student = $this->makeStudent();
        $row = [
            'subject_code' => 'CCS 101',
            'subject_name' => 'Information Management',
            'units' => '3',
            'final_grade' => '',
            'remarks' => '',
            'is_major_subject' => false,
        ];

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->set('mode', 'manual')
            ->set('school_year', '2024-2025')
            ->set('semester', 'First')
            ->set('rows', [$row])
            ->set('rows.0.final_grade', 'INC')
            ->assertSet('rows.0.remarks', 'Incomplete')
            ->set('rows.0.final_grade', '5.00')
            ->assertSet('rows.0.remarks', 'Failed')
            ->set('rows.0.final_grade', 'Dropped')
            ->assertSet('rows.0.remarks', 'Dropped')
            ->set('rows.0.final_grade', 'Unofficially Dropped')
            ->assertSet('rows.0.remarks', 'Unofficially Dropped')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subject_grades', [
            'subject_code' => 'CCS 101',
            'final_grade' => 'UD',
            'remarks' => 'UNOFFICIAL',
            'is_failed' => 0,
            'is_incomplete' => 0,
        ]);

        $summary = app(AcademicSummary::class)->for($student->fresh());
        $this->assertNull($summary['rounded_gwa']);
        $this->assertSame(0, $summary['failed_subjects']);
        $this->assertSame(1, $summary['dropped_subjects']);
    }

    public function test_missing_term_blocks_confirmation_and_keeps_extracted_rows(): void
    {
        $student = $this->makeStudent();
        $rows = $this->sheetARows();

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->set('mode', 'upload')
            ->set('stage', 'review')
            ->set('source', 'pdf_text')
            ->set('school_year', '')
            ->set('semester', '')
            ->set('rows', $rows)
            ->call('confirm')
            ->assertHasErrors(['school_year', 'semester'])
            ->assertSet('stage', 'review')
            ->assertSet('rows.0.subject_code', 'CCS 106')
            ->assertSee('Choose the academic year.')
            ->assertSee('The subject rows on this screen were kept.')
            ->assertNoRedirect();

        $this->assertSame(0, GradeReport::query()->count());

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->set('mode', 'upload')
            ->set('stage', 'review')
            ->set('rows', $rows)
            ->set('school_year', '2024-2025')
            ->set('semester', '')
            ->call('confirm')
            ->assertHasErrors(['semester'])
            ->assertHasNoErrors(['school_year'])
            ->assertSet('stage', 'review')
            ->assertSet('rows.0.subject_code', 'CCS 106');

        $this->assertSame(0, GradeReport::query()->count());
    }

    public function test_a_confirmed_upload_appears_on_assessment_and_in_history(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->set('mode', 'upload')
            ->set('stage', 'review')
            ->set('source', 'pdf_text')
            ->set('school_year', '2024-2025')
            ->set('semester', 'Second')
            ->set('rows', $this->sheetARows())
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('stage', 'input');

        $this->assertSame(1, GradeReport::query()->where('status', 'confirmed')->count());
        $this->assertSame(0, GradeReport::query()->where('status', 'draft')->count());
        $this->assertSame(7, GradeReport::query()->firstOrFail()->subjectGrades()->count());

        $this->actingAs($student->user)
            ->get(route('student.assessment', ['step' => 'grades']))
            ->assertOk()
            ->assertSee('2024-2025')
            ->assertSee('Second');

        $this->actingAs($student->user)
            ->get(route('student.history'))
            ->assertOk()
            ->assertSee('Saved grade report versions')
            ->assertSee('2024-2025')
            ->assertSee('Second');
    }

    public function test_grades_live_inside_the_unified_assessment(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student->user)
            ->get(route('student.grades'))
            ->assertRedirect('/student/assessment?step=grades');

        $this->actingAs($student->user)
            ->get('/student/assessment?step=grades')
            ->assertOk()
            ->assertSee('Optional update')
            ->assertSee('Upload Document')
            ->assertSee('Paste from UCC Portal')
            ->assertSee('Manual Entry')
            ->assertDontSee('extracted automatically using AI');
    }

    public function test_department_head_cannot_open_or_mutate_student_grade_entry(): void
    {
        $head = User::factory()->departmentHead()->create();

        $this->actingAs($head)->get(route('student.grades'))->assertForbidden();

        Livewire::actingAs($head)
            ->test(GradeReportForm::class)
            ->assertForbidden();

        Livewire::actingAs($head)
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
