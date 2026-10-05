<?php

namespace Database\Factories;

use App\Models\SkillsExperience;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillsExperience>
 */
class SkillsExperienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'technical_skills' => ['PHP', 'SQL'],
            'certifications' => [],
            'internships' => [],
            'projects' => [],
            'work_experience' => [],
            'is_draft' => false,
        ];
    }
}
