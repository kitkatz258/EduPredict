<?php

declare(strict_types=1);

namespace Tests\Feature\Prediction;

use App\Contracts\PredictorInterface;
use App\Models\GradeReport;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\SubjectGrade;
use App\Services\Prediction\FeatureBuilder;
use App\Services\Prediction\HeuristicPredictor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_maps_grades_profile_skills_and_the_latest_questionnaire(): void
    {
        $student = Student::factory()->create(['semesters_completed' => 0]);
        $this->grade($student, '2024-2025', 'First', 'CCS 106', 3, '2.00', false);
        $this->grade($student, '2024-2025', 'Second', 'IS 106', 3, '5.00', true);

        SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'household_income_bracket' => '20k_40k',
            'household_size' => '5',
            'scholarship_status' => 'partial',
            'employment_status' => 'part_time',
            'living_arrangement' => 'with_family',
            'has_internet' => 'yes',
            'has_device' => 'shared',
            'has_study_space' => 'yes',
            'is_draft' => false,
        ]);
        SkillsExperience::factory()->create([
            'student_id' => $student->id,
            'technical_skills' => ['PHP', 'SQL'],
            'certifications' => [['name' => 'IT Specialist']],
            'internships' => [['title' => 'OJT']],
            'projects' => [],
            'work_experience' => [['title' => 'Tutor']],
            'is_draft' => false,
        ]);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now()->subDay(),
            'construct_scores' => ['study_habits' => 10, 'motivation' => 10],
        ]);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'construct_scores' => [
                'study_habits' => 80,
                'time_management' => 70,
                'motivation' => 60,
                'procrastination' => 40,
                'engagement' => 90,
            ],
        ]);

        $features = app(FeatureBuilder::class)->build($student->fresh());

        $this->assertEquals(3.5, $features->gwa);
        $this->assertSame(1, $features->failedSubjects);
        $this->assertSame(2, $features->semestersCompleted);
        $this->assertFalse($features->limitedHistory);
        $this->assertSame('partial', $features->scholarshipStatus);
        $this->assertTrue($features->hasScholarship);
        $this->assertSame('part_time', $features->employmentStatus);
        $this->assertSame('20k_40k', $features->incomeBracket);
        $this->assertSame(5, $features->householdSize);
        $this->assertSame(2, $features->technicalSkillCount);
        $this->assertSame(1, $features->certificationCount);
        $this->assertSame(1, $features->internshipCount);
        $this->assertSame(1, $features->workExperienceCount);
        $this->assertEquals(80, $features->studyHabits);
        $this->assertEquals(40, $features->procrastination);

        $snapshot = $features->toSnapshot();
        foreach (['name', 'email', 'student_number', 'birthdate', 'first_name', 'last_name'] as $pii) {
            $this->assertArrayNotHasKey($pii, $snapshot);
        }
        $this->assertSame(80.0, $snapshot['construct_scores']['study_habits']);
    }

    public function test_drafts_and_unconfirmed_grades_are_left_out(): void
    {
        $student = Student::factory()->create();
        $draftReport = GradeReport::factory()->draft()->create(['student_id' => $student->id]);
        SubjectGrade::factory()->create([
            'grade_report_id' => $draftReport->id,
            'subject_code' => 'CCS 110',
            'units' => 3,
            'final_grade' => '1.00',
            'remarks' => 'PASSED',
            'is_failed' => false,
        ]);
        SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'scholarship_status' => 'full',
            'is_draft' => true,
        ]);
        SkillsExperience::factory()->create([
            'student_id' => $student->id,
            'internships' => [['title' => 'Hidden']],
            'is_draft' => true,
        ]);

        $features = app(FeatureBuilder::class)->build($student);

        $this->assertNull($features->gwa);
        $this->assertSame(0, $features->failedSubjects);
        $this->assertTrue($features->limitedHistory);
        $this->assertNull($features->scholarshipStatus);
        $this->assertFalse($features->hasScholarship);
        $this->assertSame(0, $features->internshipCount);
    }

    public function test_seeded_student_yields_scores_a_risk_level_and_low_confidence_when_history_is_short(): void
    {
        $this->seed(DatabaseSeeder::class);

        $student = Student::query()->where('student_number', 'SYN-0004')->firstOrFail();
        $features = app(FeatureBuilder::class)->build($student);
        $predictor = app(PredictorInterface::class);
        $this->assertInstanceOf(HeuristicPredictor::class, $predictor);

        $employability = $predictor->predictEmployability($features);
        $dropout = $predictor->predictDropout($features);

        $this->assertTrue($features->limitedHistory);
        $this->assertGreaterThanOrEqual(0, $employability->score);
        $this->assertLessThanOrEqual(100, $employability->score);
        $this->assertContains($dropout->riskLevel, ['low', 'moderate', 'high']);
        $this->assertNotEmpty($employability->factors);
        $this->assertNotEmpty($dropout->factors);
        $this->assertSame('low', $dropout->confidence);
        $this->assertSame('placeholder-heuristic-v0', $dropout->modelVersion);
        $this->assertSame($employability->score, $predictor->predictEmployability($features)->score);
    }

    private function grade(Student $student, string $year, string $semester, string $code, float $units, string $final, bool $failed): void
    {
        $report = GradeReport::factory()->create([
            'student_id' => $student->id,
            'school_year' => $year,
            'semester' => $semester,
            'status' => 'confirmed',
        ]);

        SubjectGrade::factory()->create([
            'grade_report_id' => $report->id,
            'subject_code' => $code,
            'subject_name' => $code,
            'units' => $units,
            'final_grade' => $final,
            'remarks' => $failed ? 'FAILED' : 'PASSED',
            'is_failed' => $failed,
        ]);
    }
}
