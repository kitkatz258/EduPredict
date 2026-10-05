<?php

namespace App\Services\Grades;

use App\Models\GradeReport;
use App\Models\Student;
use App\Models\SubjectGrade;
use Illuminate\Support\Facades\Storage;

final class GradeReportWriter
{
    public function __construct(
        private GradeRowNormalizer $normalizer,
        private AcademicSummary $summary,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $warnings
     */
    public function saveDraft(Student $student, array $attributes, array $rows, array $warnings = []): GradeReport
    {
        $report = $this->upsertReport($student, $attributes, 'draft', $warnings);
        $this->syncRows($report, $rows);

        return $report->fresh(['subjectGrades']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $warnings
     */
    public function confirm(GradeReport $report, array $attributes, array $rows, array $warnings = []): GradeReport
    {
        $duplicate = GradeReport::query()
            ->where('student_id', $report->student_id)
            ->where('school_year', $attributes['school_year'])
            ->where('semester', $attributes['semester'])
            ->where('status', 'confirmed')
            ->where('id', '!=', $report->id)
            ->exists();

        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'school_year' => 'A confirmed report already exists for this school year and semester.',
            ]);
        }

        $this->syncRows($report, $rows);

        if ($report->original_file_path) {
            Storage::disk('local')->delete($report->original_file_path);
        }

        $report->update([
            'school_year' => $attributes['school_year'],
            'semester' => $attributes['semester'],
            'source' => $attributes['source'],
            'detected_gpa' => $attributes['detected_gpa'] ?? null,
            'warnings' => $warnings,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'original_file_path' => null,
        ]);

        $this->summary->syncStudent($report->student);

        return $report->fresh(['subjectGrades']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $warnings
     */
    private function upsertReport(Student $student, array $attributes, string $status, array $warnings): GradeReport
    {
        if (! empty($attributes['id'])) {
            $report = GradeReport::query()
                ->where('student_id', $student->id)
                ->whereKey($attributes['id'])
                ->firstOrFail();
            $report->update([
                'school_year' => $attributes['school_year'],
                'semester' => $attributes['semester'],
                'source' => $attributes['source'],
                'detected_gpa' => $attributes['detected_gpa'] ?? null,
                'warnings' => $warnings,
                'status' => $status,
                'original_file_path' => $attributes['original_file_path'] ?? $report->original_file_path,
            ]);

            return $report;
        }

        return GradeReport::query()->create([
            'student_id' => $student->id,
            'school_year' => $attributes['school_year'],
            'semester' => $attributes['semester'],
            'source' => $attributes['source'],
            'status' => $status,
            'detected_gpa' => $attributes['detected_gpa'] ?? null,
            'warnings' => $warnings,
            'original_file_path' => $attributes['original_file_path'] ?? null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncRows(GradeReport $report, array $rows): void
    {
        $codes = array_map(fn (array $row): string => $this->normalizer->normalizeCode((string) ($row['subject_code'] ?? '')), $rows);
        $report->subjectGrades()->delete();

        foreach ($rows as $row) {
            $normalized = $this->normalizer->normalize($row, $codes);
            SubjectGrade::query()->create([
                'grade_report_id' => $report->id,
                'subject_code' => $normalized->subjectCode !== '' ? $normalized->subjectCode : 'UNKNOWN',
                'subject_name' => $normalized->subjectName !== '' ? $normalized->subjectName : 'Untitled',
                'units' => is_numeric($normalized->units) ? $normalized->units : 0,
                'midterm_grade' => $normalized->midtermGrade,
                'final_exam_grade' => $normalized->finalExamGrade,
                'final_grade' => $normalized->finalGrade !== '' ? $normalized->finalGrade : 'INC',
                'remarks' => $normalized->remarks !== '' ? $normalized->remarks : 'INCOMPLETE',
                'is_failed' => $normalized->isFailed,
                'is_major_subject' => $normalized->isMajorSubject,
                'needs_review' => $normalized->needsReview,
            ]);
        }
    }
}
