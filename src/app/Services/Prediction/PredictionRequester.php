<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Contracts\PredictorInterface;
use App\Enums\UserRole;
use App\Models\Prediction;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentPredictionReady;
use App\Services\Audit\AuditLogger;
use App\Services\Career\CareerMatchBuilder;
use App\Services\Grades\AcademicSummary;
use App\Services\Grades\GradeSnapshot;
use App\Services\Interventions\RecommendedActionBuilder;
use App\Services\Profile\AssessmentProgress;
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
        private AssessmentProgress $progress,
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
        $progress = $this->progress->for($student);
        $labels = [
            'socioeconomic' => 'a saved socioeconomic profile',
            'skills' => 'saved skills and experience',
            'questionnaire' => 'a submitted questionnaire',
        ];
        $missing = [];
        foreach ($labels as $section => $label) {
            if (! $progress['sections'][$section]) {
                $missing[] = $label;
            }
        }

        if ($missing !== []) {
            return 'Complete the required Assessment sections before requesting a prediction. Still needed: '.implode(', ', $missing).'.';
        }

        $ends = $this->cooldownEndsAt($student);
        if ($ends !== null && $ends->isFuture()) {
            return 'You can request another prediction after '.$ends->timezone((string) config('app.timezone'))->format('M j, Y g:i A').'.';
        }

        return null;
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
                'confidence' => $dropout->confidence,
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
                'feature_snapshot' => $featureSet->toSnapshot(),
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
}
