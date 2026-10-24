<?php

declare(strict_types=1);

namespace Tests\Unit\Prediction;

use App\Contracts\PredictorInterface;
use App\Services\Prediction\FeatureSet;
use App\Services\Prediction\HeuristicPredictor;
use App\Services\Prediction\HttpPredictor;
use App\Services\Prediction\HttpResearchInferenceClient;
use App\Services\Prediction\ResearchInferenceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class HttpResearchInferenceClientTest extends TestCase
{
    public function test_the_default_driver_stays_heuristic_and_onnx_stays_reserved(): void
    {
        config(['edupredict.predictor.driver' => 'heuristic']);
        $this->assertInstanceOf(HeuristicPredictor::class, $this->app->make(PredictorInterface::class));

        config(['edupredict.predictor.driver' => 'http']);
        $this->assertInstanceOf(HttpPredictor::class, $this->app->make(PredictorInterface::class));

        config(['edupredict.predictor.driver' => 'onnx']);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(PredictorInterface::class);
    }

    public function test_the_http_driver_refuses_a_student_feature_set_without_calling_the_service(): void
    {
        Http::fake();
        $predictor = new HttpPredictor;

        try {
            $predictor->predictEmployability($this->features());
            $this->fail('Student employability scoring should have been refused.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('student_scoring_disabled', $exception->errorCode);
            $this->assertStringNotContainsString('placeholder-heuristic-v0', $exception->getMessage());
        }

        try {
            $predictor->predictDropout($this->features());
            $this->fail('Student dropout scoring should have been refused.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('student_scoring_disabled', $exception->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_a_pianda_payload_is_sent_and_parsed_as_research_only(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('http://ml:8000/v1/research/employability', $request->url());
            $this->assertSame('Bearer research-token', $request->header('Authorization')[0]);
            $this->assertSame([
                'schema_id' => 'pianda-v1',
                'CompetenceMean' => 4.0,
                'PsyCapMean' => 3.7857,
            ], $request->data());

            return Http::response([
                'research_only' => true,
                'production_validated' => false,
                'suitable_for_student_advice' => false,
                'student_workflow' => false,
                'schema_id' => 'pianda-v1',
                'source_dataset' => 'Mendeley 10.17632/hmn3b4c6c4.4',
                'model_version' => 'employability_candidate_v1',
                'algorithm' => 'Linear Regression',
                'predicted_employability_mean' => 3.9216475093,
                'display_readiness_index' => 73.0411877323,
                'risk_level' => null,
                'disclaimer' => 'SOURCE-DATA RESEARCH DEMO. This is not a prediction for a UCC student.',
            ]);
        });

        $result = $this->client()->scoreEmployability(4, 3.7857);

        $this->assertSame('employability_candidate_v1', $result->modelVersion);
        $this->assertSame('Linear Regression', $result->algorithm);
        $this->assertEqualsWithDelta(3.9216475093, $result->predictedMean, 0.0000001);
        $this->assertTrue($result->researchOnly);
        $this->assertFalse($result->productionValidated);
        Http::assertSentCount(1);
    }

    public function test_ucc_student_fields_are_rejected_before_http(): void
    {
        Http::fake();
        $client = $this->client();

        try {
            $client->scoreDropout('full', $this->features()->toSnapshot());
            $this->fail('A student snapshot should have been rejected.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('ucc_input_rejected', $exception->errorCode);
        }

        try {
            $client->scoreDropout('scholarship_only', ['Scholarship holder' => 0, 'C1' => 4]);
            $this->fail('A UCC competence item should have been rejected.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('ucc_input_rejected', $exception->errorCode);
        }

        try {
            $client->scoreEmployability(80, 3);
            $this->fail('A 0–100 section score should have been rejected.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('invalid_input', $exception->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_schema_mismatch_is_local_and_upstream_errors_stay_safe(): void
    {
        Http::fake([
            'http://ml:8000/v1/research/dropout' => Http::response([
                'error' => 'schema_mismatch',
                'message' => 'Traceback secret-token',
                'research_only' => true,
            ], 422),
            'http://ml:8000/v1/research/employability' => Http::response(
                'Traceback (most recent call last): password=secret-token',
                500,
            ),
        ]);
        $client = $this->client();

        try {
            $client->scoreDropout('full', ['Scholarship holder' => 0]);
            $this->fail('A one-column full payload should have been rejected.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('schema_mismatch', $exception->errorCode);
        }
        Http::assertNothingSent();

        try {
            $client->scoreDropout('scholarship_only', ['Scholarship holder' => 0, 'GDP' => 1.74]);
            $this->fail('An extra UCI column should have been rejected.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('schema_mismatch', $exception->errorCode);
        }

        try {
            $client->scoreEmployability(4, 3);
            $this->fail('An upstream failure should have been refused.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('service_error', $exception->errorCode);
            $this->assertStringNotContainsString('Traceback', $exception->getMessage());
            $this->assertStringNotContainsString('secret-token', $exception->getMessage());
            $this->assertStringNotContainsString('password', $exception->getMessage());
        }
    }

    public function test_a_timeout_is_reported_without_falling_back(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 5000 milliseconds');
        });

        try {
            $this->client()->scoreEmployability(4, 3);
            $this->fail('A timeout should have been reported.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('timeout', $exception->errorCode);
            $this->assertStringNotContainsString('cURL', $exception->getMessage());
        }
    }

    public function test_an_authentication_failure_is_reported_without_the_token(): void
    {
        Http::fake([
            'http://ml:8000/*' => Http::response(['error' => 'unauthorized', 'message' => 'Bearer research-token'], 401),
        ]);

        try {
            $this->client()->scoreDropout('scholarship_only', ['Scholarship holder' => 1]);
            $this->fail('Authentication failure should have been reported.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('unauthorized', $exception->errorCode);
            $this->assertStringNotContainsString('research-token', $exception->getMessage());
        }
    }

    public function test_a_risk_band_is_rejected_without_falling_back(): void
    {
        Http::fake([
            'http://ml:8000/*' => Http::response([
                'research_only' => true,
                'production_validated' => false,
                'suitable_for_student_advice' => false,
                'student_workflow' => false,
                'schema_id' => 'uci-697',
                'algorithm' => 'Logistic Regression',
                'feature_set' => 'scholarship_only',
                'model_version' => 'm27-binary-dropout-scholarship_only-logistic_regression',
                'predicted_dropout_probability' => 0.57,
                'predicted_class' => 1,
                'predicted_class_name' => 'Dropout',
                'weak_compatibility_baseline' => true,
                'risk_level' => 'high',
                'disclaimer' => 'research',
            ]),
        ]);

        try {
            $this->client()->scoreDropout('scholarship_only', ['Scholarship holder' => 0]);
            $this->fail('A risk band should have been rejected.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('contract_mismatch', $exception->errorCode);
        }
    }

    public function test_an_empty_token_does_not_call_the_service(): void
    {
        Http::fake();
        $client = new HttpResearchInferenceClient('http://ml:8000', '');

        try {
            $client->scoreEmployability(4, 3);
            $this->fail('A missing token should have stopped the call.');
        } catch (ResearchInferenceException $exception) {
            $this->assertSame('not_configured', $exception->errorCode);
        }

        Http::assertNothingSent();
    }

    private function client(): HttpResearchInferenceClient
    {
        return new HttpResearchInferenceClient('http://ml:8000', 'research-token', 5, 2);
    }

    private function features(): FeatureSet
    {
        return new FeatureSet(
            gwa: 1.25,
            failedSubjects: 0,
            semestersCompleted: 2,
            limitedHistory: false,
            scholarshipStatus: 'full',
            hasScholarship: true,
            employmentStatus: null,
            incomeBracket: null,
            householdSize: null,
            livingArrangement: null,
            internetAccess: null,
            deviceAccess: null,
            studySpace: null,
            technicalSkillCount: 0,
            certificationCount: 0,
            internshipCount: 0,
            projectCount: 0,
            workExperienceCount: 0,
            studyHabits: null,
            timeManagement: null,
            motivation: null,
            procrastination: null,
            engagement: null,
        );
    }
}
