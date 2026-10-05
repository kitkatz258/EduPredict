<?php

declare(strict_types=1);

namespace Tests\Unit\Prediction;

use App\Contracts\PredictorInterface;
use App\Services\Prediction\FeatureSet;
use App\Services\Prediction\HeuristicPredictor;
use InvalidArgumentException;
use Tests\TestCase;

class HeuristicPredictorTest extends TestCase
{
    public function test_scores_are_deterministic_and_labelled_as_the_placeholder(): void
    {
        $predictor = new HeuristicPredictor;
        $features = $this->features();

        $firstEmployability = $predictor->predictEmployability($features);
        $secondEmployability = $predictor->predictEmployability($features);
        $firstDropout = $predictor->predictDropout($features);
        $secondDropout = $predictor->predictDropout($features);

        $this->assertSame(45.0, $firstEmployability->score);
        $this->assertSame($firstEmployability->score, $secondEmployability->score);
        $this->assertEquals($firstDropout->probability, $secondDropout->probability);
        $this->assertSame('placeholder-heuristic-v0', $firstEmployability->modelVersion);
        $this->assertSame('placeholder-heuristic-v0', $firstDropout->modelVersion);
        $this->assertNull($firstEmployability->riskLevel);
        $this->assertSame('moderate', $firstDropout->riskLevel);
        $this->assertEqualsWithDelta(0.4408, $firstDropout->probability, 0.0001);
        $this->assertSame('normal', $firstEmployability->confidence);
    }

    public function test_factors_name_the_feature_direction_and_magnitude(): void
    {
        $predictor = new HeuristicPredictor;
        $employability = $predictor->predictEmployability($this->features());
        $dropout = $predictor->predictDropout($this->features());

        $employabilityFactors = collect($employability->factors)->keyBy(fn ($factor) => $factor->feature);
        $this->assertSame('-', $employabilityFactors['failed_subjects']->direction);
        $this->assertSame('Failed subjects', $employabilityFactors['failed_subjects']->label);
        $this->assertEquals(8.0, $employabilityFactors['failed_subjects']->magnitude);
        $this->assertSame('+', $employabilityFactors['gwa']->direction);
        $this->assertEquals(5.0, $employabilityFactors['gwa']->magnitude);

        $dropoutFactors = collect($dropout->factors)->keyBy(fn ($factor) => $factor->feature);
        $this->assertSame('-', $dropoutFactors['failed_subjects']->direction);
        $this->assertEquals(0.12, $dropoutFactors['failed_subjects']->magnitude);
        $this->assertSame('-', $dropoutFactors['gwa']->direction);

        foreach ([...$employability->factors, ...$dropout->factors] as $factor) {
            $this->assertContains($factor->direction, ['+', '-']);
            $this->assertGreaterThan(0, $factor->magnitude);
        }
    }

    public function test_limited_history_lowers_confidence_without_changing_the_score(): void
    {
        $predictor = new HeuristicPredictor;
        $full = $predictor->predictDropout($this->features());
        $limited = $predictor->predictDropout($this->features(['limitedHistory' => true, 'semestersCompleted' => 1]));

        $this->assertSame('low', $limited->confidence);
        $this->assertSame('normal', $full->confidence);
        $this->assertEquals($full->probability, $limited->probability);
        $this->assertSame($full->riskLevel, $limited->riskLevel);
        $this->assertSame('low', $predictor->predictEmployability($this->features(['limitedHistory' => true]))->confidence);
    }

    public function test_stronger_academics_skills_and_habits_score_better_than_weaker_ones(): void
    {
        $predictor = new HeuristicPredictor;
        $strong = $this->features([
            'gwa' => 1.25,
            'failedSubjects' => 0,
            'scholarshipStatus' => 'full',
            'hasScholarship' => true,
            'internshipCount' => 1,
            'certificationCount' => 1,
            'studyHabits' => 90,
            'procrastination' => 10,
        ]);
        $weak = $this->features([
            'gwa' => 3.00,
            'failedSubjects' => 2,
            'scholarshipStatus' => 'none',
            'hasScholarship' => false,
            'studyHabits' => 20,
            'procrastination' => 90,
        ]);

        $this->assertGreaterThan(
            $predictor->predictEmployability($weak)->score,
            $predictor->predictEmployability($strong)->score,
        );
        $this->assertLessThan(
            $predictor->predictDropout($weak)->probability,
            $predictor->predictDropout($strong)->probability,
        );
    }

    public function test_scores_stay_inside_their_ranges(): void
    {
        $predictor = new HeuristicPredictor;
        $extreme = $this->features([
            'gwa' => 5.00,
            'failedSubjects' => 6,
            'scholarshipStatus' => 'none',
            'hasScholarship' => false,
            'studyHabits' => 0,
            'timeManagement' => 0,
            'motivation' => 0,
            'procrastination' => 100,
            'engagement' => 0,
            'internetAccess' => 'no',
            'deviceAccess' => 'no',
            'studySpace' => 'no',
            'incomeBracket' => 'below_10k',
        ]);

        $employability = $predictor->predictEmployability($extreme);
        $dropout = $predictor->predictDropout($extreme);

        $this->assertGreaterThanOrEqual(0, $employability->score);
        $this->assertLessThanOrEqual(100, $employability->score);
        $this->assertGreaterThanOrEqual(0, $dropout->probability);
        $this->assertLessThanOrEqual(1, $dropout->probability);
        $this->assertSame('high', $dropout->riskLevel);
    }

    public function test_the_heuristic_driver_is_bound_and_reserved_drivers_are_rejected(): void
    {
        config(['edupredict.predictor.driver' => 'heuristic']);
        $this->assertInstanceOf(HeuristicPredictor::class, $this->app->make(PredictorInterface::class));

        config(['edupredict.predictor.driver' => 'onnx']);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(PredictorInterface::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function features(array $overrides = []): FeatureSet
    {
        $values = array_merge([
            'gwa' => 2.50,
            'failedSubjects' => 1,
            'semestersCompleted' => 4,
            'limitedHistory' => false,
            'scholarshipStatus' => 'none',
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
        ], $overrides);

        return new FeatureSet(...$values);
    }
}
