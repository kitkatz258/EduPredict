<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Contracts\PredictorInterface;

/**
 * Placeholder predictor. Transparent weighted rules, deterministic, and labelled
 * placeholder-heuristic-v0. Replace it by binding another PredictorInterface.
 *
 * Direction is from the student's point of view: "+" helps, "-" hurts.
 * Magnitudes are the absolute pre-clamp contributions.
 */
final class HeuristicPredictor implements PredictorInterface
{
    public const MODEL_VERSION = 'placeholder-heuristic-v0';

    public function predictEmployability(FeatureSet $features): PredictionResult
    {
        $points = (float) config('edupredict.predictor.employability_base', 50);
        $factors = [];

        $this->add($factors, $points, 'gwa', 'General weighted average', $this->gwaEmployability($features));
        $this->add($factors, $points, 'failed_subjects', 'Failed subjects', $this->failedEmployability($features));
        $this->add($factors, $points, 'scholarship', 'Scholarship', $this->scholarshipEmployability($features));
        $this->add($factors, $points, 'internships', 'Internships or OJT', $this->cappedCount($features->internshipCount, 2, 6));
        $this->add($factors, $points, 'certifications', 'Certifications', $this->cappedCount($features->certificationCount, 2, 4));
        $this->add($factors, $points, 'technical_skills', 'Technical skills', $this->cappedCount($features->technicalSkillCount, 4, 1.5));
        $this->add($factors, $points, 'projects', 'Projects', $this->cappedCount($features->projectCount, 2, 3));
        $this->add($factors, $points, 'work_experience', 'Work experience', $this->cappedCount($features->workExperienceCount, 2, 3));
        $this->add($factors, $points, 'study_habits', 'Study habits', $this->positiveConstruct($features->studyHabits, 8));
        $this->add($factors, $points, 'time_management', 'Time management', $this->positiveConstruct($features->timeManagement, 8));
        $this->add($factors, $points, 'motivation', 'Motivation', $this->positiveConstruct($features->motivation, 8));
        $this->add($factors, $points, 'procrastination', 'Procrastination', $this->procrastinationEmployability($features->procrastination));
        $this->add($factors, $points, 'engagement', 'Engagement', $this->positiveConstruct($features->engagement, 8));
        $this->add($factors, $points, 'internet_access', 'Internet access', $this->resourceEmployability($features->internetAccess));
        $this->add($factors, $points, 'device_access', 'Device access', $this->resourceEmployability($features->deviceAccess));
        $this->add($factors, $points, 'study_space', 'Study space', $this->resourceEmployability($features->studySpace));
        $this->add($factors, $points, 'employment_status', 'Employment', $this->employmentEmployability($features->employmentStatus));
        $this->add($factors, $points, 'income_bracket', 'Household income band', $this->incomeEmployability($features->incomeBracket));

        return new PredictionResult(
            score: round($this->clamp($points, 0, 100), 2),
            riskLevel: null,
            confidence: $this->confidence($features),
            factors: $this->sorted($factors),
            modelVersion: self::MODEL_VERSION,
        );
    }

    public function predictDropout(FeatureSet $features): PredictionResult
    {
        $probability = (float) config('edupredict.predictor.dropout_base', 0.20);
        $factors = [];

        $this->addRisk($factors, $probability, 'gwa', 'General weighted average', $this->gwaDropout($features));
        $this->addRisk($factors, $probability, 'failed_subjects', 'Failed subjects', $this->failedDropout($features));
        $this->addRisk($factors, $probability, 'scholarship', 'Scholarship', $this->scholarshipDropout($features));
        $this->addRisk($factors, $probability, 'internships', 'Internships or OJT', $this->cappedCount($features->internshipCount, 2, -0.03));
        $this->addRisk($factors, $probability, 'certifications', 'Certifications', $this->cappedCount($features->certificationCount, 2, -0.02));
        $this->addRisk($factors, $probability, 'study_habits', 'Study habits', $this->lowConstructDropout($features->studyHabits, 0.08));
        $this->addRisk($factors, $probability, 'time_management', 'Time management', $this->lowConstructDropout($features->timeManagement, 0.08));
        $this->addRisk($factors, $probability, 'motivation', 'Motivation', $this->lowConstructDropout($features->motivation, 0.08));
        $this->addRisk($factors, $probability, 'procrastination', 'Procrastination', $this->procrastinationDropout($features->procrastination));
        $this->addRisk($factors, $probability, 'engagement', 'Engagement', $this->lowConstructDropout($features->engagement, 0.08));
        $this->addRisk($factors, $probability, 'internet_access', 'Internet access', $this->resourceDropout($features->internetAccess, 0.04));
        $this->addRisk($factors, $probability, 'device_access', 'Device access', $this->resourceDropout($features->deviceAccess, 0.04));
        $this->addRisk($factors, $probability, 'study_space', 'Study space', $this->resourceDropout($features->studySpace, 0.05));
        $this->addRisk($factors, $probability, 'employment_status', 'Employment', $this->employmentDropout($features->employmentStatus));
        $this->addRisk($factors, $probability, 'income_bracket', 'Household income band', $this->incomeDropout($features->incomeBracket));

        $probability = round($this->clamp($probability, 0, 1), 4);

        return new PredictionResult(
            score: round($probability * 100, 2),
            riskLevel: $this->riskLevel($probability),
            confidence: $this->confidence($features),
            factors: $this->sorted($factors),
            modelVersion: self::MODEL_VERSION,
            probability: $probability,
        );
    }

