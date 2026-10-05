<?php

namespace App\Services\Grades;

final class ParsedGradeRow
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $subjectCode,
        public string $subjectName,
        public string $units,
        public ?string $midtermGrade,
        public ?string $finalExamGrade,
        public string $finalGrade,
        public string $remarks,
        public bool $isFailed,
        public bool $needsReview,
        public array $warnings = [],
        public bool $isMajorSubject = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_code' => $this->subjectCode,
            'subject_name' => $this->subjectName,
            'units' => $this->units,
            'midterm_grade' => $this->midtermGrade,
            'final_exam_grade' => $this->finalExamGrade,
            'final_grade' => $this->finalGrade,
            'remarks' => $this->remarks,
            'is_failed' => $this->isFailed,
            'needs_review' => $this->needsReview,
            'is_major_subject' => $this->isMajorSubject,
            'warnings' => $this->warnings,
        ];
    }
}
