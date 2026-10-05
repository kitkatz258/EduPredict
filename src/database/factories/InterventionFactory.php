<?php

namespace Database\Factories;

use App\Models\Intervention;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::slug(fake()->unique()->words(3, true)),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'targets_factor' => ['gwa'],
            'min_risk_level' => 'moderate',
        ];
    }
}
