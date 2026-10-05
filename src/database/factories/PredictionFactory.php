<?php

namespace Database\Factories;

use App\Models\Prediction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
{
    public function definition(): array
    {
        $risk = fake()->randomElement(['low', 'moderate', 'high']);
        $probability = match ($risk) {
            'low' => fake()->randomFloat(4, 0.05, 0.29),
            'moderate' => fake()->randomFloat(4, 0.30, 0.59),
            default => fake()->randomFloat(4, 0.60, 0.92),
        };

        return [
            'student_id' => Student::factory(),
            'requested_by' => User::factory(),
            'model_version' => 'placeholder-heuristic-v0',
            'employability_score' => fake()->randomFloat(2, 40, 95),
            'dropout_probability' => $probability,
            'dropout_risk' => $risk,
            'confidence' => fake()->randomElement(['normal', 'low']),
            'program_shift_flag' => fake()->randomElement(['none', 'program_fit', 'disengagement', 'mixed']),
            'factors' => [
                ['feature' => 'gwa', 'label' => 'General weighted average', 'direction' => '+', 'magnitude' => 0.4],
            ],
            'feature_snapshot' => [
                'gwa_band' => '2.00-2.25',
                'failed_subjects' => 0,
            ],
        ];
    }
}
