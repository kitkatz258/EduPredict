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

    public function test_student_can_save_edit_and_reopen_both_sections(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->set($this->socioPayload())
            ->call('saveSocioeconomic')
            ->assertHasNoErrors()
            ->assertSet('step', 'employability');

        Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('openCreate', 'skill')
            ->set('skill.name', 'SQL')
            ->call('save')
            ->assertHasNoErrors()
            ->call('completeSection')
            ->assertDispatched('skills-section-saved');

        $student->refresh();
        $this->assertFalse($student->socioeconomicProfile->is_draft);
        $this->assertSame('20k_40k', $student->socioeconomicProfile->household_income_bracket);
        $this->assertSame('4', $student->socioeconomicProfile->household_size);
        $this->assertSame(['SQL'], $student->skills()->pluck('name')->all());
        $this->assertFalse($student->skillsExperience->is_draft);

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->assertSet('household_income_bracket', '20k_40k')
            ->set('employment_status', 'part_time')
            ->call('saveSocioeconomic', true)
            ->assertHasNoErrors();

        $this->assertSame('part_time', $student->socioeconomicProfile()->first()->employment_status);
        $this->assertTrue($student->socioeconomicProfile()->first()->is_draft);
    }

    public function test_sensitive_socioeconomic_values_are_encrypted_at_rest(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->set($this->socioPayload())
            ->call('saveSocioeconomic')
            ->assertHasNoErrors();

        $raw = DB::table('socioeconomic_profiles')->where('student_id', $student->id)->first();
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
        $this->assertFalse($progress->for($student)['sections']['socioeconomic']);

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->set('household_income_bracket', 'below_10k')
            ->call('saveSocioeconomic', true)
            ->assertHasNoErrors();

        $afterDraft = $progress->for($student->fresh());
        $this->assertFalse($afterDraft['sections']['socioeconomic']);
        $this->assertSame(0, $afterDraft['percent']);

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->set($this->socioPayload())
            ->call('saveSocioeconomic');
        Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('completeSection');

        $done = $progress->for($student->fresh());
        $this->assertTrue($done['sections']['socioeconomic']);
        $this->assertTrue($done['sections']['skills']);
        $this->assertFalse($done['sections']['academic']);
        $this->assertFalse($done['sections']['questionnaire']);
        $this->assertSame(50, $done['percent']);

        $this->actingAs($student->user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Assessment workflow progress')
            ->assertSee('50%');

        $this->actingAs($student->user)
            ->get(route('student.assessment'))
            ->assertOk()
            ->assertSee('Step 1 of 6')
            ->assertSee('2/4 sections saved')
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

        Livewire::actingAs($other->user)
            ->test(AssessmentWizard::class)
            ->set($this->socioPayload(['household_income_bracket' => 'above_70k']))
            ->call('saveSocioeconomic')
            ->assertHasNoErrors();

        $this->assertSame('below_10k', $profile->fresh()->household_income_bracket);
        $this->assertSame('above_70k', $other->fresh()->socioeconomicProfile->household_income_bracket);
    }

    public function test_completing_socioeconomic_requires_the_fields(): void
    {
        $student = $this->makeStudent();

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class)
            ->set('household_income_bracket', 'below_10k')
            ->call('saveSocioeconomic')
            ->assertHasErrors(['household_size', 'scholarship_status']);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function socioPayload(array $overrides = []): array
    {
        return array_merge([
            'household_income_bracket' => '20k_40k',
            'household_size' => '4',
            'scholarship_status' => 'partial',
            'employment_status' => 'unemployed',
            'living_arrangement' => 'with_family',
            'has_internet' => 'yes',
            'has_device' => 'shared',
            'has_study_space' => 'yes',
        ], $overrides);
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
