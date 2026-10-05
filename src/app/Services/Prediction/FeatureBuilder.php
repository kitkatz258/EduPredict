<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\Student;

/**
 * Partial feature mapping. M5.5 expands this into the full FeatureSet and predictor.
 * Questionnaire construct scores are the only features supplied here.
 */
final class FeatureBuilder
{
    /**
     * @return array{construct_scores: array<string, float>|null}
     */
    public function build(Student $student): array
    {
        $latest = $student->questionnaireResponses()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        /** @var array<string, float>|null $scores */
        $scores = $latest?->construct_scores;

        return [
            'construct_scores' => $scores,
        ];
    }
}
