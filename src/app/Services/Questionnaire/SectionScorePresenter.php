<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

/**
 * Displays construct scores exactly as they were stored.
 * Legacy draft-v1 values stay on the 0–100 scale. Approved section means
 * are not reinterpreted or rescored here.
 */
final class SectionScorePresenter
{
    /**
     * @param  array<string, mixed>|null  $scores
     * @return list<array<string, mixed>>|null
     */
    public function rows(?array $scores): ?array
    {
        if ($scores === null || $scores === []) {
            return null;
        }

        $labels = (array) config('edupredict.questionnaire.constructs', []);
        $rows = [];

        foreach ($scores as $key => $score) {
            if (is_array($score) && array_key_exists('mean', $score) && is_numeric($score['mean'])) {
                $rows[] = [
                    'key' => (string) $key,
                    'label' => is_string($score['label'] ?? null) && $score['label'] !== ''
                        ? $score['label']
                        : (string) ($labels[$key] ?? str((string) $key)->headline()),
                    'code' => is_string($score['code'] ?? null) ? $score['code'] : null,
                    'presentation' => 'mean',
                    'mean' => (float) $score['mean'],
                    'level' => is_string($score['level'] ?? null) ? $score['level'] : null,
                    'readiness_percent' => is_numeric($score['readiness_percent'] ?? null) ? (float) $score['readiness_percent'] : null,
                    'context_flag' => array_key_exists('context_flag', $score) ? (bool) $score['context_flag'] : null,
                    'role' => is_string($score['role'] ?? null) ? $score['role'] : null,
                    'sum' => is_numeric($score['sum'] ?? null) ? (float) $score['sum'] : null,
                    'items' => is_numeric($score['items'] ?? null) ? (int) $score['items'] : null,
                    'score' => is_numeric($score['readiness_percent'] ?? null) ? (float) $score['readiness_percent'] : null,
                ];

                continue;
            }

            if (! is_numeric($score)) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'label' => (string) ($labels[$key] ?? str((string) $key)->headline()),
                'code' => null,
                'presentation' => 'percent',
                'mean' => null,
                'level' => null,
                'readiness_percent' => null,
                'context_flag' => null,
                'role' => null,
                'sum' => null,
                'items' => null,
                'score' => max(0.0, min(100.0, (float) $score)),
            ];
        }

        return $rows === [] ? null : $rows;
    }
}
