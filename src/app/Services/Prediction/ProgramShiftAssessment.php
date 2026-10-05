<?php

declare(strict_types=1);

namespace App\Services\Prediction;

/**
 * Qualitative program-shift reading. No percentage and no trained model.
 */
final readonly class ProgramShiftAssessment
{
    /**
     * @param  list<array{feature: string, label: string, note: string}>  $factors
     */
    public function __construct(
        public string $flag,
        public string $label,
        public string $message,
        public ?string $engagement,
        public array $factors,
    ) {}

    /**
     * @return array{flag: string, label: string, message: string, engagement: ?string, factors: list<array{feature: string, label: string, note: string}>}
     */
    public function toArray(): array
    {
        return [
            'flag' => $this->flag,
            'label' => $this->label,
            'message' => $this->message,
            'engagement' => $this->engagement,
            'factors' => $this->factors,
        ];
    }
}
