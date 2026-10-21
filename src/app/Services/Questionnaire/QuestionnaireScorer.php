<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;

final class QuestionnaireScorer
{
    /**
     * Scores are computed once, when a response is submitted, and then stored.
     * draft-v1 keeps the legacy 0–100 construct scale. The panel-approved
     * instrument stores 1.00–5.00 section means and is not reverse-scored.
     *
     * @return array<string, mixed>
     */
    public function score(QuestionnaireResponse $response): array
    {
        $version = (string) $response->definition_version;
        $scoring = config('edupredict.questionnaire.versions.'.$version.'.scoring');

        if ($scoring === 'section-mean-1-5') {
            return $this->scoreSectionMeans($response, $version);
        }

        return $this->scoreLegacy($response);
    }

    /**
     * Verbal level for a section mean that has already been rounded to 3 decimals.
     * Gaps between the published bands (for example 1.801–1.809) fall in the
     * next band because the upper bounds are 1.80, 2.60, 3.40, 4.20, and 5.00.
     *
     * @return array{level: string, context_flag: bool}|null
     */
    public function interpret(float $mean, bool $contextSection): ?array
    {
        if ($mean < 1.0 || $mean > 5.0) {
            return null;
        }

        $level = null;
        foreach ((array) config('edupredict.questionnaire.interpretation', []) as $band) {
            if (! is_array($band) || ! isset($band['max'], $band['level']) || ! is_numeric($band['max'])) {
                continue;
            }
            if ($mean <= (float) $band['max']) {
                $level = (string) $band['level'];
                break;
            }
        }

        if ($level === null) {
            return null;
        }

        $flagAt = (float) config('edupredict.questionnaire.context_flag_at_or_below', 2.60);

        return [
            'level' => $level,
            'context_flag' => $contextSection && $mean <= $flagAt,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function scoreLegacy(QuestionnaireResponse $response): array
    {
        $response->loadMissing('answers.item');
        $grouped = [];

        foreach ($response->answers as $answer) {
            $item = $answer->item;
            $value = $answer->responseValue();
            if ($item === null || $value === null) {
                continue;
            }

            $adjusted = $item->reverse_scored ? 6 - $value : $value;
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function scoreSectionMeans(QuestionnaireResponse $response, string $version): array
    {
        $response->loadMissing('answers.item');
        $sections = (array) config('edupredict.questionnaire.versions.'.$version.'.sections', []);
        $byItem = [];
        foreach ($response->answers as $answer) {
            $value = $answer->responseValue();
            if ($value !== null) {
                $byItem[$answer->questionnaire_item_id] = $value;
            }
        }

        $scores = [];
        foreach ($sections as $section) {
            if (! is_string($section)) {
                continue;
            }

            $definition = config('edupredict.questionnaire.sections.'.$section);
            if (! is_array($definition)) {
                continue;
            }

            $items = QuestionnaireItem::query()
                ->where('definition_version', $version)
                ->where('section', $section)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                continue;
            }

            $values = [];
            foreach ($items as $item) {
                if (! isset($byItem[$item->id])) {
                    $values = [];
                    break;
                }
                $values[] = $byItem[$item->id];
            }

            if ($values === [] || count($values) !== $items->count()) {
                continue;
            }

            $sum = array_sum($values);
            $mean = round($sum / count($values), 3, PHP_ROUND_HALF_UP);
            $role = is_string($definition['role'] ?? null) ? $definition['role'] : 'context';
            $interpreted = $this->interpret($mean, $role === 'context');
            if ($interpreted === null) {
                continue;
            }

            $construct = (string) ($items->first()->construct ?? $section);
            $scores[$construct] = [
                'section' => $section,
                'code' => is_string($definition['code'] ?? null) ? $definition['code'] : null,
                'label' => is_string($definition['label'] ?? null) ? $definition['label'] : $section,
                'mean' => $mean,
                'sum' => $sum,
                'items' => count($values),
                'level' => $interpreted['level'],
                'readiness_percent' => round((($mean - 1) / 4) * 100, 2, PHP_ROUND_HALF_UP),
                'context_flag' => $interpreted['context_flag'],
                'role' => $role,
            ];
        }

        return $scores;
    }
}
