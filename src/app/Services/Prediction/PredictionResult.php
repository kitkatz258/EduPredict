<?php

declare(strict_types=1);

namespace App\Services\Prediction;

final readonly class PredictionResult
{
    /**
     * @param  list<ContributingFactor>  $factors
     */
    public function __construct(
        public float $score,
        public ?string $riskLevel,
        public string $confidence,
        public array $factors,
        public string $modelVersion,
        public ?float $probability = null,
    ) {}
}
