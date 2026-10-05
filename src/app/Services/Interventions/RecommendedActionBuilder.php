<?php

declare(strict_types=1);

namespace App\Services\Interventions;

use App\Models\Prediction;
use App\Models\RecommendedAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores the chosen interventions for one prediction. Rows are inserted once.
 */
final class RecommendedActionBuilder
{
    public const STUDENT_MESSAGE = 'Some areas suggest you may benefit from additional support. Your adviser can talk through the options with you. The specific suggestions stay with faculty.';

    public function __construct(
        private InterventionSelector $selector,
        private ActionPhrasingService $phrasing,
    ) {}

    /**
     * @return Collection<int, RecommendedAction>
     */
    public function ensure(Prediction $prediction, bool $allowAi = true): Collection
    {
        $existing = $prediction->recommendedActions()->with('intervention')->orderBy('id')->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        if (! in_array($prediction->dropout_risk, ['moderate', 'high'], true)) {
            return $existing;
        }

        $selected = $this->selector->select($prediction);
        if ($selected->isEmpty()) {
            return $existing;
        }

        $phrased = $this->phrasing->phrase($prediction, $selected, $allowAi);
        $byCode = [];
        foreach ($phrased as $row) {
            $byCode[$row['code']] = $row;
        }

        DB::transaction(function () use ($prediction, $selected, $byCode): void {
            foreach ($selected as $intervention) {
                $copy = $byCode[$intervention->code] ?? null;
                RecommendedAction::query()->create([
                    'prediction_id' => $prediction->id,
                    'intervention_id' => $intervention->id,
                    'phrased_text' => $copy['text'] ?? $intervention->description,
                    'phrasing_source' => $copy['source'] ?? 'rule_based',
                ]);
            }
        });

        return $prediction->recommendedActions()->with('intervention')->orderBy('id')->get();
    }
}
