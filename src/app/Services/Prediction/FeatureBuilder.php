<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Models\Student;
use App\Services\Grades\AcademicSummary;
use App\Services\Profile\SkillsExperienceRecords;

/**
 * The only place student records become a FeatureSet.
 * Draft profiles, archived skill entries, and unconfirmed grade reports are ignored.
 * Household profile fields are not read. Projects are no longer collected, so `projectCount` is always 0.
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
        $skills = $this->skillRecords->counts($student);
        $scores = $this->latestConstructScores($student);

        return new FeatureSet(
            gwa: $summary['rounded_gwa'],
            failedSubjects: $summary['failed_subjects'],
            semestersCompleted: $summary['semesters_completed'],
            limitedHistory: $summary['limited_history'],
            scholarshipStatus: null,
            hasScholarship: false,
            employmentStatus: null,
            incomeBracket: null,
            householdSize: null,
            livingArrangement: null,
            internetAccess: null,
            deviceAccess: null,
            studySpace: null,
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
            incompleteSubjects: $summary['incomplete_subjects'],
            droppedSubjects: $summary['dropped_subjects'],
        );
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

        // Only the legacy numeric 0–100 construct scores are model features.
        // Panel-approved section means are nested arrays and stay out of this
        // set, including Competence and Psychological Capital.
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
}
