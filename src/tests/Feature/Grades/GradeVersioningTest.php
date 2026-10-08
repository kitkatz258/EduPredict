<?php

namespace Tests\Feature\Grades;

use App\Enums\UserRole;
use App\Livewire\Student\AssessmentWizard;
use App\Livewire\Student\GradeReportForm;
use App\Livewire\Tables\GradeReportsTable;
use App\Models\GradeReport;
use App\Models\Program;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\StudentSkill;
use App\Models\User;
use App\Services\Grades\AcademicSummary;
use App\Services\Prediction\PredictionRequester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GradeVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_an_inc_creates_a_new_version_and_recalculates(): void
    {
        $student = $this->makeStudent();
        $original = $this->confirmSheetB($student);

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class, ['replaceId' => $original->id])
            ->assertSet('replacesId', $original->id)
            ->assertSet('mode', 'manual')
            ->assertSee('Updating 2025-2026 · First (version 1)')
            ->set('rows.3.final_grade', '1.50')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('replacesId', null);

        $original->refresh();
        $this->assertNotNull($original->superseded_at);
        $this->assertSame('confirmed', $original->status);
        $this->assertDatabaseHas('subject_grades', [
            'grade_report_id' => $original->id,
            'subject_code' => 'IS 103',
            'final_grade' => 'INC',
            'is_incomplete' => 1,
        ]);

        $replacement = GradeReport::query()->current()->where('student_id', $student->id)->sole();
        $this->assertSame(2, $replacement->version);
        $this->assertSame($original->id, $replacement->supersedes_id);
        $this->assertDatabaseHas('subject_grades', [
            'grade_report_id' => $replacement->id,
            'subject_code' => 'IS 103',
            'final_grade' => '1.50',
            'remarks' => 'PASSED',
            'is_incomplete' => 0,
        ]);

        $summary = app(AcademicSummary::class)->for($student->fresh());
        $this->assertSame(1.85, $summary['rounded_gwa']);
        $this->assertFalse($summary['gwa_provisional']);
        $this->assertSame(1, $summary['semesters_completed']);
    }

    public function test_prediction_snapshot_keeps_the_grade_version_it_used(): void
    {
        $student = $this->makeStudent();
        $original = $this->confirmSheetB($student);
        $this->completeOtherSections($student);

        $prediction = app(PredictionRequester::class)->request($student->fresh(), $student->user);
        $grades = $prediction->fresh()->assessment_snapshot['grades'];

        $this->assertTrue($grades['has_grades']);
        $this->assertTrue($grades['gwa_provisional']);
        $this->assertSame(1.93, $grades['rounded_gwa']);
        $this->assertSame($original->id, $grades['reports'][0]['grade_report_id']);
        $this->assertSame(1, $grades['reports'][0]['report_version']);
        $this->assertSame('INC', collect($grades['reports'][0]['subjects'])->firstWhere('subject_code', 'IS 103')['final_grade']);

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class, ['replaceId' => $original->id])
            ->set('rows.3.final_grade', '1.50')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertSame($grades, $prediction->fresh()->assessment_snapshot['grades']);
    }

    public function test_confirming_the_same_term_again_supersedes_instead_of_failing(): void
    {
        $student = $this->makeStudent();
        $first = $this->confirmSheetB($student);
        $second = $this->confirmSheetB($student);

        $this->assertNotNull($first->fresh()->superseded_at);
        $this->assertSame(2, $second->version);
        $this->assertSame(1, GradeReport::query()->current()->where('student_id', $student->id)->count());
    }

    public function test_blank_final_grade_is_not_saved_as_inc(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->call('setMode', 'manual')
            ->set('school_year', '2024-2025')
            ->set('semester', 'First')
            ->set('rows.0.subject_code', 'CCS 101')
            ->set('rows.0.subject_name', 'Intro to Computing')
            ->set('rows.0.units', '3')
            ->call('confirm')
            ->assertHasErrors(['rows']);

        $this->assertDatabaseMissing('subject_grades', ['subject_code' => 'CCS 101']);
        $this->assertSame(0, GradeReport::query()->current()->count());
    }

    public function test_manual_mode_saves_grades_with_inc(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->call('setMode', 'manual')
            ->assertSee('Manual grade entry')
            ->set('school_year', '2024-2025')
            ->set('semester', 'First')
            ->set('rows.0.subject_code', 'CCS 101')
            ->set('rows.0.subject_name', 'Intro to Computing')
            ->set('rows.0.units', '3')
            ->set('rows.0.final_grade', 'inc')
            ->call('addRow')
            ->set('rows.1.subject_code', 'CCS 102')
            ->set('rows.1.subject_name', 'Programming 1')
            ->set('rows.1.units', '3')
            ->set('rows.1.final_grade', '1.75')
            ->assertSee('Provisional')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subject_grades', ['subject_code' => 'CCS 101', 'final_grade' => 'INC', 'remarks' => 'INCOMPLETE', 'is_incomplete' => 1, 'is_failed' => 0]);
        $this->assertSame(1.75, app(AcademicSummary::class)->for($student->fresh())['rounded_gwa']);
    }

    public function test_paste_review_shows_the_reference_columns_without_ai_wording(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->call('setMode', 'paste')
            ->set('pastedText', (string) file_get_contents(base_path('tests/Fixtures/grade-reports/paste-b.txt')))
            ->call('parsePaste')
            ->assertSet('stage', 'review')
            ->assertSee('AY 2025–2026 · 1st Semester')
            ->assertSee('Pasted from the UCC portal')
            ->assertSee('Confirm &amp; Save Grades', false)
            ->assertSee('Provisional')
            ->assertSee('The portal may count INC differently')
            ->assertDontSee('AI-extracted')
            ->call('editRow', 0)
            ->set('rows.0.subject_name', 'Multimedia Systems (edited)')
            ->assertSet('editedRows', [0])
            ->assertSee('1 row edited');
    }

    public function test_confirmed_versions_cannot_be_deleted_but_drafts_can(): void
    {
        $student = $this->makeStudent();
        $confirmed = GradeReport::factory()->create(['student_id' => $student->id]);
        $draft = GradeReport::factory()->draft()->create(['student_id' => $student->id]);

        Livewire::actingAs($student->user)
            ->test(GradeReportsTable::class)
            ->call('deleteReport', $confirmed->id)
            ->assertForbidden();
        $this->assertDatabaseHas('grade_reports', ['id' => $confirmed->id]);

        Livewire::actingAs($student->user)
            ->test(GradeReportsTable::class)
            ->call('deleteReport', $draft->id)
            ->assertHasNoErrors();
        $this->assertDatabaseMissing('grade_reports', ['id' => $draft->id]);
    }

    public function test_view_modal_is_read_only_and_shows_replaced_versions(): void
    {
        $student = $this->makeStudent();
        $original = $this->confirmSheetB($student);
        $this->confirmSheetB($student);

        Livewire::actingAs($student->user)
            ->test(GradeReportsTable::class)
            ->assertSee('Replaced')
            ->assertSee('Current')
            ->call('openView', $original->id)
            ->assertSet('viewingId', $original->id)
            ->assertSee('Database System Enterprise')
            ->assertSee('read-only')
            ->assertSee('Replaced on')
            ->assertSee('Provisional: 1 incomplete subject left out.');
    }

    public function test_replaced_versions_cannot_be_replaced_again(): void
    {
        $student = $this->makeStudent();
        $original = $this->confirmSheetB($student);
        $this->confirmSheetB($student);

        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class, ['replaceId' => $original->id])
            ->assertForbidden();
    }

    public function test_other_students_and_staff_cannot_view_or_replace_a_report(): void
    {
        $program = Program::factory()->create();
        $owner = $this->makeStudent($program, '2024-71001');
        $other = $this->makeStudent($program, '2024-71002');
        $report = $this->confirmSheetB($owner);

        Livewire::actingAs($other->user)
            ->test(GradeReportsTable::class)
            ->call('openView', $report->id)
            ->assertNotFound();

        Livewire::actingAs($other->user)
            ->test(GradeReportsTable::class)
            ->call('replaceReport', $report->id)
            ->assertNotFound();

        Livewire::actingAs($other->user)
            ->test(GradeReportForm::class, ['replaceId' => $report->id])
            ->assertForbidden();

        Livewire::actingAs($other->user)
            ->test(GradeReportForm::class)
            ->call('continueDraft', $report->id)
            ->assertForbidden();

        $head = User::factory()->create(['role' => UserRole::DepartmentHead, 'program_id' => $program->id]);
        Livewire::actingAs($head)
            ->test(GradeReportForm::class, ['replaceId' => $report->id])
            ->assertForbidden();

        $this->assertNull($report->fresh()->superseded_at);
    }

    public function test_assessment_offers_latest_grades_or_update_choice(): void
    {
        $student = $this->makeStudent();
        $report = $this->confirmSheetB($student);

        Livewire::actingAs($student->user)
            ->withQueryParams(['step' => 'grades'])
            ->test(AssessmentWizard::class)
            ->assertSee('Use latest confirmed grades')
            ->assertSee('Update grades')
            ->assertSee('Provisional · 1 INC left out')
            ->assertDontSee('Manual Entry')
            ->call('replaceGradeReport', $report->id)
            ->assertSet('gradeChoice', 'update')
            ->assertSet('gradeReplaceId', $report->id)
            ->assertSee('Manual Entry');
    }

    private function confirmSheetB(Student $student): GradeReport
    {
        Livewire::actingAs($student->user)
            ->test(GradeReportForm::class)
            ->call('setMode', 'paste')
            ->set('pastedText', (string) file_get_contents(base_path('tests/Fixtures/grade-reports/paste-b.txt')))
            ->call('parsePaste')
            ->call('confirm')
            ->assertHasNoErrors();

        return GradeReport::query()->current()->where('student_id', $student->id)->sole();
    }

    private function makeStudent(?Program $program = null, string $number = '2024-71000'): Student
    {
        $program ??= Program::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Student]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
            'semesters_completed' => 0,
        ]);
    }

    private function completeOtherSections(Student $student): void
    {
        SocioeconomicProfile::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        SkillsExperience::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'SQL']);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'construct_scores' => ['study_habits' => 60, 'time_management' => 60, 'motivation' => 60, 'procrastination' => 40, 'engagement' => 60],
        ]);
    }
}
