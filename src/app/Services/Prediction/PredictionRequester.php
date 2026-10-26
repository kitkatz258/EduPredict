<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Contracts\PredictorInterface;
use App\Enums\UserRole;
use App\Models\Prediction;
use App\Models\QuestionnaireItem;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentPredictionReady;
use App\Services\Audit\AuditLogger;
use App\Services\Career\CareerMatchBuilder;
use App\Services\Grades\AcademicSummary;
use App\Services\Grades\GradeSnapshot;
use App\Services\Interventions\RecommendedActionBuilder;
use App\Services\Profile\SkillsExperienceRecords;
use App\Services\Questionnaire\QuestionnaireSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Inserts a prediction. Existing rows are never updated.
 */
final class PredictionRequester
{
    public function __construct(
        private FeatureBuilder $features,
        private PredictorInterface $predictor,
        private AcademicSummary $academic,
        private ProgramShiftEvaluator $programShift,
        private CareerMatchBuilder $careers,
        private RecommendedActionBuilder $actions,
        private AuditLogger $audit,
        private SkillsExperienceRecords $skillRecords,
        private GradeSnapshot $grades,
        private QuestionnaireSnapshot $questionnaire,
        private PredictionAttemptPolicy $attempts,
    ) {}

    /**
     * Successful predictions already stored for the Philippine day of $instant.
     * Rejected requests are not rows, so they are not counted.
     *
     * @return array{used: int, base_limit: int, extra: int, limit: int, remaining: int, resets_at: CarbonInterface, timezone: string}
     */
    public function quota(Student $student, ?CarbonInterface $instant = null): array
    {
        $instant ??= now();
        $timezone = $this->attempts->timezone();
        $local = $instant->copy()->timezone($timezone);
        [$start, $end] = $this->attempts->storedWindow($instant);
        $used = $student->predictions()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
        $extra = $this->attempts->extraAttempts($student, $instant);
        $base = $this->attempts->dailyLimit();
        $limit = $base + $extra;

        return [
            'used' => $used,
            'base_limit' => $base,
            'extra' => $extra,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'resets_at' => $local->copy()->startOfDay()->addDay(),
            'timezone' => $timezone,
        ];
    }

    public const RESEARCH_ENGINE_BLOCK = 'Student predictions stay on the rule-based placeholder. The research inference service does not score this assessment.';

    public function blockMessage(Student $student): ?string
    {
        if ((string) config('edupredict.predictor.driver', 'heuristic') !== 'heuristic') {
            return self::RESEARCH_ENGINE_BLOCK;
        }

        $student->loadMissing('user');
        if ($student->user?->consented_at === null) {
            return 'Consent is required before a new prediction.';
        }

        if (! $this->hasCurrentQuestionnaire($student)) {
            return 'Complete the required Assessment sections before requesting a prediction. Still needed: a completed submission of the current questionnaire.';
        }

        $quota = $this->quota($student);
        if ($quota['remaining'] < 1) {
            return $this->quotaMessage($quota);
        }

        return $this->unsupportedEstimateMessage($this->features->build($student));
    }

    public function request(Student $student, User $actor): Prediction
    {
        abort_unless(
            $actor->isRole(UserRole::Student) && $actor->id === $student->user_id,
            403,
        );

        $blocked = $this->blockMessage($student);
        if ($blocked !== null) {
            throw new PredictionBlockedException($blocked);
        }

        $this->academic->syncStudent($student);
        $current = $student->fresh();
        $featureSet = $this->features->build($current);
        $unsupported = $this->unsupportedEstimateMessage($featureSet);
        if ($unsupported !== null) {
            throw new PredictionBlockedException($unsupported);
        }

        $assessment = [
            'skills_experience' => $this->skillRecords->snapshot($current),
            'grades' => $this->grades->for($current),
            'questionnaire' => $this->questionnaire->for($current),
        ];
        $employability = $this->predictor->predictEmployability($featureSet);
        $dropout = $this->predictor->predictDropout($featureSet);
        $programShift = $this->programShift->evaluate($featureSet, $dropout->factors);

        $prediction = DB::transaction(function () use ($student, $actor, $featureSet, $assessment, $employability, $dropout, $programShift): Prediction {
            Student::query()->whereKey($student->id)->lockForUpdate()->first();
            $quota = $this->quota($student);
            if ($quota['remaining'] < 1) {
                throw new PredictionBlockedException($this->quotaMessage($quota));
            }

            $prediction = Prediction::query()->create([
                'student_id' => $student->id,
                'requested_by' => $actor->id,
                'model_version' => $employability->modelVersion,
                'employability_score' => $employability->score,
                'dropout_probability' => $dropout->probability,
                'dropout_risk' => $dropout->riskLevel,
                'confidence' => $this->confidenceFor($featureSet, $dropout->confidence),
                'program_shift_flag' => $programShift->flag,
                'factors' => [
                    'employability' => array_map(
                        fn (ContributingFactor $factor): array => $factor->toArray(),
                        $employability->factors,
                    ),
                    'dropout' => array_map(
                        fn (ContributingFactor $factor): array => $factor->toArray(),
                        $dropout->factors,
                    ),
                    'program_shift' => $programShift->toArray(),
                ],
                'feature_snapshot' => $this->snapshot($featureSet),
                'assessment_snapshot' => $assessment,
            ]);

            $student->loadMissing('user', 'program');
            User::query()->reviewersOf($student)->get()
                ->each(fn (User $reviewer) => $reviewer->notify(new StudentPredictionReady($student, $prediction)));

            $this->audit->record('prediction_requested', $prediction, [
                'student_id' => $student->id,
                'model_version' => $prediction->model_version,
                'dropout_risk' => $prediction->dropout_risk,
            ], $actor);

            return $prediction;
        });

        $this->careers->ensure($prediction);
        $this->actions->ensure($prediction);

        return $prediction;
    }

