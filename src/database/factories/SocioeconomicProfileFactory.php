<?php

namespace Database\Factories;

use App\Models\SocioeconomicProfile;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocioeconomicProfile>
 */
class SocioeconomicProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'household_income_bracket' => fake()->randomElement(['below_10k', '10k_20k', '20k_40k', '40k_70k', 'above_70k']),
            'household_size' => (string) fake()->numberBetween(2, 8),
            'scholarship_status' => fake()->randomElement(['none', 'partial', 'full']),
            'employment_status' => fake()->randomElement(['unemployed', 'part_time', 'working_student']),
            'living_arrangement' => fake()->randomElement(['with_family', 'boarding', 'relative']),
            'has_internet' => fake()->randomElement(['yes', 'no', 'unreliable']),
            'has_device' => fake()->randomElement(['yes', 'shared', 'no']),
            'has_study_space' => fake()->randomElement(['yes', 'no']),
            'is_draft' => false,
        ];
    }
}
