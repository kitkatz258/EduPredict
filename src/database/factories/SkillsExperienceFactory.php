<?php

namespace Database\Factories;

use App\Models\SkillsExperience;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Section status row. Entries live in student_skills, student_certifications,
 * and student_work_experiences; the JSON columns hold legacy data only.
 *
 * @extends Factory<SkillsExperience>
 */
class SkillsExperienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'is_draft' => false,
        ];
    }
}