    public const INSUFFICIENT_ESTIMATE = 'Insufficient information for a reliable estimate';

    private function hasCurrentQuestionnaire(Student $student): bool
    {
        $version = (string) config('edupredict.questionnaire.current_version');
        $required = QuestionnaireItem::query()
            ->where('definition_version', $version)
            ->where('is_active', true)
            ->pluck('id');

        if ($required->isEmpty()) {
            return $student->questionnaireResponses()->whereNotNull('submitted_at')->exists();
        }

        $submission = $student->questionnaireResponses()
            ->where('definition_version', $version)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        if ($submission === null) {
            return false;
        }

        $answered = $submission->answers()
            ->whereIn('questionnaire_item_id', $required)
            ->pluck('questionnaire_item_id')
            ->unique();

        return $required->diff($answered)->isEmpty();
    }

    /**
     * The placeholder can omit a missing field. It must not invent a score
     * when every scored input would be a silent zero.
     */
    private function unsupportedEstimateMessage(FeatureSet $features): ?string
    {
        if ($features->gwa !== null) {
            return null;
        }

        foreach ($features->constructScores() as $score) {
            if ($score !== null) {
                return null;
            }
        }

        if ($features->technicalSkillCount > 0 || $features->certificationCount > 0 || $features->internshipCount > 0 || $features->workExperienceCount > 0) {
            return null;
        }

        return self::INSUFFICIENT_ESTIMATE;
    }

    private function confidenceFor(FeatureSet $features, string $confidence): string
    {
        if ($features->gwa === null || in_array(null, $features->constructScores(), true)) {
            return 'low';
        }

        return $confidence;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FeatureSet $features): array
    {
        $snapshot = $features->toSnapshot();
        $unavailable = [];

        if ($features->gwa === null) {
            $unavailable[] = 'gwa';
            $unavailable[] = 'failed_subjects';
        }

        foreach ($features->constructScores() as $key => $score) {
            if ($score === null) {
                $unavailable[] = $key;
            }
        }

        if ($features->technicalSkillCount === 0 && $features->certificationCount === 0 && $features->internshipCount === 0 && $features->workExperienceCount === 0) {
            $unavailable[] = 'skills_experience';
        }

        foreach ([
            'scholarship_status',
            'has_scholarship',
            'income_bracket',
            'household_size',
            'living_arrangement',
            'internet_access',
            'device_access',
            'study_space',
            'employment_status',
        ] as $key) {
            $snapshot[$key] = null;
            $unavailable[] = $key;
        }

        $snapshot['unavailable_inputs'] = $unavailable;
        $snapshot['estimate_note'] = 'Workflow completeness is separate from placeholder confidence and from the risk classification. Missing grades and unmapped questionnaire section means are omitted. They are not scored as zero. Skills that were not logged are not treated as zero ability. Discontinued household profile fields are not used in a new prediction, even when an older encrypted profile is still on file. They are not treated as poverty, as no scholarship, or as zero.';

        return $snapshot;
    }

    /**
     * @param  array{used: int, limit: int, resets_at: CarbonInterface, timezone: string}  $quota
     */
    private function quotaMessage(array $quota): string
    {
        $reset = $quota['resets_at']->timezone($quota['timezone'])->format('M j, Y g:i A');

        return 'You have used '.$quota['used'].' of '.$quota['limit'].' predictions for this Philippine calendar day. You can request another after '.$reset.'.';
    }
}
