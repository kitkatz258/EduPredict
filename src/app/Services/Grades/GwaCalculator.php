<?php

namespace App\Services\Grades;

use App\Models\SubjectGrade;

final class GwaCalculator
{
    public function __construct(private GradeScale $scale) {}

    /**
     * @param  iterable<int, array<string, mixed>|SubjectGrade|ParsedGradeRow>  $rows
     */
    public function compute(iterable $rows): GwaResult
    {
        $points = 0.0;
        $units = 0.0;
        $failed = 0;
        $incomplete = 0;

        foreach ($rows as $row) {
            $data = $this->normalize($row);
            if ($this->scale->isFailed($data['final_grade'], $data['remarks'])) {
                $failed++;
            }
            if ($this->scale->isIncomplete($data['final_grade']) || $this->scale->isIncomplete($data['remarks'])) {
                $incomplete++;

                continue;
            }
            if ($this->scale->excludesFromGpa($data['subject_code'])) {
                continue;
            }
            if ($this->scale->isDropped($data['final_grade']) || $this->scale->isDropped($data['remarks'])) {
                continue;
            }

            $value = $this->scale->gpaNumericValue($data['final_grade']);
            $rowUnits = (float) $data['units'];
            if ($value === null || $rowUnits <= 0) {
                continue;
            }

            $points += $rowUnits * $value;
            $units += $rowUnits;
        }

        $gpa = $units > 0 ? $points / $units : null;
        $rounded = $gpa === null ? null : round($gpa, 2);

        return new GwaResult($gpa, $points, $units, $failed, $rounded, $incomplete);
    }

    /**
     * @param  array<string, mixed>|SubjectGrade|ParsedGradeRow  $row
     * @return array{subject_code: string, units: float|string, final_grade: string, remarks: string}
     */
    private function normalize(array|SubjectGrade|ParsedGradeRow $row): array
    {
        if ($row instanceof ParsedGradeRow) {
            $row = $row->toArray();
        } elseif ($row instanceof SubjectGrade) {
            $row = $row->toArray();
        }

        return [
            'subject_code' => (string) ($row['subject_code'] ?? ''),
            'units' => $row['units'] ?? 0,
            'final_grade' => (string) ($row['final_grade'] ?? ''),
            'remarks' => (string) ($row['remarks'] ?? ''),
        ];
    }
}
