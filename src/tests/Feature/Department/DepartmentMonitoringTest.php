<?php

declare(strict_types=1);

namespace Tests\Feature\Department;

use App\Enums\UserRole;
use App\Livewire\Staff\RecommendedActions;
use App\Livewire\Tables\ScopedStudentsTable;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Department;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\RecommendedAction;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\InterventionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DepartmentMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_aggregate_and_the_students_page_reviews_status_in_a_modal(): void
    {
        $this->seed(InterventionSeeder::class);

        $department = Department::factory()->create();
        $program = Program::factory()->create([
            'college_id' => $department->college_id,
            'department_id' => $department->id,
            'code' => 'BSIS',
        ]);
        $sibling = Program::factory()->create([
            'college_id' => $department->college_id,
            'department_id' => $department->id,
            'code' => 'BSCS',
        ]);
        $outside = Program::factory()->create(['code' => 'OUT']);
        $head = User::factory()->departmentHead($department)->create();
        $student = $this->student($program, 'M19-1001', 'Mina Inside', 2);
        $otherYear = $this->student($sibling, 'M19-1002', 'Omar Low', 4);
        $outsider = $this->student($outside, 'M19-9999', 'Olivia Outside', 2);

        $this->profile($student);
        $prediction = $this->prediction($student, 'high', 'program_fit', 41);
        $this->prediction($otherYear, 'low', 'none', 80);
        $this->prediction($outsider, 'high', 'disengagement', 12);

        $this->actingAs($head)
            ->get(route('department.dashboard'))
            ->assertOk()
            ->assertSee($department->name)
            ->assertSee(route('department.students'), false)
            ->assertDontSee('M19-1001')
            ->assertDontSee('Mina Inside')
            ->assertDontSee(route('students.show', $student), false);

        $page = $this->actingAs($head)
            ->get(route('department.students'))
            ->assertOk()
            ->assertSee('M19-1001')
            ->assertSee('Mina Inside')
            ->assertSee('M19-1002')
            ->assertDontSee('M19-9999')
            ->assertDontSee('Olivia Outside')
            ->assertSee('1 student is in the higher dropout-risk range.')
            ->assertSee('High risk: 1')
            ->assertSee('Moderate risk: 0')
            ->assertSee('Low risk: 1')
            ->assertSee('Program concern: 1')
            ->assertSee('1st Year')
            ->assertSee('2nd Year')
            ->assertSee('3rd Year')
            ->assertSee('4th Year')
            ->assertSee('Engagement: Stable')
            ->assertSee('wire:click="openView('.$student->id.')"', false)
            ->assertDontSee(route('students.show', $student), false)
            ->assertDontSee('Faculty')
            ->assertDontSee('SOCIO-RAW-TOKEN-M19')
            ->assertDontSee('bracket-secret-m19')
            ->assertDontSee('Raw question marker M19');

        $this->assertStringNotContainsString('adviser_id', $page->getContent());

        $opened = $this->actingAs($head)
            ->get(route('department.students', ['student' => $student->id]))
            ->assertOk()
            ->assertSee('Mina Inside')
            ->assertSee('41')
            ->assertSee('High risk')
            ->assertSee('Lower confidence')
            ->assertSee('Modal factor marker')
            ->assertSee('Program fit context for this review.')
            ->assertSee('Engagement: Stable')
            ->assertSee('Academic tutoring')
            ->assertSee('scheduled subject tutoring')
            ->assertSee('AI unavailable, using standard text')
            ->assertSee('Mark reviewed')
            ->assertSee('placeholder-heuristic-v0')
            ->assertSee('not a trained model')
            ->assertDontSee('SOCIO-RAW-TOKEN-M19')
            ->assertDontSee('bracket-secret-m19')
            ->assertDontSee('Raw question marker M19')
            ->assertDontSee(route('students.show', $student), false);

        $this->assertStringNotContainsString('household_income', $opened->getContent());

        $this->assertSame(1, AuditLog::query()->where('action', 'student_record_viewed')->count());
        $this->assertSame($student->id, AuditLog::query()->where('action', 'student_record_viewed')->value('subject_id'));

        $this->actingAs($head)
            ->get(route('department.students', ['student' => $outsider->id]))
            ->assertForbidden();

        $action = RecommendedAction::query()
            ->where('prediction_id', $prediction->id)
            ->whereHas('intervention', fn ($query) => $query->where('code', 'academic_tutoring'))
            ->firstOrFail();

        Livewire::actingAs($head)
            ->test(RecommendedActions::class, ['studentId' => $student->id])
            ->set('notes.'.$action->id, 'Reviewed from the status modal.')
            ->call('markReviewed', $action->id)
            ->assertSee('Reviewed from the status modal.');

        $this->assertSame($head->id, $action->fresh()->reviewed_by);

        Livewire::actingAs($head)
            ->test(ScopedStudentsTable::class, ['statusModal' => true])
            ->call('openView', $outsider->id)
            ->assertForbidden();

        Livewire::actingAs($head)
            ->test(ScopedStudentsTable::class, ['statusModal' => true])
            ->set('filters.year_level', '4')
            ->assertSee('M19-1002')
            ->assertDontSee('M19-1001')
            ->set('filters.program_id', (string) $program->id)
            ->set('filters.year_level', '')
            ->assertSee('M19-1001')
            ->assertDontSee('M19-1002')
            ->call('showHighRisk')
            ->assertSee('M19-1001')
            ->assertDontSee('M19-1002')
            ->call('closeView');
    }

    public function test_other_roles_cannot_open_the_department_students_page(): void
    {
        $college = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id]);
        $student = $this->student($program, 'M19-2001', 'Sam Student', 1);

        foreach ([
            User::factory()->role(UserRole::Student)->create(),
            User::factory()->dean($college)->create(),
            User::factory()->administrator()->create(),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('department.students', ['student' => $student->id]))
                ->assertForbidden();
        }

        Livewire::actingAs(User::factory()->dean($college)->create())
            ->test(ScopedStudentsTable::class, ['statusModal' => true])
            ->assertForbidden();
    }

    private function student(Program $program, string $number, string $name, int $year): Student
    {
        $user = User::factory()->create([
            'name' => $name,
            'role' => UserRole::Student,
        ]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
            'year_level' => $year,
        ]);
    }

    private function profile(Student $student): void
    {
        SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'household_income_bracket' => 'bracket-secret-m19',
        ]);

        $item = QuestionnaireItem::query()->create([
            'section' => 'academic_behavior',
            'definition_version' => 'draft-v1',
            'construct' => 'study_habits',
            'text' => 'Raw question marker M19',
            'reverse_scored' => false,
            'is_active' => true,
            'is_draft' => true,
            'sort_order' => 1,
        ]);
        $response = QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'definition_version' => 'draft-v1',
            'submitted_at' => now(),
            'construct_scores' => ['study_habits' => 40],
        ]);
        QuestionnaireAnswer::query()->create([
            'questionnaire_response_id' => $response->id,
            'questionnaire_item_id' => $item->id,
            'value' => 2,
        ]);
    }

    private function prediction(Student $student, string $risk, string $flag, float $score): Prediction
    {
        return Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'model_version' => 'placeholder-heuristic-v0',
            'employability_score' => $score,
            'dropout_risk' => $risk,
            'confidence' => 'low',
            'program_shift_flag' => $flag,
            'factors' => [
                'employability' => [
                    ['feature' => 'gwa', 'label' => 'Modal factor marker', 'direction' => '+', 'magnitude' => 0.4],
                ],
                'dropout' => [
                    ['feature' => 'failed_subjects', 'label' => 'Failed subjects', 'direction' => '-', 'magnitude' => 0.3],
                ],
                'program_shift' => [
                    'label' => $flag === 'program_fit' ? 'Program-fit concern' : 'No shift pattern',
                    'message' => 'Program fit context for this review.',
                    'engagement' => 'Stable',
                    'factors' => [],
                ],
            ],
            'feature_snapshot' => [
                'secret_marker' => 'SOCIO-RAW-TOKEN-M19',
                'construct_scores' => ['engagement' => 80],
            ],
        ]);
    }
}
