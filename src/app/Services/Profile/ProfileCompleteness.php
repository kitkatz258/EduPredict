<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\Student;

final class ProfileCompleteness
{
    /**
     * Equal weight across academic, socioeconomic, skills, and questionnaire.
     *
     * @return array{percent: int, sections: array<string, bool>}
     */
    public function for(Student $student): array
    {
        $socioeconomic = $student->socioeconomicProfile()->first();
        $skills = $student->skillsExperience()->first();

        $sections = [
            'academic' => $student->gradeReports()->where('status', 'confirmed')->exists(),
            'socioeconomic' => $socioeconomic !== null && ! $socioeconomic->is_draft,
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
