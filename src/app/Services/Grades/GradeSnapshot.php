<?php

declare(strict_types=1);

namespace App\Services\Grades;

use App\Models\GradeReport;
use App\Models\Student;
use App\Models\SubjectGrade;

/**
 * The exact grade versions a prediction used, copied row by row. Stored on the
 * prediction and never rewritten, so later term updates cannot change it.
 * Instructor names left in older pasted descriptions are not copied.
 */
final class GradeSnapshot
{
    public const SNAPSHOT_VERSION = 1;

    public function __construct(
        private AcademicSummary $academic,
        private GwaCalculator $calculator,
        private GradeRowNormalizer $normalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Student $student): array
    {
        $reports = $this->academic->currentReports($student);
        $gwa = $this->calculator->compute($reports->flatMap(fn (GradeReport $report) => $report->subjectGrades));

        return [
            'version' => self::SNAPSHOT_VERSION,
            'has_grades' => $reports->isNotEmpty(),
            'rounded_gwa' => $gwa->roundedGpa,
            'gwa_provisional' => $gwa->isProvisional(),
            'failed_subjects' => $gwa->failedCount,
            'incomplete_subjects' => $gwa->incompleteCount,
            'reports' => $reports->map(fn (GradeReport $report): array => [
                'grade_report_id' => $report->id,
                'report_version' => $report->version,
                'school_year' => $report->school_year,
                'semester' => $report->semester,
                'source' => $report->source,
                'confirmed_at' => $report->confirmed_at?->toIso8601String(),
                'subjects' => $report->subjectGrades->map(fn (SubjectGrade $grade): array => [
                    'subject_code' => $grade->subject_code,
                    'subject_name' => $this->normalizer->stripInstructorName($grade->subject_name),
                    'units' => $grade->units,
                    'final_grade' => $grade->final_grade,
                    'remarks' => $grade->remarks,
                    'is_failed' => $grade->is_failed,
                    'is_incomplete' => $grade->is_incomplete,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
