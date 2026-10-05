<?php

declare(strict_types=1);

namespace App\Services\Interventions;

use App\Models\Intervention;
use App\Models\Prediction;
use Illuminate\Support\Collection;

/**
 * Maps a prediction's hurting factors onto the predefined intervention list.
 * The score is a rank only. It is never shown as a probability or percentage.
 */
final class InterventionSelector
{
    /**
     * @return Collection<int, Intervention>
     */
    public function select(Prediction $prediction): Collection
    {
        $risk = (string) $prediction->dropout_risk;
        if (! in_array($risk, ['moderate', 'high'], true)) {
            return collect();
        }

        $interventions = Intervention::query()->orderBy('code')->get()
            ->filter(fn (Intervention $intervention): bool => $this->meetsMinimum($risk, (string) $intervention->min_risk_level))
            ->values();

        if ($interventions->isEmpty()) {
            return collect();
        }

        $negatives = $this->negativeFactors($prediction);
        $flag = (string) $prediction->program_shift_flag;
        $bonus = (float) config('edupredict.interventions.shift_rank_bonus', 0.5);
        $ranked = [];

        foreach ($interventions as $intervention) {
            $score = $this->score($intervention, $negatives, $flag, $bonus);
            if ($score <= 0) {
                continue;
            }
            $ranked[] = ['intervention' => $intervention, 'score' => $score];
        }

        if ($ranked === []) {
            $ranked = $this->generalFallback($interventions);
        }

        usort($ranked, function (array $left, array $right): int {
            return $right['score'] <=> $left['score']
                ?: strcmp((string) $left['intervention']->code, (string) $right['intervention']->code);
        });

        $limit = max(1, (int) config('edupredict.interventions.max_actions', 5));

        return collect($ranked)
            ->take($limit)
            ->map(fn (array $row): Intervention => $row['intervention'])
            ->values();
    }

    /**
     * @param  array<string, float>  $negatives
     */
    private function score(Intervention $intervention, array $negatives, string $flag, float $bonus): float
    {
        $score = 0.0;
        foreach ($this->targets($intervention) as $target) {
            if ($target === 'general') {
                continue;
            }
            if ($target === 'program_fit' && in_array($flag, ['program_fit', 'mixed'], true)) {
                $score += $bonus;
            } elseif ($target === 'disengagement' && in_array($flag, ['disengagement', 'mixed'], true)) {
                $score += $bonus;
            } elseif (isset($negatives[$target])) {
                $score += $negatives[$target];
            }
        }

        return round($score, 4);
    }

    /**
     * @param  Collection<int, Intervention>  $interventions
     * @return list<array{intervention: Intervention, score: float}>
     */
    private function generalFallback(Collection $interventions): array
    {
        $rows = [];
        foreach ($interventions as $intervention) {
            if (! in_array('general', $this->targets($intervention), true)) {
                continue;
            }
            $rows[] = ['intervention' => $intervention, 'score' => 0.01];
        }

        return $rows;
    }

    /**
     * Hurting factors from the dropout group and the employability group.
     * Legacy seeded rows store a flat list and are read the same way.
     *
     * @return array<string, float>
     */
    private function negativeFactors(Prediction $prediction): array
    {
        $factors = $prediction->factors;
        if (! is_array($factors)) {
            return [];
        }

        $groups = [];
        if (array_key_exists('dropout', $factors) || array_key_exists('employability', $factors)) {
            $groups[] = $factors['dropout'] ?? [];
            $groups[] = $factors['employability'] ?? [];
        } elseif (array_is_list($factors)) {
            $groups[] = $factors;
        }

        $scores = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group as $factor) {
                if (! is_array($factor) || ($factor['direction'] ?? '') !== '-') {
                    continue;
                }
                $feature = trim((string) ($factor['feature'] ?? ''));
                $magnitude = (float) ($factor['magnitude'] ?? 0);
                if ($feature === '' || $magnitude <= 0) {
                    continue;
                }
                $scores[$feature] = max($scores[$feature] ?? 0.0, $magnitude);
            }
        }

        return $scores;
    }

    /**
     * @return list<string>
     */
    private function targets(Intervention $intervention): array
    {
        $targets = $intervention->targets_factor;

        return is_array($targets) ? array_map('strval', $targets) : [];
    }

    private function meetsMinimum(string $risk, string $minimum): bool
    {
        $rank = ['low' => 1, 'moderate' => 2, 'high' => 3];
        if (! isset($rank[$risk], $rank[$minimum])) {
            return false;
        }

        return $rank[$risk] >= $rank[$minimum];
    }
}
