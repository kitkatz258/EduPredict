<?php

declare(strict_types=1);

namespace App\Services\Prediction;

/**
 * Pianda linear-regression research score. Not a UCC student employability result.
 */
final readonly class ResearchEmployabilityResult
{
    public function __construct(
        public string $modelVersion,
        public string $algorithm,
        public string $schemaId,
        public string $sourceDataset,
        public float $predictedMean,
        public ?float $displayReadinessIndex,
        public bool $researchOnly,
        public bool $productionValidated,
        public string $disclaimer,
    ) {}
}
