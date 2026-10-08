<?php

namespace App\Services\Grades;

final readonly class GwaResult
{
    public function __construct(
        public ?float $gpa,
        public float $qualityPoints,
        public float $gpaUnits,
        public int $failedCount,
        public ?float $roundedGpa,
        public int $incompleteCount = 0,
    ) {}

    /**
     * Incomplete subjects are left out until a final grade replaces them.
     */
    public function isProvisional(): bool
    {
        return $this->incompleteCount > 0;
    }
}
