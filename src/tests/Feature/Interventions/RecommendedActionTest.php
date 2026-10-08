<?php

declare(strict_types=1);

namespace Tests\Feature\Interventions;

use App\Livewire\Staff\RecommendedActions;
use App\Models\College;
use App\Models\Intervention;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\RecommendedAction;
use App\Models\Student;
use App\Models\User;
use App\Services\Interventions\RecommendedActionBuilder;
use Database\Seeders\InterventionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RecommendedActionTest extends TestCase
{
    use RefreshDatabase;

    private int $studentSequence = 1000;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(InterventionSeeder::class);
    }

    public function test_low_risk_gets_none_and_moderate_or_high_get_ranked_actions_from_the_list(): void
    {
        $this->assertGreaterThanOrEqual(15, Intervention::query()->count());
        $this->assertLessThanOrEqual(20, Intervention::query()->count());

        $builder = app(RecommendedActionBuilder::class);
        $low = $this->prediction('low', 'program_fit', $this->hurtingFactors());
        $moderate = $this->prediction('moderate', 'program_fit', $this->hurtingFactors());
        $high = $this->prediction('high', 'disengagement', [
            'dropout' => [
                ['feature' => 'engagement', 'label' => 'Engagement', 'direction' => '-', 'magnitude' => 0.2],
            ],
        ]);
        $empty = $this->prediction('moderate', 'none', ['dropout' => []]);
        $many = $this->prediction('moderate', 'program_fit', $this->manyFactors());
        $legacy = $this->prediction('high', 'disengagement', [
            ['feature' => 'gwa', 'label' => 'General weighted average', 'direction' => '-', 'magnitude' => 0.35],
            ['feature' => 'failed_subjects', 'label' => 'Failed subjects', 'direction' => '-', 'magnitude' => 0.25],
        ]);

        $this->assertCount(0, $builder->ensure($low));
        $this->assertSame(0, RecommendedAction::query()->where('prediction_id', $low->id)->count());

        $this->assertSame(
            ['academic_tutoring', 'program_fit_conversation', 'study_skills_workshop'],
            $this->codes($builder->ensure($moderate)),
        );
        $this->assertSame(
            ['academic_tutoring', 'program_fit_conversation', 'study_skills_workshop'],
            $this->codes($builder->ensure($moderate)),
        );

        $highCodes = $this->codes($builder->ensure($high));
        $this->assertContains('attendance_monitoring', $highCodes);
        $this->assertLessThanOrEqual(5, count($highCodes));
        $this->assertNotContains('attendance_monitoring', $this->codes($builder->ensure($moderate->fresh())));

        $this->assertSame(
            ['adviser_check_in', 'guidance_counselling'],
            $this->codes($builder->ensure($empty)),
        );

        $manyCodes = $this->codes($builder->ensure($many));
        $this->assertCount(5, $manyCodes);
        $this->assertSame('academic_tutoring', $manyCodes[0]);

        $legacyCodes = $this->codes($builder->ensure($legacy));
        $this->assertContains('academic_tutoring', $legacyCodes);
        $this->assertContains('attendance_monitoring', $legacyCodes);
        $this->assertLessThanOrEqual(5, count($legacyCodes));
    }

    public function test_ai_output_outside_the_intervention_list_is_rejected(): void
    {
        $student = $this->student('Unique Secretname', 'SYN-4242');
        $prediction = $this->prediction('moderate', 'program_fit', $this->hurtingFactors(), $student);

        config([
            'edupredict.ai.api_key' => 'test-key',
            'edupredict.ai.model' => 'test/free',
        ]);

        Http::fake(function ($request) use ($student) {
            $body = $request->body();
            $this->assertStringNotContainsString($student->user->name, $body);
            $this->assertStringNotContainsString('Secretname', $body);
            $this->assertStringNotContainsString($student->student_number, $body);
            $this->assertStringContainsString('academic_tutoring', $body);

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'actions' => [
                                ['code' => 'academic_tutoring', 'text' => 'Offer tutoring for the subjects that are pulling grades down.'],
                                ['code' => 'expulsion', 'text' => 'Expel the student immediately from the institution.'],
                                ['code' => 'dismiss_student', 'text' => 'Assign automatic dismissal.'],
                            ],
                        ]),
                    ],
                ]],
            ]);
        });

        $actions = app(RecommendedActionBuilder::class)->ensure($prediction);
        $this->assertCount(3, $actions);
        $this->assertSame(
            ['academic_tutoring', 'program_fit_conversation', 'study_skills_workshop'],
            $this->codes($actions),
        );

        $tutoring = $actions->first(fn (RecommendedAction $action): bool => $action->intervention?->code === 'academic_tutoring');
        $workshop = $actions->first(fn (RecommendedAction $action): bool => $action->intervention?->code === 'study_skills_workshop');
        $this->assertSame('ai', $tutoring->phrasing_source);
        $this->assertSame('Offer tutoring for the subjects that are pulling grades down.', $tutoring->phrased_text);
        $this->assertSame('rule_based', $workshop->phrasing_source);
        $this->assertStringContainsString('study-skills workshop', $workshop->phrased_text);

        $stored = RecommendedAction::query()->pluck('phrased_text')->implode(' ');
        $this->assertStringNotContainsString('Expel the student immediately', $stored);
        $this->assertStringNotContainsString('automatic dismissal', $stored);
        $this->assertFalse(Intervention::query()->whereIn('code', ['expulsion', 'dismiss_student'])->exists());

        Http::assertSentCount(1);
        app(RecommendedActionBuilder::class)->ensure($prediction);
        Http::assertSentCount(1);

        $this->actingAs($student->user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee(RecommendedActionBuilder::STUDENT_MESSAGE)
            ->assertDontSee('Academic tutoring')
            ->assertDontSee('Expel the student immediately')
            ->assertDontSee('Offer tutoring for the subjects');

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(RecommendedActionBuilder::STUDENT_MESSAGE)
            ->assertDontSee('scheduled subject tutoring');
    }

    public function test_invalid_ai_json_uses_the_stored_description(): void
    {
        config([
            'edupredict.ai.api_key' => 'test-key',
            'edupredict.ai.model' => 'test/free',
        ]);
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'not-json'],
                ]],
            ]),
        ]);

        $actions = app(RecommendedActionBuilder::class)->ensure(
            $this->prediction('moderate', 'none', ['dropout' => []]),
        );

        $this->assertSame(['rule_based', 'rule_based'], $actions->pluck('phrasing_source')->all());
        $this->assertStringContainsString('schedule a check-in', $actions->first()->phrased_text);
    }

    public function test_department_heads_review_actions_and_deans_and_students_do_not_see_them(): void
    {
        $college = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id]);
        $otherProgram = Program::factory()->create();
        $head = User::factory()->departmentHead($program)->create();
        $departmentHead = User::factory()->departmentHead($program->department)->create();
        $otherHead = User::factory()->departmentHead($otherProgram)->create();
        $dean = User::factory()->dean($college)->create();
        $student = $this->student('Sam Visible', 'SYN-5151', $program);
        $prediction = $this->prediction('moderate', 'program_fit', $this->hurtingFactors(), $student);

        app(RecommendedActionBuilder::class)->ensure($prediction, false);

        $this->actingAs($student->user)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee(RecommendedActionBuilder::STUDENT_MESSAGE)
            ->assertDontSee('Academic tutoring')
            ->assertDontSee('scheduled subject tutoring')
            ->assertDontSee('Mark reviewed');

        $this->actingAs($head)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Academic tutoring')
            ->assertSee('scheduled subject tutoring')
            ->assertSee('Mark reviewed')
            ->assertSee('not a trained model and it has no percentage')
            ->assertSee('AI unavailable, using standard text')
            ->assertDontSee(RecommendedActionBuilder::STUDENT_MESSAGE);

        $action = RecommendedAction::query()
            ->where('prediction_id', $prediction->id)
            ->whereHas('intervention', fn ($query) => $query->where('code', 'academic_tutoring'))
            ->firstOrFail();

        Livewire::actingAs($head)
            ->test(RecommendedActions::class, ['studentId' => $student->id])
            ->set('notes.'.$action->id, 'Talked on Monday.')
            ->call('markReviewed', $action->id)
            ->assertSee('Reviewed')
            ->assertSee('Talked on Monday.');

        $action->refresh();
        $this->assertSame($head->id, $action->reviewed_by);
        $this->assertSame('Talked on Monday.', $action->reviewer_note);
        $this->assertNotNull($action->reviewed_at);

        $workshop = RecommendedAction::query()
            ->where('prediction_id', $prediction->id)
            ->whereHas('intervention', fn ($query) => $query->where('code', 'study_skills_workshop'))
            ->firstOrFail();

        Livewire::actingAs($departmentHead)
            ->test(RecommendedActions::class, ['studentId' => $student->id])
            ->set('notes.'.$workshop->id, 'Workshop invite sent.')
            ->call('markReviewed', $workshop->id)
            ->assertSee('Workshop invite sent.');

        Livewire::actingAs($dean)
            ->test(RecommendedActions::class, ['studentId' => $student->id])
            ->assertForbidden();
        $this->assertFalse($dean->can('review', $action));
        $this->assertFalse($dean->can('view', $action));
        $this->actingAs($dean)->get(route('students.show', $student))->assertForbidden();

        Livewire::actingAs($student->user)
            ->test(RecommendedActions::class, ['studentId' => $student->id])
            ->assertForbidden();

        Livewire::actingAs($otherHead)
            ->test(RecommendedActions::class, ['studentId' => $student->id])
            ->assertForbidden();

        $this->actingAs($otherHead)->get(route('students.show', $student))->assertForbidden();
    }

    public function test_a_low_risk_student_does_not_see_the_support_note(): void
    {
        $student = $this->student('Low Risk', 'SYN-6060');
        $this->prediction('low', 'none', $this->hurtingFactors(), $student);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee(RecommendedActionBuilder::STUDENT_MESSAGE)
            ->assertDontSee('Academic tutoring');
    }

    /**
     * @param  array<string, mixed>  $factors
     */
    private function prediction(string $risk, string $flag, array $factors, ?Student $student = null): Prediction
    {
        $student ??= $this->student();

        return Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'dropout_risk' => $risk,
            'program_shift_flag' => $flag,
            'factors' => $factors,
        ]);
    }

    private function student(string $name = 'Ada Student', ?string $number = null, ?Program $program = null): Student
    {
        $this->studentSequence++;
        $program ??= Program::factory()->create();
        $user = User::factory()->create([
            'name' => $name,
            'role' => \App\Enums\UserRole::Student,
        ]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number ?? 'SYN-'.$this->studentSequence,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function hurtingFactors(): array
    {
        return [
            'dropout' => [
                ['feature' => 'gwa', 'label' => 'General weighted average', 'direction' => '-', 'magnitude' => 0.40],
                ['feature' => 'failed_subjects', 'label' => 'Failed subjects', 'direction' => '-', 'magnitude' => 0.30],
                ['feature' => 'study_habits', 'label' => 'Study habits', 'direction' => '-', 'magnitude' => 0.10],
                ['feature' => 'engagement', 'label' => 'Engagement', 'direction' => '+', 'magnitude' => 0.20],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manyFactors(): array
    {
        $features = [
            'gwa' => 0.90,
            'failed_subjects' => 0.90,
            'engagement' => 0.20,
            'motivation' => 0.20,
            'study_habits' => 0.20,
            'time_management' => 0.20,
            'procrastination' => 0.20,
            'income_bracket' => 0.20,
            'scholarship' => 0.20,
            'internships' => 0.20,
            'device_access' => 0.20,
            'internet_access' => 0.20,
            'study_space' => 0.20,
            'employment_status' => 0.20,
            'certifications' => 0.20,
            'technical_skills' => 0.20,
        ];
        $rows = [];
        foreach ($features as $feature => $magnitude) {
            $rows[] = [
                'feature' => $feature,
                'label' => $feature,
                'direction' => '-',
                'magnitude' => $magnitude,
            ];
        }

        return ['dropout' => $rows];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RecommendedAction>  $actions
     * @return list<string>
     */
    private function codes($actions): array
    {
        return $actions->map(fn (RecommendedAction $action): string => (string) $action->intervention?->code)->all();
    }
}
