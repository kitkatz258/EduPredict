<?php

namespace Database\Factories;

use App\Models\InstitutionStudent;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitutionStudent>
 */
class InstitutionStudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_number' => fake()->unique()->numerify('202#-#####'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'program_id' => Program::factory(),
            'year_level' => fake()->numberBetween(1, 4),
            'birthdate' => fake()->dateTimeBetween('-24 years', '-17 years')->format('Y-m-d'),
            'email' => fake()->unique()->safeEmail(),
            'is_registered' => false,
        ];
    }
}
