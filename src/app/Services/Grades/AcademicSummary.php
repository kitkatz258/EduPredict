<?php

namespace App\Services\Grades;

use App\Models\Student;

final class AcademicSummary
{
    public function __construct(private GwaCalculator $calculator) {}

    /**
     * @return array{gwa: ?float, rounded_gwa: ?float, failed_subjects: int, semesters_completed: int, limited_history: bool}
     */
    public function for(Student $student): array
    {
        $reports = $student->gradeReports()
            ->where('status', 'confirmed')
            ->with('subjectGrades')
            ->orderBy('school_year')
            ->orderBy('semester')
            ->get();

        $rows = $reports->flatMap(fn ($report) => $report->subjectGrades);
        $gwa = $this->calculator->compute($rows);
        $semesters = $reports->unique(fn ($report) => $report->school_year.'|'.$report->semester)->count();
        $limit = (int) config('edupredict.prediction.limited_history_semesters', 2);

        return [
            'gwa' => $gwa->gpa,
            'rounded_gwa' => $gwa->roundedGpa,
            'failed_subjects' => $gwa->failedCount,
            'semesters_completed' => $semesters,
            'limited_history' => $semesters < $limit,
        ];
    }

    public function syncStudent(Student $student): array
    {
        $summary = $this->for($student);
        $student->update(['semesters_completed' => $summary['semesters_completed']]);

        return $summary;
    }
}
