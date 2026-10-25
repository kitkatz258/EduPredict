<?php

declare(strict_types=1);

namespace Tests\Feature\Prediction;

use App\Enums\UserRole;
use App\Livewire\Student\RequestPrediction;
use App\Models\GradeReport;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\StudentSkill;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\Prediction\HttpResearchInferenceClient;
use App\Services\Prediction\PredictionRequester;
use App\Services\Prediction\ResearchDropoutResult;
use App\Services\Prediction\ResearchEmployabilityResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ResearchInferenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_requests_stay_on_the_heuristic_and_keep_history(): void
    {
        $student = $this->makeStudent(2);
        $this->completeProfile($student);
        $existing = Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'model_version' => 'placeholder-heuristic-v0',
            'employability_score' => 61,
            'dropout_probability' => 0.22,
            'dropout_risk' => 'low',
        ]);

        config(['edupredict.predictor.driver' => 'http']);
        Http::fake();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->assertSee(PredictionRequester::RESEARCH_ENGINE_BLOCK)
            ->call('request')
            ->assertHasErrors('request')
            ->assertSee(PredictionRequester::RESEARCH_ENGINE_BLOCK);

        Http::assertNothingSent();
        $this->assertSame(1, Prediction::query()->count());
        $existing->refresh();
        $this->assertSame('placeholder-heuristic-v0', $existing->model_version);
        $this->assertEquals(61, (float) $existing->employability_score);
        $this->assertEquals(0.22, (float) $existing->dropout_probability);
        $this->assertSame('low', $existing->dropout_risk);
    }

    public function test_the_heuristic_workflow_still_saves_a_placeholder_prediction(): void
    {
        $student = $this->makeStudent(2);
        $this->completeProfile($student);
        Http::fake();

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $prediction = Prediction::query()->sole();
        $this->assertSame('placeholder-heuristic-v1', $prediction->model_version);
        $this->assertNotNull($prediction->dropout_risk);
        Http::assertNothingSent();
    }

    public function test_the_laravel_client_matches_a_live_research_service_when_one_is_running(): void
    {
        if (getenv('ML_API_LIVE') !== '1') {
            $this->markTestSkipped('Set ML_API_LIVE=1 to compare Laravel with the running research service.');
        }

        $baseUrl = (string) (getenv('ML_API_BASE_URL') ?: 'http://ml:8000');
        $token = (string) getenv('ML_API_TOKEN');
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/research/research-inference.json')), true, 512, JSON_THROW_ON_ERROR);
        $client = new HttpResearchInferenceClient($baseUrl, $token, 5, 2);

        $employability = $client->scoreEmployability(
            (float) $fixture['employability']['CompetenceMean'],
            (float) $fixture['employability']['PsyCapMean'],
        );
        $directEmployability = Http::withToken($token)->acceptJson()->post($baseUrl.'/v1/research/employability', $fixture['employability']);
        $directEmployability->throw();
        $this->assertInstanceOf(ResearchEmployabilityResult::class, $employability);
        $this->assertEqualsWithDelta(
            (float) $directEmployability->json('predicted_employability_mean'),
            $employability->predictedMean,
            0.0000001,
        );
        $this->assertSame('employability_candidate_v1', $employability->modelVersion);
        $this->assertFalse($employability->productionValidated);

        $dropout = $client->scoreDropout('full', $fixture['dropout_full']['features']);
        $directDropout = Http::withToken($token)->acceptJson()->post($baseUrl.'/v1/research/dropout', $fixture['dropout_full']);
        $directDropout->throw();
        $this->assertInstanceOf(ResearchDropoutResult::class, $dropout);
        $this->assertEqualsWithDelta(
            (float) $directDropout->json('predicted_dropout_probability'),
            $dropout->probability,
            0.0000001,
        );
        $this->assertSame($directDropout->json('predicted_class_name'), $dropout->predictedClassName);
        $this->assertFalse($dropout->suitableForStudentAdvice);
        $this->assertContains($dropout->predictedClassName, ['Dropout', 'Not Dropout']);
    }

    private function makeStudent(int $semesters): Student
    {
        $program = Program::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Student]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => 'REQ-'.$user->id,
            'year_level' => 3,
            'semesters_completed' => $semesters,
        ]);
    }

    private function completeProfile(Student $student): void
    {
        foreach (['First', 'Second'] as $index => $semester) {
            $report = GradeReport::factory()->create([
                'student_id' => $student->id,
                'school_year' => '2024-2025',
                'semester' => $semester,
                'status' => 'confirmed',
            ]);
            SubjectGrade::factory()->create([
                'grade_report_id' => $report->id,
                'subject_code' => 'CCS 10'.($index + 1),
                'subject_name' => 'Sample subject',
                'units' => 3,
                'final_grade' => '2.00',
                'remarks' => 'PASSED',
                'is_failed' => false,
                'is_major_subject' => false,
            ]);
        }

        SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'household_income_bracket' => '20k_40k',
            'household_size' => '4',
            'scholarship_status' => 'none',
            'employment_status' => 'unemployed',
            'living_arrangement' => 'with_family',
            'has_internet' => 'yes',
            'has_device' => 'yes',
            'has_study_space' => 'yes',
            'is_draft' => false,
        ]);
        SkillsExperience::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'SQL']);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'construct_scores' => [
                'study_habits' => 60,
                'time_management' => 60,
                'motivation' => 60,
                'procrastination' => 40,
                'engagement' => 60,
            ],
        ]);
    }
}
