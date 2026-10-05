<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\Prediction;
use App\Models\RecommendedAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendedAction>
 */
class RecommendedActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prediction_id' => Prediction::factory(),
            'intervention_id' => Intervention::factory(),
            'phrased_text' => 'An adviser can talk through this option with the student.',
            'phrasing_source' => 'rule_based',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'reviewer_note' => null,
        ];
    }
}
