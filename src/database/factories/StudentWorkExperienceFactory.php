<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentWorkExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentWorkExperience>
 */
class StudentWorkExperienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'experience_type' => 'part_time',
            'organization' => 'Campus library',
            'role_title' => 'Student assistant',
            'start_date' => '2025-06-01',
            'end_date' => '2025-12-15',
            'is_ongoing' => false,
        ];
    }

    public function internship(): static
    {
        return $this->state(fn (): array => [
            'experience_type' => StudentWorkExperience::TYPE_OJT_INTERNSHIP,
            'organization' => 'City Hall ICT Office',
            'role_title' => 'OJT trainee',
        ]);
    }
}
