<?php

namespace Database\Factories;

use App\Models\GradeReport;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeReport>
 */
class GradeReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_year' => '2024-2025',
            'semester' => fake()->randomElement(['First', 'Second']),
            'source' => 'manual',
            'status' => 'confirmed',
            'original_file_path' => null,
            'confirmed_at' => now(),
        ];
    }
}
