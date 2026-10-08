<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireAnswer;
use App\Models\Student;

/**
 * The submitted questionnaire response a prediction used, copied with its
 * definition version, item text, and answers. Stored on the prediction and
 * never rewritten. Socioeconomic answers live in the encrypted profile and
 * are not copied here.
 */
final class QuestionnaireSnapshot
{
    public const SNAPSHOT_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function for(Student $student): array
    {
        $response = $student->questionnaireResponses()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->with('answers.item')
            ->first();

        if ($response === null) {
            return ['version' => self::SNAPSHOT_VERSION, 'submitted' => false];
        }

        return [
            'version' => self::SNAPSHOT_VERSION,
            'submitted' => true,
            'questionnaire_response_id' => $response->id,
            'definition_version' => $response->definition_version,
            'submitted_at' => $response->submitted_at?->toIso8601String(),
            'construct_scores' => is_array($response->construct_scores) ? $response->construct_scores : [],
            'answers' => $response->answers
                ->sortBy('questionnaire_item_id')
                ->map(fn (QuestionnaireAnswer $answer): array => [
                    'questionnaire_item_id' => $answer->questionnaire_item_id,
                    'section' => $answer->item?->section,
                    'construct' => $answer->item?->construct,
                    'text' => $answer->item?->text,
                    'reverse_scored' => (bool) $answer->item?->reverse_scored,
                    'value' => $answer->value,
                ])->values()->all(),
        ];
    }
}
