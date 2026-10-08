<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSkill>
 */
class StudentSkillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'name' => fake()->randomElement(['PHP', 'SQL', 'Data analysis', 'Public speaking', 'Research writing']),
        ];
    }
}
