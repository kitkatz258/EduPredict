<?php

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Livewire\Student\AssessmentWizard;
use App\Livewire\Student\SkillsExperienceSection;
use App\Models\Prediction;
use App\Models\QuestionnaireResponse;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\StudentCertification;
use App\Models\StudentSkill;
use App\Models\StudentWorkExperience;
use App\Models\User;
use App\Services\Prediction\FeatureBuilder;
use App\Services\Prediction\PredictionRequester;
use App\Services\Privacy\StudentDataExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SkillsExperienceSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_adds_edits_archives_and_restores_structured_entries(): void
    {
        $student = Student::factory()->create();

        $component = Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('openCreate', 'skill')
            ->assertSet('modal', 'skill')
            ->set('skill.name', 'SQL')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('modal', null)
            ->call('openCreate', 'certification')
            ->set('certification.title', 'IT Specialist - Databases')
            ->set('certification.issuer', 'Certiport')
            ->set('certification.issued_on', '2025-03-14')
            ->set('certification.credential_reference', 'ABC-123')
            ->call('save')
            ->assertHasNoErrors()
            ->call('openCreate', 'experience')
            ->set('experience.experience_type', 'ojt_internship')
            ->set('experience.organization', 'City Hall ICT Office')
            ->set('experience.role_title', 'OJT trainee')
            ->set('experience.start_date', '2025-06-02')
            ->set('experience.is_ongoing', true)
            ->set('experience.description', 'Help desk and inventory')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('SQL')
            ->assertSee('IT Specialist - Databases')
            ->assertSee('OJT / Internship');

        $certification = $student->certifications()->firstOrFail();
        $this->assertSame(2025, $certification->issued_year);
        $this->assertSame('ABC-123', $certification->credential_reference);
        $experience = $student->workExperiences()->firstOrFail();
        $this->assertTrue($experience->isInternship());
        $this->assertTrue($experience->is_ongoing);
        $this->assertNull($experience->end_date);

        $skill = $student->skills()->firstOrFail();
        $component->call('openEdit', 'skill', $skill->id)
            ->assertSet('skill.name', 'SQL')
            ->set('skill.name', 'PostgreSQL')
            ->call('save')
            ->assertHasNoErrors()
            ->call('openView', 'certification', $certification->id)
            ->assertSet('viewOnly', true)
            ->assertSee('Certification details')
            ->call('closeModal')
            ->call('archive', 'skill', $skill->id);

        $this->assertSame('PostgreSQL', $skill->fresh()->name);
        $this->assertNotNull($skill->fresh()->archived_at);
        $this->assertDatabaseCount('student_skills', 1);

        $component->call('toggleArchived')
            ->assertSee('Archived entries (1)')
            ->call('restore', 'skill', $skill->id);
        $this->assertNull($skill->fresh()->archived_at);
    }

    public function test_entries_are_validated(): void
    {
        $student = Student::factory()->create();
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'SQL']);

        Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('openCreate', 'skill')
            ->set('skill.name', 'sql')
            ->call('save')
            ->assertHasErrors(['skill.name'])
            ->call('openCreate', 'certification')
            ->call('save')
            ->assertHasErrors(['certification.title', 'certification.issuer', 'certification.issued_year'])
            ->set('certification.title', 'Cert')
            ->set('certification.issuer', 'Issuer')
            ->set('certification.issued_on', '2025-05-01')
            ->set('certification.expires_on', '2025-01-01')
            ->call('save')
            ->assertHasErrors(['certification.expires_on'])
            ->call('openCreate', 'experience')
            ->set('experience.experience_type', 'astronaut')
            ->set('experience.start_date', '2025-06-01')
            ->set('experience.end_date', '2025-05-01')
            ->call('save')
            ->assertHasErrors(['experience.experience_type', 'experience.organization', 'experience.role_title', 'experience.end_date']);

        $this->assertDatabaseCount('student_certifications', 0);
        $this->assertDatabaseCount('student_work_experiences', 0);
    }

    public function test_other_students_and_staff_cannot_touch_entries(): void
    {
        $owner = Student::factory()->create();
        $other = Student::factory()->create();
        $skill = StudentSkill::factory()->create(['student_id' => $owner->id, 'name' => 'SQL']);
        $experience = StudentWorkExperience::factory()->create(['student_id' => $owner->id]);

        Livewire::actingAs($other->user)
            ->test(SkillsExperienceSection::class)
            ->call('openEdit', 'skill', $skill->id)
            ->assertForbidden();
        Livewire::actingAs($other->user)
            ->test(SkillsExperienceSection::class)
            ->call('archive', 'experience', $experience->id)
            ->assertForbidden();
        Livewire::actingAs($other->user)
            ->test(SkillsExperienceSection::class)
            ->call('openView', 'experience', $experience->id)
            ->assertForbidden();

        $this->assertSame('SQL', $skill->fresh()->name);
        $this->assertNull($experience->fresh()->archived_at);

        foreach ([UserRole::DepartmentHead, UserRole::Dean, UserRole::Administrator] as $role) {
            Livewire::actingAs(User::factory()->role($role)->create())
                ->test(SkillsExperienceSection::class)
                ->assertForbidden();
        }
    }

    public function test_completing_the_section_moves_the_wizard_to_grades(): void
    {
        $student = Student::factory()->create();

        Livewire::actingAs($student->user)
            ->test(SkillsExperienceSection::class)
            ->call('completeSection')
            ->assertDispatched('skills-section-saved');
        $this->assertFalse($student->skillsExperience()->firstOrFail()->is_draft);

        Livewire::actingAs($student->user)
            ->test(AssessmentWizard::class, ['step' => 'skills'])
            ->assertSee('Technical skills')
            ->assertDontSee('Projects')
            ->dispatch('skills-section-saved')
            ->assertSet('step', 'grades');
    }

    public function test_features_count_only_active_entries_and_projects_are_not_used(): void
    {
        $student = Student::factory()->create();
        SkillsExperience::factory()->create([
            'student_id' => $student->id,
            'projects' => [['title' => 'Capstone']],
            'is_draft' => false,
        ]);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'PHP']);
        StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'Gone', 'archived_at' => now()]);
        StudentWorkExperience::factory()->internship()->create(['student_id' => $student->id]);
        StudentWorkExperience::factory()->internship()->create(['student_id' => $student->id, 'archived_at' => now()]);
        StudentWorkExperience::factory()->create(['student_id' => $student->id, 'experience_type' => 'volunteer']);

        $features = app(FeatureBuilder::class)->build($student);

        $this->assertSame(1, $features->technicalSkillCount);
        $this->assertSame(1, $features->internshipCount);
        $this->assertSame(1, $features->workExperienceCount);
        $this->assertSame(0, $features->certificationCount);
        $this->assertSame(0, $features->projectCount);
    }

    public function test_prediction_keeps_an_immutable_skills_snapshot(): void
    {
        $student = Student::factory()->create();
        SocioeconomicProfile::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        SkillsExperience::factory()->create(['student_id' => $student->id, 'is_draft' => false]);
        QuestionnaireResponse::query()->create([
            'student_id' => $student->id,
            'submitted_at' => now(),
            'construct_scores' => ['study_habits' => 70, 'time_management' => 70, 'motivation' => 70, 'procrastination' => 30, 'engagement' => 70],
        ]);
        $skill = StudentSkill::factory()->create(['student_id' => $student->id, 'name' => 'SQL']);
        StudentCertification::factory()->create(['student_id' => $student->id, 'title' => 'Cert A']);
        StudentWorkExperience::factory()->internship()->create(['student_id' => $student->id]);

        $prediction = app(PredictionRequester::class)->request($student, $student->user);
        $snapshot = $prediction->fresh()->assessment_snapshot['skills_experience'];

        $this->assertTrue($snapshot['section_saved']);
        $this->assertSame([['name' => 'SQL']], $snapshot['technical_skills']);
        $this->assertSame('Cert A', $snapshot['certifications'][0]['title']);
        $this->assertSame('ojt_internship', $snapshot['work_experience'][0]['experience_type']);
        $this->assertArrayNotHasKey('projects', $snapshot);

        $skill->update(['name' => 'Renamed']);
        StudentCertification::query()->update(['archived_at' => now()]);

        $stored = Prediction::query()->findOrFail($prediction->id)->assessment_snapshot['skills_experience'];
        $this->assertSame([['name' => 'SQL']], $stored['technical_skills']);
        $this->assertSame('Cert A', $stored['certifications'][0]['title']);
    }

    public function test_legacy_json_entries_are_copied_without_touching_the_original(): void
    {
        $student = Student::factory()->create();
        DB::table('skills_experiences')->insert([
            'student_id' => $student->id,
            'technical_skills' => json_encode(['SQL', 'PHP']),
            'certifications' => json_encode([['name' => 'AWS Cloud Practitioner', 'year' => '2025']]),
            'internships' => json_encode([['title' => 'OJT', 'hours' => 200], ['organization' => 'City Hall', 'role' => 'IT intern']]),
            'projects' => json_encode([['title' => 'Capstone', 'description' => 'Grade viewer']]),
            'work_experience' => json_encode([['employer' => 'Campus library', 'role' => 'Student assistant']]),
            'is_draft' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = (array) DB::table('skills_experiences')->where('student_id', $student->id)->first();

        $migration = require database_path('migrations/2026_10_09_160000_create_structured_skill_records.php');
        (new \ReflectionMethod($migration, 'copyLegacyEntries'))->invoke($migration);

        $this->assertSame(['PHP', 'SQL'], $student->skills()->orderBy('name')->pluck('name')->all());
        $certification = $student->certifications()->firstOrFail();
        $this->assertSame('AWS Cloud Practitioner', $certification->title);
        $this->assertSame(2025, $certification->issued_year);
        $this->assertSame('skills_experiences.certifications', $certification->legacy_source);

        $internships = $student->workExperiences()->where('experience_type', 'ojt_internship')->orderBy('id')->get();
        $this->assertCount(2, $internships);
        $this->assertSame('OJT', $internships[0]->role_title);
        $this->assertSame('200 hours', $internships[0]->description);
        $this->assertSame('City Hall', $internships[1]->organization);
        $work = $student->workExperiences()->where('experience_type', 'other')->firstOrFail();
        $this->assertSame('Campus library', $work->organization);

        $this->assertSame($before, (array) DB::table('skills_experiences')->where('student_id', $student->id)->first());

        $export = app(StudentDataExporter::class)->forStudent($student->fresh());
        $this->assertSame('Capstone', $export['skills']['legacy_entries']['projects'][0]['title']);
        $this->assertCount(2, $export['skills']['technical_skills']);
    }
}
