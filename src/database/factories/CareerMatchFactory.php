<?php

namespace Database\Factories;

use App\Models\CareerMatch;
use App\Models\Prediction;
use App\Models\PsocOccupation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareerMatch>
 */
class CareerMatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prediction_id' => Prediction::factory(),
            'psoc_occupation_id' => PsocOccupation::factory(),
            'compatibility_score' => 70,
            'explanation' => 'This is a broad occupational category, not a job offer.',
            'explanation_source' => 'template',
            'matched_skills' => ['sql'],
            'missing_skills' => ['programming'],
        ];
    }
}
