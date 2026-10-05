<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        $program = Program::query()->inRandomOrder()->first() ?? Program::factory()->create();

        return [
            'user_id' => User::factory()->role(UserRole::Student),
            'student_number' => fake()->unique()->numerify('202#-#####'),
            'program_id' => $program->id,
            'year_level' => fake()->numberBetween(1, 4),
            'adviser_id' => null,
            'enrollment_year' => fake()->numberBetween(2021, 2026),
            'semesters_completed' => fake()->numberBetween(0, 8),
            'consent_version' => 'v1',
        ];
    }
}
