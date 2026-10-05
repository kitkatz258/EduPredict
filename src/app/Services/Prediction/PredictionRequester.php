<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Contracts\PredictorInterface;
use App\Enums\UserRole;
use App\Models\Prediction;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AdviseePredictionReady;
use App\Services\Career\CareerMatchBuilder;
use App\Services\Grades\AcademicSummary;
use App\Services\Profile\ProfileCompleteness;
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
        private ProfileCompleteness $completeness,
        private AcademicSummary $academic,
        private ProgramShiftEvaluator $programShift,
        private CareerMatchBuilder $careers,
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
        $profile = $this->completeness->for($student);
        $minimum = (int) config('edupredict.prediction.min_profile_completeness', 80);

        if ($profile['percent'] < $minimum) {
            $labels = [
                'academic' => 'a confirmed grade report',
                'socioeconomic' => 'a saved socioeconomic profile',
                'skills' => 'saved skills and experience',
                'questionnaire' => 'a submitted questionnaire',
            ];
            $missing = [];
            foreach ($profile['sections'] as $section => $done) {
                if (! $done) {
                    $missing[] = $labels[$section] ?? $section;
                }
            }

            return 'Complete your profile before requesting a prediction. Still needed: '.implode(', ', $missing).'.';
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
        $featureSet = $this->features->build($student->fresh());
        $employability = $this->predictor->predictEmployability($featureSet);
        $dropout = $this->predictor->predictDropout($featureSet);
        $programShift = $this->programShift->evaluate($featureSet, $dropout->factors);

        $prediction = DB::transaction(function () use ($student, $actor, $featureSet, $employability, $dropout, $programShift): Prediction {
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
            ]);

            $student->loadMissing('user', 'adviser');
            $adviser = $student->adviser;
            if ($adviser instanceof User) {
                $adviser->notify(new AdviseePredictionReady($student, $prediction));
            }

            return $prediction;
        });

        $this->careers->ensure($prediction);

        return $prediction;
    }
}
