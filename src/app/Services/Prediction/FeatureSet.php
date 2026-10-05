<?php

declare(strict_types=1);

namespace App\Services\Prediction;

/**
 * De-identified features for one student. Built only by FeatureBuilder.
 */
final readonly class FeatureSet
{
    public function __construct(
        public ?float $gwa,
        public int $failedSubjects,
        public int $semestersCompleted,
        public bool $limitedHistory,
        public ?string $scholarshipStatus,
        public bool $hasScholarship,
        public ?string $employmentStatus,
        public ?string $incomeBracket,
        public ?int $householdSize,
        public ?string $livingArrangement,
        public ?string $internetAccess,
        public ?string $deviceAccess,
        public ?string $studySpace,
        public int $technicalSkillCount,
        public int $certificationCount,
        public int $internshipCount,
        public int $projectCount,
        public int $workExperienceCount,
        public ?float $studyHabits,
        public ?float $timeManagement,
        public ?float $motivation,
        public ?float $procrastination,
        public ?float $engagement,
    ) {}

    /**
     * @return array<string, float|null>
     */
    public function constructScores(): array
    {
        return [
            'study_habits' => $this->studyHabits,
            'time_management' => $this->timeManagement,
            'motivation' => $this->motivation,
            'procrastination' => $this->procrastination,
            'engagement' => $this->engagement,
        ];
    }

    /**
     * Coded features only. No name, student number, email, or birthdate.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'gwa' => $this->gwa,
            'failed_subjects' => $this->failedSubjects,
            'semesters_completed' => $this->semestersCompleted,
            'limited_history' => $this->limitedHistory,
            'scholarship_status' => $this->scholarshipStatus,
            'has_scholarship' => $this->hasScholarship,
            'employment_status' => $this->employmentStatus,
            'income_bracket' => $this->incomeBracket,
            'household_size' => $this->householdSize,
            'living_arrangement' => $this->livingArrangement,
            'internet_access' => $this->internetAccess,
            'device_access' => $this->deviceAccess,
            'study_space' => $this->studySpace,
            'technical_skill_count' => $this->technicalSkillCount,
            'certification_count' => $this->certificationCount,
            'internship_count' => $this->internshipCount,
            'project_count' => $this->projectCount,
            'work_experience_count' => $this->workExperienceCount,
            'construct_scores' => $this->constructScores(),
        ];
    }
}
