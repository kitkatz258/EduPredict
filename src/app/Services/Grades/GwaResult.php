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
    ) {}
}
