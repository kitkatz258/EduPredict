<?php

namespace App\Livewire\Student;

use App\Http\Requests\Student\SkillsExperienceRequest;
use App\Http\Requests\Student\SocioeconomicProfileRequest;
use App\Models\SkillsExperience;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Services\Grades\AcademicSummary;
use App\Services\Profile\ProfileCompleteness;
use App\Services\Profile\SkillsListParser;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProfileWizard extends Component
{
    public string $step = 'academic';

    public string $statusMessage = '';

    public ?string $household_income_bracket = null;

    public ?string $household_size = null;

    public ?string $scholarship_status = null;

    public ?string $employment_status = null;

    public ?string $living_arrangement = null;

    public ?string $has_internet = null;

    public ?string $has_device = null;

    public ?string $has_study_space = null;

    public string $technicalSkills = '';

    public string $certifications = '';

    public string $internships = '';

    public string $projects = '';

    public string $workExperience = '';

    public function mount(): void
    {
        $this->authorizeStudent();
        $student = $this->student();
        $this->fillSocioeconomic($student->socioeconomicProfile);
        $this->fillSkills($student->skillsExperience);
    }

    public function goTo(string $step): void
    {
        $this->authorizeStudent();
        if (! in_array($step, ['academic', 'socioeconomic', 'skills'], true)) {
            return;
        }

        $this->step = $step;
        $this->statusMessage = '';
    }

    public function saveSocioeconomic(bool $asDraft = false): void
    {
        $student = $this->authorizeStudent();
        $existing = $student->socioeconomicProfile;
        $existing
            ? $this->authorize('update', $existing)
            : $this->authorize('create', SocioeconomicProfile::class);

        $this->blankSocioeconomicToNull();
        $rules = $asDraft
            ? SocioeconomicProfileRequest::draftRules()
            : SocioeconomicProfileRequest::fieldRules(required: true);
        $validated = $this->validate($rules);

        $profile = $student->socioeconomicProfile()->updateOrCreate(
            ['student_id' => $student->id],
            [
                'household_income_bracket' => $this->blankToNull($validated['household_income_bracket'] ?? null),
                'household_size' => isset($validated['household_size']) && $validated['household_size'] !== null && $validated['household_size'] !== ''
                    ? (string) $validated['household_size']
                    : null,
                'scholarship_status' => $this->blankToNull($validated['scholarship_status'] ?? null),
                'employment_status' => $this->blankToNull($validated['employment_status'] ?? null),
                'living_arrangement' => $this->blankToNull($validated['living_arrangement'] ?? null),
                'has_internet' => $this->blankToNull($validated['has_internet'] ?? null),
                'has_device' => $this->blankToNull($validated['has_device'] ?? null),
                'has_study_space' => $this->blankToNull($validated['has_study_space'] ?? null),
                'is_draft' => $asDraft,
            ],
        );

        $this->authorize('update', $profile);
        $this->statusMessage = $asDraft
            ? 'Socioeconomic draft saved. It stays private to you.'
            : 'Socioeconomic section saved.';

        if (! $asDraft) {
            $this->step = 'skills';
        }
    }

    public function saveSkills(bool $asDraft = false, ?SkillsListParser $parser = null): void
    {
        $student = $this->authorizeStudent();
        $existing = $student->skillsExperience;
        $existing
            ? $this->authorize('update', $existing)
            : $this->authorize('create', SkillsExperience::class);

        $validated = $this->validate(SkillsExperienceRequest::fieldRules());
        $parser ??= app(SkillsListParser::class);
        $certifications = array_map(
            fn (array $pair): array => ['name' => $pair['name'], 'year' => $pair['detail']],
            $parser->pairs($validated['certifications'] ?? ''),
        );
        $internships = array_map(
            fn (array $pair): array => ['organization' => $pair['name'], 'role' => $pair['detail']],
            $parser->pairs($validated['internships'] ?? ''),
        );
        $projects = array_map(
            fn (array $pair): array => ['title' => $pair['name'], 'description' => $pair['detail']],
            $parser->pairs($validated['projects'] ?? ''),
        );
        $work = array_map(
            fn (array $pair): array => ['employer' => $pair['name'], 'role' => $pair['detail']],
            $parser->pairs($validated['workExperience'] ?? ''),
        );

        $skills = $student->skillsExperience()->updateOrCreate(
            ['student_id' => $student->id],
            [
                'technical_skills' => $parser->strings($validated['technicalSkills'] ?? ''),
                'certifications' => $certifications,
                'internships' => $internships,
                'projects' => $projects,
                'work_experience' => $work,
                'is_draft' => $asDraft,
            ],
        );

        $this->authorize('update', $skills);
        $this->statusMessage = $asDraft
            ? 'Skills draft saved.'
            : 'Skills and experience section saved.';
    }

    public function render(AcademicSummary $academic, ProfileCompleteness $completeness): View
    {
        $student = $this->authorizeStudent();

        return view('livewire.student.profile-wizard', [
            'academic' => $academic->for($student),
            'completeness' => $completeness->for($student),
            'incomeOptions' => config('edupredict.profile.income_brackets', []),
            'scholarshipOptions' => config('edupredict.profile.scholarship_statuses', []),
            'employmentOptions' => config('edupredict.profile.employment_statuses', []),
            'livingOptions' => config('edupredict.profile.living_arrangements', []),
            'internetOptions' => config('edupredict.profile.internet_access', []),
            'deviceOptions' => config('edupredict.profile.device_access', []),
            'studySpaceOptions' => config('edupredict.profile.study_space', []),
        ]);
    }

    private function authorizeStudent(): Student
    {
        $this->authorize('create', SocioeconomicProfile::class);
        $student = auth()->user()?->student;
        abort_unless($student instanceof Student, 403);

        return $student;
    }

    private function student(): Student
    {
        $student = auth()->user()?->student;
        abort_unless($student instanceof Student, 403);

        return $student->load(['socioeconomicProfile', 'skillsExperience', 'program']);
    }

    private function fillSocioeconomic(?SocioeconomicProfile $profile): void
    {
        if ($profile === null) {
            return;
        }

        $this->authorize('view', $profile);
        $this->household_income_bracket = $profile->household_income_bracket;
        $this->household_size = $profile->household_size;
        $this->scholarship_status = $profile->scholarship_status;
        $this->employment_status = $profile->employment_status;
        $this->living_arrangement = $profile->living_arrangement;
        $this->has_internet = $profile->has_internet;
        $this->has_device = $profile->has_device;
        $this->has_study_space = $profile->has_study_space;
    }

    private function blankSocioeconomicToNull(): void
    {
        foreach ([
            'household_income_bracket',
            'household_size',
            'scholarship_status',
            'employment_status',
            'living_arrangement',
            'has_internet',
            'has_device',
            'has_study_space',
        ] as $field) {
            if (trim((string) $this->{$field}) === '') {
                $this->{$field} = null;
            }
        }
    }

    private function fillSkills(?SkillsExperience $skills): void
    {
        if ($skills === null) {
            return;
        }

        $this->authorize('view', $skills);
        $parser = app(SkillsListParser::class);
        $this->technicalSkills = $parser->stringsToText($skills->technical_skills ?? []);
        $this->certifications = $parser->pairsToText($skills->certifications ?? [], ['name'], ['year']);
        $this->internships = $parser->pairsToText($skills->internships ?? [], ['organization', 'title'], ['role', 'hours']);
        $this->projects = $parser->pairsToText($skills->projects ?? [], ['title', 'name'], ['description', 'detail']);
        $this->workExperience = $parser->pairsToText($skills->work_experience ?? [], ['employer', 'organization'], ['role', 'description']);
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
