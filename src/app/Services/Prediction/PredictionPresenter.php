<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\Prediction;
use App\Models\Student;

final class PredictionPresenter
{
    /**
     * @return array{employability: string, dropout: string, top: list<array<string, mixed>>, model: string}|null
     */
    public function summary(?Prediction $prediction, bool $forStudent): ?array
    {
        if ($prediction === null) {
            return null;
        }

        $groups = $this->groups($prediction);

        return [
            'employability' => $this->employabilitySentence($prediction, $forStudent),
            'dropout' => $this->dropoutSentence($prediction, $groups['dropout'], $forStudent),
            'top' => array_slice($groups['employability'], 0, 3),
            'model' => (string) $prediction->model_version,
        ];
    }

    /**
     * @return array{history: array<string, list<mixed>>, employability: array<string, list<mixed>>, dropout: array<string, list<mixed>>}
     */
    public function charts(Student $student, ?Prediction $latest): array
    {
        $history = $student->predictions()->orderBy('created_at')->orderBy('id')->get();
        $groups = $this->groups($latest);

        return [
            'history' => [
                'labels' => $history->map(fn (Prediction $prediction): string => $prediction->created_at?->timezone((string) config('app.timezone'))->format('M j') ?? '')->all(),
                'employability' => $history->map(fn (Prediction $prediction): float => round((float) $prediction->employability_score, 2))->all(),
                'dropout' => $history->map(fn (Prediction $prediction): float => round((float) $prediction->dropout_probability * 100, 2))->all(),
            ],
            'employability' => $this->bars($groups['employability']),
            'dropout' => $this->bars($groups['dropout']),
        ];
    }

    /**
     * @return array{employability: list<array<string, mixed>>, dropout: list<array<string, mixed>>}
     */
    public function groups(?Prediction $prediction): array
    {
        $factors = $prediction?->factors;
        if (! is_array($factors)) {
            return ['employability' => [], 'dropout' => []];
        }

        if (array_key_exists('employability', $factors) || array_key_exists('dropout', $factors)) {
            return [
                'employability' => $this->normalize($factors['employability'] ?? []),
                'dropout' => $this->normalize($factors['dropout'] ?? []),
            ];
        }

        $legacy = $this->normalize($factors);

        return ['employability' => $legacy, 'dropout' => $legacy];
    }

    private function employabilitySentence(Prediction $prediction, bool $forStudent): string
    {
        $score = number_format((float) $prediction->employability_score, 0);
        $opening = $forStudent
            ? "Your estimated employability is {$score} out of 100."
            : "Estimated employability is {$score} out of 100.";

        return $opening.' This estimate uses academics, skills, experience, and self-reported study patterns.';
    }

    /**
     * @param  list<array<string, mixed>>  $dropoutFactors
     */
    private function dropoutSentence(Prediction $prediction, array $dropoutFactors, bool $forStudent): string
    {
        $range = match ($prediction->dropout_risk) {
            'high' => 'the higher range',
            'moderate' => 'the moderate range',
            default => 'the lower range',
        };
        $areas = [];
        foreach ($dropoutFactors as $factor) {
            if (($factor['direction'] ?? '') === '-' && isset($factor['label'])) {
                $areas[] = (string) $factor['label'];
            }
            if (count($areas) === 3) {
                break;
            }
        }

        $support = $areas === []
            ? 'No major area stands out as needing extra support in this estimate.'
            : 'Areas where support could help: '.implode(', ', $areas).'.';
        $contact = $forStudent
            ? ' Your department can talk through it with you.'
            : ' Talk it through with the student before any action.';

        return "This estimate sits in {$range}. {$support}{$contact}";
    }

    /**
     * @param  list<array<string, mixed>>  $factors
     * @return array{labels: list<string>, magnitudes: list<float>, colors: list<string>}
     */
    private function bars(array $factors): array
    {
        $factors = array_slice($factors, 0, 8);

        return [
            'labels' => array_map(fn (array $factor): string => (string) ($factor['label'] ?? $factor['feature'] ?? 'Factor'), $factors),
            'magnitudes' => array_map(fn (array $factor): float => (float) ($factor['magnitude'] ?? 0), $factors),
            'colors' => array_map(
                fn (array $factor): string => ($factor['direction'] ?? '-') === '+' ? '#1B5E20' : '#B45309',
                $factors,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalize(mixed $factors): array
    {
        if (! is_array($factors) || array_is_list($factors) === false) {
            return [];
        }

        $rows = [];
        foreach ($factors as $factor) {
            if (is_array($factor) && isset($factor['label'])) {
                $rows[] = $factor;
            }
        }

        return $rows;
    }
}
