<?php

declare(strict_types=1);

namespace App\Services\Prediction;

/**
 * UCI binary logistic research score. Dropout or Not Dropout, with no risk band.
 */
final readonly class ResearchDropoutResult
{
    public function __construct(
        public string $modelVersion,
        public string $algorithm,
        public string $featureSet,
        public string $schemaId,
        public string $sourceDataset,
        public float $probability,
        public int $predictedClass,
        public string $predictedClassName,
        public bool $researchOnly,
        public bool $productionValidated,
        public bool $suitableForStudentAdvice,
        public bool $weakCompatibilityBaseline,
        public string $disclaimer,
    ) {}
}
