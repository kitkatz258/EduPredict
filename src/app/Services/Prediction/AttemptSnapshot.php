<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\CareerMatch;
use App\Models\Prediction;
use App\Services\Questionnaire\SectionScorePresenter;

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
        private SectionScorePresenter $sectionScores,
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
            'constructs' => $this->constructSummary($prediction),
            'careers' => $prediction->careerMatches()->with('occupation')->get()
                ->sortByDesc(fn (CareerMatch $match): float => (float) $match->compatibility_score)
                ->values(),
        ];
    }

    /**
     * Category scores exactly as stored with the attempt: the questionnaire
     * snapshot first, then the attempt's own feature snapshot. Never rescored.
     *
     * @return list<array<string, mixed>>|null
     */
    public function constructSummary(Prediction $prediction): ?array
    {
        $questionnaire = $this->part($prediction, 'questionnaire');
        $scores = ($questionnaire['submitted'] ?? false) ? ($questionnaire['construct_scores'] ?? null) : null;
        if (! is_array($scores) || $scores === []) {
            $scores = is_array($prediction->feature_snapshot) ? ($prediction->feature_snapshot['construct_scores'] ?? null) : null;
        }
        if (! is_array($scores)) {
            return null;
        }

        return $this->sectionScores->rows($scores);
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
