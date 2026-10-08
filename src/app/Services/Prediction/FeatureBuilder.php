<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Services\Grades\AcademicSummary;
use App\Services\Profile\SkillsExperienceRecords;

/**
 * The only place student records become a FeatureSet.
 * Draft profiles, archived skill entries, and unconfirmed grade reports are ignored.
 * Projects are no longer collected, so `projectCount` is always 0.
 */
final class FeatureBuilder
{
    public function __construct(
        private AcademicSummary $academic,
        private SkillsExperienceRecords $skillRecords,
    ) {}

    public function build(Student $student): FeatureSet
    {
        $summary = $this->academic->for($student);
        $profile = $this->publishedProfile($student);
        $skills = $this->skillRecords->counts($student);
        $scores = $this->latestConstructScores($student);

        $scholarship = $profile?->scholarship_status;
        $householdSize = $profile?->household_size;

        return new FeatureSet(
            gwa: $summary['rounded_gwa'],
            failedSubjects: $summary['failed_subjects'],
            semestersCompleted: $summary['semesters_completed'],
            limitedHistory: $summary['limited_history'],
            scholarshipStatus: $this->nullableString($scholarship),
            hasScholarship: is_string($scholarship) && $scholarship !== '' && $scholarship !== 'none',
            employmentStatus: $this->nullableString($profile?->employment_status),
            incomeBracket: $this->nullableString($profile?->household_income_bracket),
            householdSize: is_numeric($householdSize) ? (int) $householdSize : null,
            livingArrangement: $this->nullableString($profile?->living_arrangement),
            internetAccess: $this->nullableString($profile?->has_internet),
            deviceAccess: $this->nullableString($profile?->has_device),
            studySpace: $this->nullableString($profile?->has_study_space),
            technicalSkillCount: $skills['technical_skills'],
            certificationCount: $skills['certifications'],
            internshipCount: $skills['internships'],
            projectCount: 0,
            workExperienceCount: $skills['work_experience'],
            studyHabits: $this->score($scores, 'study_habits'),
            timeManagement: $this->score($scores, 'time_management'),
            motivation: $this->score($scores, 'motivation'),
            procrastination: $this->score($scores, 'procrastination'),
            engagement: $this->score($scores, 'engagement'),
            majorGwa: $summary['major_gwa'],
            otherGwa: $summary['other_gwa'],
            majorFailedSubjects: $summary['major_failed_subjects'],
            otherFailedSubjects: $summary['other_failed_subjects'],
            majorUnits: $summary['major_units'],
            otherUnits: $summary['other_units'],
        );
    }

    private function publishedProfile(Student $student): ?SocioeconomicProfile
    {
        $profile = $student->socioeconomicProfile()->first();

        return $profile !== null && ! $profile->is_draft ? $profile : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function latestConstructScores(Student $student): array
    {
        $latest = $student->questionnaireResponses()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $scores = $latest?->construct_scores;

        return is_array($scores) ? $scores : [];
    }

    /**
     * @param  array<string, mixed>  $scores
     */
    private function score(array $scores, string $construct): ?float
    {
        if (! isset($scores[$construct]) || ! is_numeric($scores[$construct])) {
            return null;
        }

        return (float) $scores[$construct];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