    /**
     * Employability points: a positive delta helps the student.
     *
     * @param  list<ContributingFactor>  $factors
     */
    private function add(array &$factors, float &$total, string $feature, string $label, float $delta): void
    {
        $this->push($factors, $total, $feature, $label, $delta, true);
    }

    /**
     * Dropout probability: a positive delta raises risk, so it hurts the student.
     *
     * @param  list<ContributingFactor>  $factors
     */
    private function addRisk(array &$factors, float &$total, string $feature, string $label, float $delta): void
    {
        $this->push($factors, $total, $feature, $label, $delta, false);
    }

    /**
     * @param  list<ContributingFactor>  $factors
     */
    private function push(array &$factors, float &$total, string $feature, string $label, float $delta, bool $positiveHelps): void
    {
        $delta = round($delta, 4);
        if (abs($delta) < 0.0001) {
            return;
        }

        $total += $delta;
        $helps = $positiveHelps ? $delta > 0 : $delta < 0;
        $factors[] = new ContributingFactor(
            $feature,
            $label,
            $helps ? '+' : '-',
            round(abs($delta), 4),
        );
    }

    private function gwaEmployability(FeatureSet $features): float
    {
        if ($features->gwa === null) {
            return 0.0;
        }

        return $this->clamp((3.0 - $features->gwa) / 2.0, -1, 1) * 20;
    }

    private function failedEmployability(FeatureSet $features): float
    {
        if ($features->failedSubjects > 0) {
            return -min(3, $features->failedSubjects) * 8;
        }

        return $features->gwa === null ? 0.0 : 4.0;
    }

    private function scholarshipEmployability(FeatureSet $features): float
    {
        if ($features->hasScholarship) {
            return 8.0;
        }

        return $features->scholarshipStatus === 'none' ? -2.0 : 0.0;
    }

    private function positiveConstruct(?float $score, float $weight): float
    {
        if ($score === null) {
            return 0.0;
        }

        return (($score - 50) / 50) * $weight;
    }

    private function procrastinationEmployability(?float $score): float
    {
        if ($score === null) {
            return 0.0;
        }

        return ((50 - $score) / 50) * 8;
    }

    private function resourceEmployability(?string $access): float
    {
        return match ($access) {
            'yes' => 2.0,
            'no' => -3.0,
            default => 0.0,
        };
    }

    private function employmentEmployability(?string $status): float
    {
        return match ($status) {
            'part_time', 'working_student' => 2.0,
            'full_time', 'self_employed' => 3.0,
            default => 0.0,
        };
    }

    private function incomeEmployability(?string $bracket): float
    {
        return match ($bracket) {
            'below_10k' => -3.0,
            '10k_20k' => -1.0,
            '40k_70k' => 1.0,
            'above_70k' => 2.0,
            default => 0.0,
        };
    }

    private function gwaDropout(FeatureSet $features): float
    {
        if ($features->gwa === null) {
            return 0.0;
        }

        if ($features->gwa <= 1.75) {
            return -0.05;
        }

        return (($features->gwa - 1.75) / 3.25) * 0.35;
    }

    private function failedDropout(FeatureSet $features): float
    {
        return min(3, $features->failedSubjects) * 0.12;
    }

    private function scholarshipDropout(FeatureSet $features): float
    {
        if ($features->hasScholarship) {
            return -0.05;
        }

        return $features->scholarshipStatus === 'none' ? 0.04 : 0.0;
    }

    private function lowConstructDropout(?float $score, float $weight): float
    {
        if ($score === null) {
            return 0.0;
        }

        return ((100 - $score) / 100) * $weight;
    }

    private function procrastinationDropout(?float $score): float
    {
        if ($score === null) {
            return 0.0;
        }

        return ($score / 100) * 0.12;
    }

    private function resourceDropout(?string $access, float $penalty): float
    {
        return match ($access) {
            'yes' => -0.01,
            'no' => $penalty,
            default => 0.0,
        };
    }

    private function employmentDropout(?string $status): float
    {
        return match ($status) {
            'part_time', 'working_student' => 0.02,
            'full_time' => 0.04,
            'self_employed' => 0.01,
            default => 0.0,
        };
    }

    private function incomeDropout(?string $bracket): float
    {
        return match ($bracket) {
            'below_10k' => 0.03,
            '10k_20k' => 0.01,
            'above_70k' => -0.01,
            default => 0.0,
        };
    }

    private function cappedCount(int $count, int $cap, float $each): float
    {
        return min($cap, max(0, $count)) * $each;
    }

    private function confidence(FeatureSet $features): string
    {
        return $features->limitedHistory ? 'low' : 'normal';
    }

    private function riskLevel(float $probability): string
    {
        $high = (float) config('edupredict.predictor.dropout_high_at', 0.60);
        $moderate = (float) config('edupredict.predictor.dropout_moderate_at', 0.30);

        if ($probability >= $high) {
            return 'high';
        }

        if ($probability >= $moderate) {
            return 'moderate';
        }

        return 'low';
    }

    /**
     * @param  list<ContributingFactor>  $factors
     * @return list<ContributingFactor>
     */
    private function sorted(array $factors): array
    {
        usort($factors, function (ContributingFactor $left, ContributingFactor $right): int {
            $byMagnitude = $right->magnitude <=> $left->magnitude;

            return $byMagnitude !== 0 ? $byMagnitude : $left->feature <=> $right->feature;
        });

        return $factors;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
