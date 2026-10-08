<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\CareerMatch;
use App\Models\Prediction;

/**
 * Read-only view of one saved prediction attempt, built only from what the
 * prediction row stored. A missing part stays null so the UI can say it is
 * not available instead of filling in current data.
 */
final class AttemptSnapshot
{
    public const SECTIONS = ['questionnaire', 'skills_experience', 'grades'];

    public function __construct(
        private PredictionPresenter $presenter,
        private ProgramShiftEvaluator $programShift,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Prediction $prediction): array
    {
        return [
            'prediction' => $prediction,
            'factors' => $this->presenter->groups($prediction),
            'shift' => $this->programShift->present($prediction),
            'questionnaire' => $this->part($prediction, 'questionnaire'),
            'skills' => $this->part($prediction, 'skills_experience'),
            'grades' => $this->part($prediction, 'grades'),
            'complete' => $this->isComplete($prediction),
            'construct_labels' => (array) config('edupredict.questionnaire.constructs', []),
            'careers' => $prediction->careerMatches()->with('occupation')->get()
                ->sortByDesc(fn (CareerMatch $match): float => (float) $match->compatibility_score)
                ->values(),
        ];
    }

    public function isComplete(Prediction $prediction): bool
    {
        foreach (self::SECTIONS as $section) {
            if ($this->part($prediction, $section) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function part(Prediction $prediction, string $section): ?array
    {
        $stored = $prediction->assessment_snapshot[$section] ?? null;

        return is_array($stored) ? $stored : null;
    }
}
