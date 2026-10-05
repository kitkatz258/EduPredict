<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\Prediction;

/**
 * Qualitative reading of dropout factors plus major-subject concentration.
 *
 * The placeholder's dropout factors for constructs are usually direction "-"
 * whenever the score is under 100, so health is taken from the same snapshot
 * scores that produced those factors. Major versus other performance is not a
 * dropout factor; it is stored on the feature snapshot. This is not a model
 * and it does not produce a percentage.
 */
final class ProgramShiftEvaluator
{
    /**
     * @param  list<ContributingFactor|array<string, mixed>>  $dropoutFactors
     */
    public function evaluate(FeatureSet $features, array $dropoutFactors): ProgramShiftAssessment
    {
        $labels = $this->labels($dropoutFactors);
        $healthyConstructs = $this->isHealthy($features->studyHabits)
            && $this->isHealthy($features->motivation)
            && $this->isHealthy($features->engagement);
        $lowEngagement = $this->isLow($features->engagement) || $this->isLow($features->motivation);
        $lowConsistency = $this->isLow($features->timeManagement) || $this->isHighProcrastination($features->procrastination);
        $concentrated = $this->concentratedInMajors($features);
        $broad = $this->broadPoorPerformance($features, $concentrated);

        $flag = match (true) {
            $concentrated && ($lowEngagement || $lowConsistency) => 'mixed',
            $concentrated && $healthyConstructs && ! $lowConsistency => 'program_fit',
            $lowEngagement || $lowConsistency || $broad => 'disengagement',
            default => 'none',
        };

        $factors = $this->reasons($flag, $features, $labels, $broad, $lowEngagement, $lowConsistency);

        return new ProgramShiftAssessment(
            flag: $flag,
            label: $this->label($flag),
            message: $this->message($flag),
            engagement: $this->engagementLabel($features->engagement),
            factors: $factors,
        );
    }

    /**
     * @return array{flag: string, label: string, message: string, engagement: ?string, factors: list<array{feature: string, label: string, note: string}>}
     */
    public function present(Prediction $prediction): array
    {
        $flag = $this->knownFlag((string) $prediction->program_shift_flag);
        $stored = is_array($prediction->factors) ? ($prediction->factors['program_shift'] ?? null) : null;
        $factors = [];
        $engagement = null;
        $message = $this->message($flag);
        $label = $this->label($flag);

        if (is_array($stored)) {
            $message = is_string($stored['message'] ?? null) ? $stored['message'] : $message;
            $label = is_string($stored['label'] ?? null) ? $stored['label'] : $label;
            $engagement = is_string($stored['engagement'] ?? null) ? $stored['engagement'] : null;
            $factors = $this->storedFactors($stored['factors'] ?? []);
        }

        if ($engagement === null) {
            $scores = is_array($prediction->feature_snapshot) ? ($prediction->feature_snapshot['construct_scores'] ?? []) : [];
            $score = is_array($scores) && isset($scores['engagement']) && is_numeric($scores['engagement'])
                ? (float) $scores['engagement']
                : null;
            $engagement = $this->engagementLabel($score);
        }

        return [
            'flag' => $flag,
            'label' => $label,
            'message' => $message,
            'engagement' => $engagement,
            'factors' => $factors,
        ];
    }

    public function label(string $flag): string
    {
        return match ($this->knownFlag($flag)) {
            'program_fit' => 'Program-fit concern',
            'disengagement' => 'Broader disengagement',
            'mixed' => 'Mixed signals',
            default => 'No shift pattern',
        };
    }

    public function message(string $flag): string
    {
        return match ($this->knownFlag($flag)) {
            'program_fit' => 'Academic performance is weaker in major subjects than in other subjects, while study habits, motivation, and engagement look steady. This may be worth a conversation with an adviser about program fit.',
            'disengagement' => 'This pattern looks more like engagement, motivation, consistency, or performance across subjects than a program-fit concern. Those are areas where support could help.',
            'mixed' => 'Major-subject results and day-to-day engagement both stand out. This may be worth a conversation with an adviser about program fit, alongside support for follow-through.',
            default => 'No program-shift pattern stands out in this estimate.',
        };
    }

    private function concentratedInMajors(FeatureSet $features): bool
    {
        $poorMajor = ($features->majorGwa !== null && $features->majorGwa >= $this->number('poor_gwa_min', 2.75))
            || $features->majorFailedSubjects >= 1;
        $otherHealthy = $features->otherFailedSubjects === 0
            && $features->otherGwa !== null
            && $features->otherGwa <= $this->number('healthy_other_gwa_max', 2.25);
        $gap = $features->majorGwa !== null
            && $features->otherGwa !== null
            && ($features->majorGwa - $features->otherGwa) >= $this->number('major_gap', 0.75);
        $enough = $features->majorUnits >= $this->number('min_major_units', 6)
            && $features->otherUnits >= $this->number('min_other_units', 3);

        return $enough && $poorMajor && $otherHealthy && ($gap || $features->majorFailedSubjects >= 1);
    }

