<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentCertification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentCertification>
 */
class StudentCertificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'title' => 'IT Specialist - Databases',
            'issuer' => 'Certiport',
            'issued_year' => 2025,
        ];
    }
}
