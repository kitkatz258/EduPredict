<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireResponse;

final class QuestionnaireScorer
{
    /**
     * Likert 1–5, with reverse items flipped to 6 − value.
     * A construct score is the mean item score scaled to 0–100.
     *
     * @return array<string, float>
     */
    public function score(QuestionnaireResponse $response): array
    {
        $response->loadMissing('answers.item');
        $grouped = [];

        foreach ($response->answers as $answer) {
            $item = $answer->item;
            if ($item === null) {
                continue;
            }

            $adjusted = $item->reverse_scored ? 6 - (int) $answer->value : (int) $answer->value;
            $grouped[$item->construct][] = $adjusted;
        }

        $scores = [];
        foreach (array_keys(config('edupredict.questionnaire.constructs', [])) as $construct) {
            if (! isset($grouped[$construct])) {
                continue;
            }

            $mean = array_sum($grouped[$construct]) / count($grouped[$construct]);
            $scores[$construct] = round(($mean - 1) / 4 * 100, 2);
        }

        return $scores;
    }
}
