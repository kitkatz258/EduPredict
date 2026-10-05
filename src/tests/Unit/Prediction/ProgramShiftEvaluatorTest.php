<?php

declare(strict_types=1);

namespace Tests\Unit\Prediction;

use App\Services\Prediction\ContributingFactor;
use App\Services\Prediction\FeatureSet;
use App\Services\Prediction\ProgramShiftEvaluator;
use Tests\TestCase;

class ProgramShiftEvaluatorTest extends TestCase
{
    public function test_contrasting_cases_are_deterministic_and_have_no_percentage(): void
    {
        $evaluator = app(ProgramShiftEvaluator::class);
        $cases = [
            'program_fit' => $this->features([
                'gwa' => 2.10,
                'failedSubjects' => 0,
                'majorGwa' => 3.00,
                'otherGwa' => 1.50,
                'majorFailedSubjects' => 0,
                'otherFailedSubjects' => 0,
                'majorUnits' => 9,
                'otherUnits' => 6,
                'studyHabits' => 80,
                'timeManagement' => 75,
                'motivation' => 80,
                'procrastination' => 20,
                'engagement' => 85,
            ]),
            'disengagement' => $this->features([
                'gwa' => 1.50,
                'failedSubjects' => 0,
                'majorGwa' => 1.50,
                'otherGwa' => 1.50,
                'majorUnits' => 9,
                'otherUnits' => 6,
                'studyHabits' => 70,
                'timeManagement' => 30,
                'motivation' => 25,
                'procrastination' => 80,
                'engagement' => 20,
            ]),
            'broad_disengagement' => $this->features([
                'gwa' => 3.25,
                'failedSubjects' => 2,
                'majorGwa' => 3.25,
                'otherGwa' => 3.25,
                'majorFailedSubjects' => 1,
                'otherFailedSubjects' => 1,
                'majorUnits' => 9,
                'otherUnits' => 6,
                'studyHabits' => 80,
                'timeManagement' => 80,
                'motivation' => 80,
                'procrastination' => 20,
                'engagement' => 80,
            ]),
            'mixed' => $this->features([
                'gwa' => 2.40,
                'failedSubjects' => 1,
                'majorGwa' => 5.00,
                'otherGwa' => 1.25,
                'majorFailedSubjects' => 1,
                'otherFailedSubjects' => 0,
                'majorUnits' => 9,
                'otherUnits' => 9,
                'studyHabits' => 70,
                'timeManagement' => 40,
                'motivation' => 30,
                'procrastination' => 75,
                'engagement' => 25,
            ]),
            'none' => $this->features([
                'gwa' => 1.75,
                'failedSubjects' => 0,
                'majorGwa' => 1.75,
                'otherGwa' => 1.75,
                'majorUnits' => 9,
                'otherUnits' => 6,
                'studyHabits' => 70,
                'timeManagement' => 70,
                'motivation' => 70,
                'procrastination' => 30,
                'engagement' => 75,
            ]),
        ];

        $expected = [
            'program_fit' => 'program_fit',
            'disengagement' => 'disengagement',
            'broad_disengagement' => 'disengagement',
            'mixed' => 'mixed',
            'none' => 'none',
        ];

        foreach ($cases as $name => $features) {
            $first = $evaluator->evaluate($features, $this->factors());
            $second = $evaluator->evaluate($features, $this->factors());

            $this->assertSame($expected[$name], $first->flag, $name);
            $this->assertSame($first->flag, $second->flag, $name);
            $this->assertSame($first->message, $second->message, $name);
            $this->assertStringNotContainsString('%', $first->message, $name);
            $this->assertStringNotContainsString('%', $first->label, $name);
        }

        $fit = $evaluator->evaluate($cases['program_fit'], $this->factors());
        $this->assertSame('Program-fit concern', $fit->label);
        $this->assertStringContainsString('may be worth a conversation with an adviser about program fit', $fit->message);
        $this->assertNotEmpty($fit->factors);

        $broad = $evaluator->evaluate($cases['broad_disengagement'], $this->factors());
        $this->assertSame('Broader disengagement', $broad->label);
        $notes = array_column($broad->factors, 'note');
        $this->assertContains('Performance is spread across subject types', $notes);
    }

    public function test_a_steady_record_without_major_subjects_is_none(): void
    {
        $assessment = app(ProgramShiftEvaluator::class)->evaluate($this->features([
            'gwa' => 2.00,
            'failedSubjects' => 0,
            'studyHabits' => 60,
            'timeManagement' => 60,
            'motivation' => 60,
            'procrastination' => 40,
            'engagement' => 60,
        ]), []);

        $this->assertSame('none', $assessment->flag);
        $this->assertSame('Stable', $assessment->engagement);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function features(array $overrides = []): FeatureSet
    {
        $values = array_merge([
            'gwa' => 2.00,
            'failedSubjects' => 0,
            'semestersCompleted' => 4,
            'limitedHistory' => false,
            'scholarshipStatus' => null,
            'hasScholarship' => false,
            'employmentStatus' => null,
            'incomeBracket' => null,
            'householdSize' => null,
            'livingArrangement' => null,
            'internetAccess' => null,
            'deviceAccess' => null,
            'studySpace' => null,
            'technicalSkillCount' => 0,
            'certificationCount' => 0,
            'internshipCount' => 0,
            'projectCount' => 0,
            'workExperienceCount' => 0,
            'studyHabits' => null,
            'timeManagement' => null,
            'motivation' => null,
            'procrastination' => null,
            'engagement' => null,
            'majorGwa' => null,
            'otherGwa' => null,
            'majorFailedSubjects' => 0,
            'otherFailedSubjects' => 0,
            'majorUnits' => 0.0,
            'otherUnits' => 0.0,
        ], $overrides);

        return new FeatureSet(...$values);
    }

    /**
     * @return list<ContributingFactor>
     */
    private function factors(): array
    {
        return [
            new ContributingFactor('gwa', 'General weighted average', '-', 0.2),
            new ContributingFactor('study_habits', 'Study habits', '-', 0.02),
            new ContributingFactor('motivation', 'Motivation', '-', 0.02),
            new ContributingFactor('engagement', 'Engagement', '-', 0.02),
            new ContributingFactor('time_management', 'Time management', '-', 0.04),
            new ContributingFactor('procrastination', 'Procrastination', '-', 0.04),
        ];
    }
}
