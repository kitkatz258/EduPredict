<?php

namespace App\Services\Grades;

use App\Models\GradeReport;
use App\Models\Student;
use App\Models\SubjectGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class GradeReportWriter
{
    public function __construct(
        private GradeRowNormalizer $normalizer,
        private GradeScale $scale,
        private AcademicSummary $summary,
    ) {}

    /**
     * Only drafts are written in place. Passing a confirmed report id fails.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $warnings
     */
    public function saveDraft(Student $student, array $attributes, array $rows, array $warnings = []): GradeReport
    {
        $report = $this->upsertDraft($student, $attributes, $warnings);
        $this->syncRows($report, $rows);

        return $report->fresh(['subjectGrades']);
    }

    /**
     * Confirms a draft. A current confirmed report for the same term, or the one
     * named by `$replacesId`, is stamped superseded and kept unchanged; the new
     * report becomes the next version.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $warnings
     */
    public function confirm(GradeReport $report, array $attributes, array $rows, array $warnings = [], ?int $replacesId = null): GradeReport
    {
        abort_unless($report->isDraft(), 409, 'Only a draft can be confirmed.');
        $this->assertFinalGrades($rows);

        $predecessors = GradeReport::query()
            ->current()
            ->where('student_id', $report->student_id)
            ->whereKeyNot($report->id)
            ->where(function ($query) use ($attributes, $replacesId): void {
                $query->where(function ($term) use ($attributes): void {
                    $term->where('school_year', $attributes['school_year'])
                        ->where('semester', $attributes['semester']);
                });
                if ($replacesId !== null) {
                    $query->orWhere('id', $replacesId);
                }
            })
            ->get();

        if ($replacesId !== null && ! $predecessors->contains('id', $replacesId)) {
            throw ValidationException::withMessages([
                'school_year' => 'The report you are updating is no longer the current version.',
            ]);
        }

        DB::transaction(function () use ($report, $attributes, $rows, $warnings, $predecessors, $replacesId): void {
            $now = now();
            $previous = $replacesId !== null
                ? $predecessors->firstWhere('id', $replacesId)
                : $predecessors->sortByDesc('version')->first();

            GradeReport::query()->whereKey($predecessors->pluck('id'))->update(['superseded_at' => $now]);

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
                'version' => $predecessors->isEmpty() ? 1 : ((int) $predecessors->max('version')) + 1,
                'supersedes_id' => $previous?->id,
                'confirmed_at' => $now,
                'original_file_path' => null,
            ]);
        });

        $this->summary->syncStudent($report->student);

        return $report->fresh(['subjectGrades']);
    }

    /**
     * A blank final grade is never turned into INC; the student must enter a
     * grade on the scale, INC, or a dropped/withdrawn mark.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function assertFinalGrades(array $rows): void
    {
        foreach ($rows as $index => $row) {
            if (trim((string) ($row['subject_code'] ?? '')) === '') {
                continue;
            }
            $grade = $this->scale->normalizeGradeToken((string) ($row['final_grade'] ?? '')) ?? '';
            if (! $this->scale->isNumericGrade($grade) && ! $this->scale->isIncomplete($grade) && ! $this->scale->isDropped($grade)) {
                throw ValidationException::withMessages([
                    'rows' => 'Row '.($index + 1).' needs a final grade on the scale, INC, or DRP/W before saving.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $warnings
     */
    private function upsertDraft(Student $student, array $attributes, array $warnings): GradeReport
    {
        if (! empty($attributes['id'])) {
            $report = GradeReport::query()
                ->where('student_id', $student->id)
                ->where('status', 'draft')
                ->whereKey($attributes['id'])
                ->firstOrFail();
            $report->update([
                'school_year' => $attributes['school_year'],
                'semester' => $attributes['semester'],
                'source' => $attributes['source'],
                'detected_gpa' => $attributes['detected_gpa'] ?? null,
                'warnings' => $warnings,
                'original_file_path' => $attributes['original_file_path'] ?? $report->original_file_path,
            ]);

            return $report;
        }

        return GradeReport::query()->create([
            'student_id' => $student->id,
            'school_year' => $attributes['school_year'],
            'semester' => $attributes['semester'],
            'source' => $attributes['source'],
            'status' => 'draft',
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
        $rows = array_values(array_filter($rows, fn (array $row): bool => trim((string) ($row['subject_code'] ?? '')) !== ''
            || trim((string) ($row['subject_name'] ?? '')) !== ''
            || trim((string) ($row['final_grade'] ?? '')) !== ''));
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
                'final_grade' => $normalized->finalGrade,
                'remarks' => $normalized->remarks,
                'is_failed' => $normalized->isFailed,
                'is_incomplete' => $normalized->isIncomplete,
                'is_major_subject' => $normalized->isMajorSubject,
                'needs_review' => $normalized->needsReview,
            ]);
        }
    }
}
