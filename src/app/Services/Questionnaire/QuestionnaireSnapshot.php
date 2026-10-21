<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireAnswer;
use App\Models\Student;

/**
 * The submitted questionnaire response a prediction used, copied with its
 * definition version, item text, and answers. Stored on the prediction and
 * never rewritten. Household-profile fields stay in the encrypted profile.
 * Socioeconomic questionnaire answers are encrypted on the response and are
 * not copied into this snapshot, because staff-accessible history must not
 * hold the raw values.
 */
final class QuestionnaireSnapshot
{
    public const SNAPSHOT_VERSION = 2;

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

        $omitted = 0;
        $answers = $response->answers
            ->sortBy('questionnaire_item_id')
            ->map(function (QuestionnaireAnswer $answer) use (&$omitted): ?array {
                if ($answer->item?->isSensitive()) {
                    $omitted++;

                    return null;
                }

                return [
                    'questionnaire_item_id' => $answer->questionnaire_item_id,
                    'section' => $answer->item?->section,
                    'construct' => $answer->item?->construct,
                    'code' => $answer->item?->displayCode(),
                    'text' => $answer->item?->text,
                    'reverse_scored' => (bool) $answer->item?->reverse_scored,
                    'value' => $answer->responseValue(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'version' => self::SNAPSHOT_VERSION,
            'submitted' => true,
            'questionnaire_response_id' => $response->id,
            'definition_version' => $response->definition_version,
            'submitted_at' => $response->submitted_at?->toIso8601String(),
            'construct_scores' => is_array($response->construct_scores) ? $response->construct_scores : [],
            'sensitive_answers_omitted' => $omitted,
            'answers' => $answers,
        ];
    }
}
