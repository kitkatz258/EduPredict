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
use App\Models\StudentSkill;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\SubjectGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_fit_is_stored_and_shown_beside_dropout_risk_for_student_and_department_heads(): void
    {
        $program = Program::factory()->create();
        $head = User::factory()->departmentHead($program)->create();
        $departmentHead = User::factory()->departmentHead($program->department)->create();
        $outsider = User::factory()->departmentHead(Program::factory()->create())->create();
        $student = Student::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'program_id' => $program->id,
            'student_number' => 'SHIFT-10001',
            'year_level' => 3,
            'semesters_completed' => 2,
        ]);

        $this->grades($student);
        $this->profile($student);

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $prediction = Prediction::query()->sole();
        $this->assertSame('program_fit', $prediction->program_shift_flag);
        $this->assertSame('program_fit', $prediction->factors['program_shift']['flag']);
        $this->assertEquals(3.0, $prediction->feature_snapshot['major_gwa']);
        $this->assertEquals(1.5, $prediction->feature_snapshot['other_gwa']);

        $phrase = 'may be worth a conversation with an adviser about program fit';

        $this->actingAs($student->user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Program-fit concern')
            ->assertSee($phrase)
            ->assertSee('not a trained model')
            ->assertSee('Engagement: Stable');

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Program-shift indicator')
            ->assertSee($phrase);

        $this->actingAs($departmentHead)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Program-fit concern')
            ->assertSee($phrase);

        $this->actingAs($head)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Program-fit concern')
            ->assertSee($phrase);

        $this->actingAs($outsider)
            ->get(route('students.show', $student))
            ->assertForbidden();
    }

    public function test_low_engagement_with_even_grades_is_disengagement(): void
    {
        $program = Program::factory()->create();
        $student = Student::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Student])->id,
            'program_id' => $program->id,
            'student_number' => 'SHIFT-20002',
            'year_level' => 2,
            'semesters_completed' => 2,
        ]);

        $report = GradeReport::factory()->create([
            'student_id' => $student->id,
            'school_year' => '2024-2025',
            'semester' => 'First',
            'status' => 'confirmed',
        ]);
        GradeReport::factory()->create([
            'student_id' => $student->id,
            'school_year' => '2024-2025',
            'semester' => 'Second',
            'status' => 'confirmed',
        ]);
        SubjectGrade::factory()->create([
            'grade_report_id' => $report->id,
            'subject_code' => 'GEC 001',
            'units' => 3,
            'final_grade' => '1.50',
            'remarks' => 'PASSED',
            'is_failed' => false,
            'is_major_subject' => false,
        ]);
        $this->profile($student, [
            'study_habits' => 70,
            'time_management' => 30,
            'motivation' => 25,
            'procrastination' => 80,
            'engagement' => 20,
        ]);

        Livewire::actingAs($student->user)
            ->test(RequestPrediction::class)
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame('disengagement', Prediction::query()->sole()->program_shift_flag);

        $this->actingAs($student->user)
            ->get(route('student.results'))
            ->assertOk()
            ->assertSee('Broader disengagement')
            ->assertSee('Engagement: Needs support')
            ->assertDontSee('Program-fit concern');
    }

    private function grades(Student $student): void
    {
        $report = GradeReport::factory()->create([
            'student_id' => $student->id,
            'school_year' => '2024-2025',
            'semester' => 'First',
            'status' => 'confirmed',
        ]);
        GradeReport::factory()->create([
            'student_id' => $student->id,
            'school_year' => '2024-2025',
            'semester' => 'Second',
            'status' => 'confirmed',
        ]);

        foreach (['CCS 101', 'CCS 102', 'IS 101'] as $code) {
            SubjectGrade::factory()->create([
                'grade_report_id' => $report->id,
                'subject_code' => $code,
                'units' => 3,
                'final_grade' => '3.00',
                'remarks' => 'PASSED',
                'is_failed' => false,
                'is_major_subject' => true,
            ]);
        }

        foreach (['GEC 001', 'GEC 002'] as $code) {
            SubjectGrade::factory()->create([
                'grade_report_id' => $report->id,
                'subject_code' => $code,
                'units' => 3,
                'final_grade' => '1.50',
                'remarks' => 'PASSED',
                'is_failed' => false,
                'is_major_subject' => false,
            ]);
        }
    }

    /**
     * @param  array<string, int>  $scores
     */
    private function profile(Student $student, array $scores = []): void
    {
        SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'household_income_bracket' => '20k_40k',
            'scholarship_status' => 'none',
            'employment_status' => 'unemployed',
            'is_draft' => false,
        ]);
        SkillsExperience::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'SQL']);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'construct_scores' => $scores === [] ? [
                'study_habits' => 80,
                'time_management' => 75,
                'motivation' => 80,
                'procrastination' => 20,
                'engagement' => 85,
            ] : $scores,
        ]);
    }
}