    private function broadPoorPerformance(FeatureSet $features, bool $concentrated): bool
    {
        if ($concentrated) {
            return false;
        }

        $poorGwa = $features->gwa !== null && $features->gwa >= $this->number('poor_gwa_min', 2.75);

        return $poorGwa || $features->failedSubjects >= (int) $this->number('broad_failed_min', 2);
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{feature: string, label: string, note: string}>
     */
    private function reasons(
        string $flag,
        FeatureSet $features,
        array $labels,
        bool $broad,
        bool $lowEngagement,
        bool $lowConsistency,
    ): array {
        $rows = [];

        if (in_array($flag, ['program_fit', 'mixed'], true)) {
            $major = $features->majorGwa === null ? 'n/a' : number_format($features->majorGwa, 2);
            $other = $features->otherGwa === null ? 'n/a' : number_format($features->otherGwa, 2);
            $rows[] = [
                'feature' => 'major_subjects',
                'label' => 'Major subjects',
                'note' => "Weaker than other subjects (major GWA {$major}, other GWA {$other})",
            ];
        }

        if ($flag === 'program_fit') {
            foreach (['study_habits' => 'Study habits', 'motivation' => 'Motivation', 'engagement' => 'Engagement'] as $feature => $fallback) {
                $rows[] = [
                    'feature' => $feature,
                    'label' => $labels[$feature] ?? $fallback,
                    'note' => 'Steady',
                ];
            }
        }

        if (in_array($flag, ['disengagement', 'mixed'], true)) {
            if ($lowEngagement || $this->isLow($features->engagement)) {
                $rows[] = [
                    'feature' => 'engagement',
                    'label' => $labels['engagement'] ?? 'Engagement',
                    'note' => 'An area where support could help',
                ];
            }
            if ($this->isLow($features->motivation)) {
                $rows[] = [
                    'feature' => 'motivation',
                    'label' => $labels['motivation'] ?? 'Motivation',
                    'note' => 'An area where support could help',
                ];
            }
            if ($lowConsistency) {
                $feature = $this->isLow($features->timeManagement) ? 'time_management' : 'procrastination';
                $fallback = $feature === 'time_management' ? 'Time management' : 'Procrastination';
                $rows[] = [
                    'feature' => $feature,
                    'label' => $labels[$feature] ?? $fallback,
                    'note' => 'Consistency is an area where support could help',
                ];
            }
            if ($broad) {
                $rows[] = [
                    'feature' => 'gwa',
                    'label' => $labels['gwa'] ?? 'General weighted average',
                    'note' => 'Performance is spread across subject types',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<ContributingFactor|array<string, mixed>>  $dropoutFactors
     * @return array<string, string>
     */
    private function labels(array $dropoutFactors): array
    {
        $labels = [];
        foreach ($dropoutFactors as $factor) {
            if ($factor instanceof ContributingFactor) {
                $labels[$factor->feature] = $factor->label;
                continue;
            }
            if (is_array($factor) && isset($factor['feature'], $factor['label'])) {
                $labels[(string) $factor['feature']] = (string) $factor['label'];
            }
        }

        return $labels;
    }

    /**
     * @return list<array{feature: string, label: string, note: string}>
     */
    private function storedFactors(mixed $factors): array
    {
        if (! is_array($factors)) {
            return [];
        }

        $rows = [];
        foreach ($factors as $factor) {
            if (! is_array($factor) || ! isset($factor['label'], $factor['note'])) {
                continue;
            }
            $rows[] = [
                'feature' => (string) ($factor['feature'] ?? ''),
                'label' => (string) $factor['label'],
                'note' => (string) $factor['note'],
            ];
        }

        return $rows;
    }

    private function engagementLabel(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        if ($score >= $this->number('healthy_construct_min', 60)) {
            return 'Stable';
        }

        if ($score <= $this->number('low_construct_max', 40)) {
            return 'Needs support';
        }

        return 'Moderate';
    }

    private function isHealthy(?float $score): bool
    {
        return $score !== null && $score >= $this->number('healthy_construct_min', 60);
    }

    private function isLow(?float $score): bool
    {
        return $score !== null && $score <= $this->number('low_construct_max', 40);
    }

    private function isHighProcrastination(?float $score): bool
    {
        return $score !== null && $score >= $this->number('high_procrastination_min', 70);
    }

    private function number(string $key, float $default): float
    {
        $value = config('edupredict.program_shift.'.$key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    private function knownFlag(string $flag): string
    {
        return in_array($flag, ['none', 'program_fit', 'disengagement', 'mixed'], true) ? $flag : 'none';
    }
}
