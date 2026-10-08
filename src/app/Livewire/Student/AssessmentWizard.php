<?php

namespace App\Livewire\Student;

use App\Http\Requests\Student\SocioeconomicProfileRequest;
use App\Livewire\Concerns\DispatchesToasts;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Services\Grades\AcademicSummary;
use App\Services\Profile\AssessmentProgress;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class AssessmentWizard extends Component
{
    use DispatchesToasts;

    #[Url(except: 'questionnaire', history: true)]
    public string $step = 'questionnaire';

    public string $questionnaireSection = 'academic_behavior';

    public bool $showGradeEditor = false;

    public string $statusMessage = '';

    public ?string $household_income_bracket = null;

    public ?string $household_size = null;

    public ?string $scholarship_status = null;

    public ?string $employment_status = null;

    public ?string $living_arrangement = null;

    public ?string $has_internet = null;

    public ?string $has_device = null;

    public ?string $has_study_space = null;

    public function mount(): void
    {
        $this->authorizeStudent();
        if (! in_array($this->step, $this->steps(), true)) {
            $this->step = 'questionnaire';
        }
        $student = $this->student();
        $this->fillSocioeconomic($student->socioeconomicProfile);
    }

    public function goTo(string $step): void
    {
        $this->authorizeStudent();
        if (! in_array($step, $this->steps(), true)) {
            return;
        }

        $this->step = $step;
        $this->statusMessage = '';
    }

    public function selectQuestionnaireSection(string $section): void
    {
        if (array_key_exists($section, config('edupredict.questionnaire.sections', []))) {
            $this->questionnaireSection = $section;
            $this->resetValidation();
        }
    }

    #[On('skills-section-saved')]
    public function skillsSectionSaved(): void
    {
        $this->authorizeStudent();
        $this->step = 'grades';
        $this->statusMessage = 'Skills and experience section saved.';
    }

    public function toggleGradeEditor(): void
    {
        $this->showGradeEditor = ! $this->showGradeEditor;
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
        $this->toast($this->statusMessage);

        if (! $asDraft) {
            $this->questionnaireSection = 'employability';
        }
    }

    public function render(AcademicSummary $academic, AssessmentProgress $progress): View
    {
        $student = $this->authorizeStudent();

        return view('livewire.student.assessment-wizard', [
            'academic' => $academic->for($student),
            'progress' => $progress->for($student),
            'questionnaireSections' => config('edupredict.questionnaire.sections', []),
            'incomeOptions' => config('edupredict.profile.income_brackets', []),
            'scholarshipOptions' => config('edupredict.profile.scholarship_statuses', []),
            'employmentOptions' => config('edupredict.profile.employment_statuses', []),
            'livingOptions' => config('edupredict.profile.living_arrangements', []),
            'internetOptions' => config('edupredict.profile.internet_access', []),
            'deviceOptions' => config('edupredict.profile.device_access', []),
            'studySpaceOptions' => config('edupredict.profile.study_space', []),
            'confirmedGradeReports' => $student->gradeReports()->where('status', 'confirmed')->latest('created_at')->get(),
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

        return $student->load(['socioeconomicProfile', 'program']);
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

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function steps(): array
    {
        return ['questionnaire', 'skills', 'grades', 'review'];
    }
}
