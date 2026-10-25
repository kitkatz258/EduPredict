<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\Student;

final class AssessmentProgress
{
    /**
     * Percentage of workflow sections saved; not a model-quality measure.
     *
     * @return array{percent: int, sections: array<string, bool>}
     */
    public function for(Student $student): array
    {
        $skills = $student->skillsExperience()->first();

        // Household profile is no longer an active assessment step. Stored
        // socioeconomic rows stay encrypted and are not part of this meter.
        $sections = [
            'academic' => $student->gradeReports()->current()->exists(),
            'skills' => $skills !== null && ! $skills->is_draft,
            'questionnaire' => $student->questionnaireResponses()->whereNotNull('submitted_at')->exists(),
        ];

        $total = count($sections);
        $done = count(array_filter($sections));

        return [
            'percent' => $total === 0 ? 0 : (int) round(($done / $total) * 100),
            'sections' => $sections,
        ];
    }
}
