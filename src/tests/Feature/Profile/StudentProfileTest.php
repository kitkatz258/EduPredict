<?php

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Livewire\Student\AssessmentWizard;
use App\Livewire\Student\SkillsExperienceSection;
use App\Models\Program;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\User;
use App\Services\Profile\AssessmentProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_skills_without_a_household_step(): void
    {
        $student = $this->makeStudent();
        $profile = SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'household_income_bracket' => '20k_40k',
            'household_size' => '4',
            'is_draft' => false,
        ]);

        Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('openCreate', 'skill')
            ->set('skill.name', 'SQL')
            ->call('save')
            ->assertHasNoErrors()
            ->call('completeSection')
            ->assertDispatched('skills-section-saved');

        $this->assertSame(['SQL'], $student->skills()->pluck('name')->all());
        $this->assertFalse($student->fresh()->skillsExperience->is_draft);
        $this->assertSame('20k_40k', $profile->fresh()->household_income_bracket);
        $this->assertSame('4', $profile->fresh()->household_size);

        $this->actingAs($student->user)
            ->get(route('student.assessment'))
            ->assertOk()
            ->assertDontSee('Household profile')
            ->assertDontSee('Household income bracket');
    }

    public function test_sensitive_socioeconomic_values_are_encrypted_at_rest(): void
    {
        $student = $this->makeStudent();
        $profile = SocioeconomicProfile::factory()->create([
            'student_id' => $student->id,
            'household_income_bracket' => '20k_40k',
            'scholarship_status' => 'partial',
        ]);

        $raw = DB::table('socioeconomic_profiles')->where('id', $profile->id)->first();
        $this->assertIsString($raw->household_income_bracket);
        $this->assertStringNotContainsString('20k_40k', $raw->household_income_bracket);
        $this->assertStringNotContainsString('partial', $raw->scholarship_status);
        $this->assertSame('20k_40k', SocioeconomicProfile::query()->find($raw->id)->household_income_bracket);
    }

    public function test_incomplete_section_does_not_count_and_dashboard_shows_the_meter(): void
    {
        $student = $this->makeStudent();
        $progress = app(AssessmentProgress::class);

        $this->assertSame(0, $progress->for($student)['percent']);
        $this->assertArrayNotHasKey('socioeconomic', $progress->for($student)['sections']);

        Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('completeSection');

        $done = $progress->for($student->fresh());
        $this->assertTrue($done['sections']['skills']);
        $this->assertFalse($done['sections']['academic']);
        $this->assertFalse($done['sections']['questionnaire']);
        $this->assertSame(33, $done['percent']);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Assessment workflow progress')
            ->assertSee('Complete Assessment');

        $this->actingAs($student->user)
            ->get(route('student.assessment'))
            ->assertOk()
            ->assertSee('Step 1 of 4')
            ->assertSee('1/3 sections saved')
            ->assertSee('Assessment');
    }

    public function test_other_roles_and_other_students_cannot_read_the_profile(): void
    {
        $program = Program::factory()->create();
        $owner = $this->makeStudent($program, '2024-71001');
        $other = $this->makeStudent($program, '2024-71002');
        $profile = SocioeconomicProfile::factory()->create([
            'student_id' => $owner->id,
            'household_income_bracket' => 'below_10k',
            'is_draft' => false,
        ]);

        $this->assertTrue(Gate::forUser($owner->user)->allows('view', $profile));
        $this->assertFalse(Gate::forUser($other->user)->allows('view', $profile));

        foreach ([UserRole::DepartmentHead, UserRole::Dean, UserRole::Administrator] as $role) {
            $staff = User::factory()->role($role)->create();
            $this->assertFalse(Gate::forUser($staff)->allows('view', $profile));
            $this->actingAs($staff)->get(route('student.assessment'))->assertForbidden();
            Livewire::actingAs($staff)->test(AssessmentWizard::class)->assertForbidden();
        }

        $this->actingAs($other->user)
            ->get(route('student.assessment'))
            ->assertOk()
            ->assertDontSee('below_10k')
            ->assertDontSee('Household income bracket');

        $this->assertSame('below_10k', $profile->fresh()->household_income_bracket);
        $this->assertNull($other->fresh()->socioeconomicProfile);
    }

    public function test_the_active_assessment_does_not_require_household_fields(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->call('goTo', 'skills')
            ->assertSet('step', 'skills')
            ->assertDontSee('Household income bracket')
            ->assertDontSee('Scholarship status');
    }

    private function makeStudent(?Program $program = null, string $number = '2024-71000'): Student
    {
        $program ??= Program::factory()->create();
        $user = User::factory()->create();

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
        ]);
    }
}
