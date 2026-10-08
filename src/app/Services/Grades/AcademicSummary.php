<?php

namespace App\Services\Grades;

use App\Models\GradeReport;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

final class AcademicSummary
{
    public function __construct(private GwaCalculator $calculator) {}

    /**
     * @return array{
     *     gwa: ?float,
     *     rounded_gwa: ?float,
     *     failed_subjects: int,
     *     semesters_completed: int,
     *     limited_history: bool,
     *     major_gwa: ?float,
     *     other_gwa: ?float,
     *     major_failed_subjects: int,
     *     other_failed_subjects: int,
     *     major_units: float,
     *     other_units: float,
     *     incomplete_subjects: int,
     *     gwa_provisional: bool
     * }
     */
    public function for(Student $student): array
    {
        $reports = $this->currentReports($student);

        $rows = $reports->flatMap(fn ($report) => $report->subjectGrades);
        $gwa = $this->calculator->compute($rows);
        $major = $this->calculator->compute($rows->filter(fn ($row): bool => (bool) $row->is_major_subject));
        $other = $this->calculator->compute($rows->reject(fn ($row): bool => (bool) $row->is_major_subject));
        $semesters = $reports->unique(fn ($report) => $report->school_year.'|'.$report->semester)->count();
        $limit = (int) config('edupredict.prediction.limited_history_semesters', 2);

        return [
            'gwa' => $gwa->gpa,
            'rounded_gwa' => $gwa->roundedGpa,
            'failed_subjects' => $gwa->failedCount,
            'semesters_completed' => $semesters,
            'limited_history' => $semesters < $limit,
            'major_gwa' => $major->roundedGpa,
            'other_gwa' => $other->roundedGpa,
            'major_failed_subjects' => $major->failedCount,
            'other_failed_subjects' => $other->failedCount,
            'major_units' => $major->gpaUnits,
            'other_units' => $other->gpaUnits,
            'incomplete_subjects' => $gwa->incompleteCount,
            'gwa_provisional' => $gwa->isProvisional(),
        ];
    }

    /**
     * Confirmed reports that have not been replaced by a newer version.
     *
     * @return Collection<int, GradeReport>
     */
    public function currentReports(Student $student): Collection
    {
        return $student->gradeReports()
            ->current()
            ->with('subjectGrades')
            ->orderBy('school_year')
            ->orderBy('semester')
            ->orderBy('id')
            ->get();
    }

    public function syncStudent(Student $student): array
    {
        $summary = $this->for($student);
        $student->update(['semesters_completed' => $summary['semesters_completed']]);

        return $summary;
    }
}
