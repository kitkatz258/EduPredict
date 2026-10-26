<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\PredictionAttemptGrant;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds extra successful-prediction slots for one Asia/Manila calendar date.
 * Historical prediction rows and the base daily limit stay unchanged.
 */
class PredictionGrantService
{
    public function grant(Student $student, string $manilaDate, int $extraAttempts, string $reason, User $actor, ?string $ip = null): PredictionAttemptGrant
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A justification is required.',
            ]);
        }

        if ($extraAttempts < 1 || $extraAttempts > 9) {
            throw ValidationException::withMessages([
                'extraAttempts' => 'Grant between 1 and 9 extra successful predictions.',
            ]);
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $manilaDate, 'Asia/Manila');
        } catch (\Throwable) {
            $date = false;
        }

        if ($date === false) {
            throw ValidationException::withMessages([
                'manilaDate' => 'Choose a valid Asia/Manila calendar date.',
            ]);
        }

        return DB::transaction(function () use ($student, $date, $extraAttempts, $reason, $actor, $ip): PredictionAttemptGrant {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            $grant = PredictionAttemptGrant::query()->create([
                'student_id' => $student->id,
                'manila_date' => $date->toDateString(),
                'extra_attempts' => $extraAttempts,
                'granted_by' => $actor->id,
                'reason' => $reason,
            ]);

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'prediction_grant_created',
                'subject_type' => PredictionAttemptGrant::class,
                'subject_id' => $grant->id,
                'meta' => [
                    'student_id' => $student->id,
                    'manila_date' => $date->toDateString(),
                    'extra_attempts' => $extraAttempts,
                ],
                'ip' => $ip,
            ]);

            return $grant;
        });
    }
}
