<?php

declare(strict_types=1);

namespace App\Services\Prediction;

final readonly class ContributingFactor
{
    public function __construct(
        public string $feature,
        public string $label,
        public string $direction,
        public float $magnitude,
    ) {}

    /**
     * @return array{feature: string, label: string, direction: string, magnitude: float}
     */
    public function toArray(): array
    {
        return [
            'feature' => $this->feature,
            'label' => $this->label,
            'direction' => $this->direction,
            'magnitude' => $this->magnitude,
        ];
    }
}
