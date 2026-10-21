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
    ) {}

    public function cooldownEndsAt(Student $student): ?CarbonInterface
    {
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();
        if ($latest?->created_at === null) {
            return null;
        }

        return $latest->created_at->copy()->addHours((int) config('edupredict.prediction.cooldown_hours', 24));
    }

    public function blockMessage(Student $student): ?string
    {
        $student->loadMissing('user');
        if ($student->user?->consented_at === null) {
            return 'Consent is required before a new prediction.';
        }

        if (! $this->hasCurrentQuestionnaire($student)) {
            return 'Complete the required Assessment sections before requesting a prediction. Still needed: a completed submission of the current questionnaire.';
        }

        $ends = $this->cooldownEndsAt($student);
        if ($ends !== null && $ends->isFuture()) {
            return 'You can request another prediction after '.$ends->timezone((string) config('app.timezone'))->format('M j, Y g:i A').'.';
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

        foreach ([$features->scholarshipStatus, $features->incomeBracket, $features->internetAccess, $features->deviceAccess, $features->studySpace, $features->employmentStatus] as $value) {
            if ($value !== null) {
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

        $snapshot['unavailable_inputs'] = $unavailable;
        $snapshot['estimate_note'] = 'Workflow completeness is separate from placeholder confidence and from the risk classification. Missing grades and unmapped questionnaire section means are omitted. They are not scored as zero. Skills that were not logged are not treated as zero ability.';

        return $snapshot;
    }
}
