<?php

namespace Database\Factories;

use App\Models\QuestionnaireItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionnaireItem>
 */
class QuestionnaireItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'construct' => 'study_habits',
            'text' => fake()->sentence(),
            'reverse_scored' => false,
            'is_active' => true,
            'is_draft' => true,
            'sort_order' => 1,
        ];
    }
}
