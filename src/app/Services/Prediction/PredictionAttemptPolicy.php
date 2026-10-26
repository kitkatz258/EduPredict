<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\PredictionAttemptGrant;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * Two successful predictions per student per Asia/Manila calendar day.
 * Administrator extras are additive rows in prediction_attempt_grants.
 * M38 writes those rows. This class only reads them and never rewrites predictions.
 */
final class PredictionAttemptPolicy
{
    public function timezone(): string
    {
        return (string) config('edupredict.prediction.timezone', 'Asia/Manila');
    }

    public function dailyLimit(): int
    {
        return max(0, (int) config('edupredict.prediction.daily_limit', 2));
    }

    /**
     * Extra attempts granted for this Philippine date.
     * The count of successful predictions is left unchanged.
     */
    public function extraAttempts(Student $student, CarbonInterface $instant): int
    {
        $date = $instant->copy()->timezone($this->timezone())->toDateString();

        return (int) PredictionAttemptGrant::query()
            ->where('student_id', $student->id)
            ->whereDate('manila_date', $date)
            ->sum('extra_attempts');
    }

    /**
     * Inclusive start and exclusive end of the Philippine day, expressed in the
     * application timezone so created_at comparisons match stored clocks.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function storedWindow(CarbonInterface $instant): array
    {
        $start = $instant->copy()->timezone($this->timezone())->startOfDay();
        $end = $start->copy()->addDay();
        $appTimezone = (string) config('app.timezone');

        return [$start->timezone($appTimezone), $end->timezone($appTimezone)];
    }
}
