<?php

namespace App\Services\Grades;

final class ParsedGradeReport
{
    /**
     * @param  list<ParsedGradeRow>  $rows
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $source,
        public array $rows = [],
        public ?string $program = null,
        public ?string $schoolYear = null,
        public ?string $semester = null,
        public ?float $detectedGpa = null,
        public array $warnings = [],
        public bool $usedAiFallback = false,
    ) {}

    public function reviewRate(): float
    {
        $total = count($this->rows);
        if ($total === 0) {
            return 1.0;
        }

        $flagged = count(array_filter($this->rows, fn (ParsedGradeRow $row): bool => $row->needsReview));

        return $flagged / $total;
    }

    public function isLowConfidence(): bool
    {
        return $this->rows === [] || $this->reviewRate() > 0.20;
    }
}
