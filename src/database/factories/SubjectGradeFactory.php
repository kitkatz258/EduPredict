<?php

namespace Database\Factories;

use App\Models\GradeReport;
use App\Models\SubjectGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectGrade>
 */
class SubjectGradeFactory extends Factory
{
    public function definition(): array
    {
        $grade = fake()->randomElement(['1.00', '1.25', '1.50', '1.75', '2.00', '2.25', '2.50', '2.75', '3.00', '5.00']);
        $failed = $grade === '5.00';

        return [
            'grade_report_id' => GradeReport::factory(),
            'subject_code' => strtoupper(fake()->bothify('?? ###')),
            'subject_name' => fake()->words(3, true),
            'units' => fake()->randomElement([2, 3, 5]),
            'midterm_grade' => $grade,
            'final_exam_grade' => $grade,
            'final_grade' => $grade,
            'remarks' => $failed ? 'FAILED' : 'PASSED',
            'is_failed' => $failed,
            'is_major_subject' => fake()->boolean(40),
            'needs_review' => false,
        ];
    }
}
