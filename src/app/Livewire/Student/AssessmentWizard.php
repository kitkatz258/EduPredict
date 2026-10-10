<?php

namespace App\Livewire\Student;

use App\Http\Requests\Student\SocioeconomicProfileRequest;
use App\Livewire\Concerns\DispatchesToasts;
use App\Models\GradeReport;
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

    /** Wizard order. Continue and Back walk this list. */
    public const STEPS = [
        'academic_behavior' => 'Academic Behavior',
        'socioeconomic' => 'Socioeconomic Factors',
        'employability' => 'Employability Assessment',
        'skills' => 'Skills & Experience',
        'grades' => 'Grades',
        'review' => 'Review & Run',
    ];

    /** Older links used the M15 step names. */
    private const ALIASES = ['questionnaire' => 'academic_behavior'];

    #[Url(except: 'academic_behavior', history: true)]
    public string $step = 'academic_behavior';

    /** `latest` keeps the confirmed grades on file; `update` opens the grade editor. */
    public string $gradeChoice = 'latest';

    public ?int $gradeDraftId = null;

    public ?int $gradeReplaceId = null;

    public int $gradeFormKey = 0;

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
        $this->step = $this->normalizeStep($this->step) ?? 'academic_behavior';
        $student = $this->student();
        $this->fillSocioeconomic($student->socioeconomicProfile);
    }

    public function goTo(string $step): void
    {
        $this->authorizeStudent();
        $step = $this->normalizeStep($step);
        if ($step === null) {
            return;
        }

        $this->step = $step;
        $this->resetValidation();
    }

    public function next(): void
    {
        $this->move(1);
    }

    public function back(): void
    {
        $this->move(-1);
    }

    #[On('questionnaire-submitted')]
    public function questionnaireSubmitted(): void
    {
        $this->authorizeStudent();
        $this->step = 'socioeconomic';
    }

    #[On('skills-section-saved')]
    public function skillsSectionSaved(): void
    {
        $this->authorizeStudent();
        $this->step = 'grades';
    }

    public function chooseGrades(string $choice): void
    {
        $this->authorizeStudent();
        if (in_array($choice, ['latest', 'update'], true)) {
            $this->gradeChoice = $choice;
        }
    }

    #[On('grade-report-continue')]
    public function continueGradeDraft(int $id): void
    {
        $this->openGradeEditor(draftId: $id);
    }

    #[On('grade-report-replace')]
    public function replaceGradeReport(int $id): void
    {
        $this->openGradeEditor(replaceId: $id);
    }

    /**
     * Drafts only. Confirmed versions stay for history and are replaced, not deleted.
     */
    public function deleteGradeDraft(int $id): void
    {
        $student = $this->authorizeStudent();
        $report = GradeReport::query()->where('student_id', $student->id)->findOrFail($id);
        $this->authorize('delete', $report);
        $report->subjectGrades()->delete();
        $report->delete();
        if ($this->gradeDraftId === $id) {
            $this->gradeDraftId = null;
            $this->gradeFormKey++;
        }
        $this->toast('Draft deleted.');
    }

    #[On('grades-updated')]
    public function gradesUpdated(): void
    {
        $this->authorizeStudent();
        $this->gradeDraftId = null;
        $this->gradeReplaceId = null;
    }

    /**
     * The form re-checks ownership on mount; these ids only pick what it loads.
     */
    private function openGradeEditor(?int $draftId = null, ?int $replaceId = null): void
    {
        $this->authorizeStudent();
        $this->gradeChoice = 'update';
        $this->gradeDraftId = $draftId;
        $this->gradeReplaceId = $replaceId;
        $this->gradeFormKey++;
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
        $this->toast($asDraft
            ? 'Socioeconomic draft saved. It stays private to you.'
            : 'Socioeconomic section saved.');

        if (! $asDraft) {
            $this->step = 'employability';
        }
    }

    public function render(AcademicSummary $academic, AssessmentProgress $progress): View
    {
        $student = $this->authorizeStudent();
        $questionnaireDraft = $student->questionnaireResponses()->whereNull('submitted_at')->exists();
        $socioeconomic = $student->socioeconomicProfile()->first();

        return view('livewire.student.assessment-wizard', [
            'steps' => self::STEPS,
            'stepIndex' => (int) array_search($this->step, array_keys(self::STEPS), true),
            'academic' => $academic->for($student),
            'progress' => $progress->for($student),
            'drafts' => [
                'academic_behavior' => $questionnaireDraft,
                'socioeconomic' => $socioeconomic !== null && $socioeconomic->is_draft,
            ],
            'questionnaireSections' => config('edupredict.questionnaire.sections', []),
            'incomeOptions' => config('edupredict.profile.income_brackets', []),
            'scholarshipOptions' => config('edupredict.profile.scholarship_statuses', []),
            'employmentOptions' => config('edupredict.profile.employment_statuses', []),
            'livingOptions' => config('edupredict.profile.living_arrangements', []),
            'internetOptions' => config('edupredict.profile.internet_access', []),
            'deviceOptions' => config('edupredict.profile.device_access', []),
            'studySpaceOptions' => config('edupredict.profile.study_space', []),
            'currentGradeReports' => $student->gradeReports()->current()->latest('confirmed_at')->latest('id')->get(),
            'draftGradeReports' => $student->gradeReports()->where('status', 'draft')->latest('updated_at')->latest('id')->get(),
        ]);
    }

    private function move(int $offset): void
    {
        $this->authorizeStudent();
        $keys = array_keys(self::STEPS);
        $index = array_search($this->step, $keys, true);
        $target = $keys[(is_int($index) ? $index : 0) + $offset] ?? null;
        if ($target !== null) {
            $this->step = $target;
            $this->resetValidation();
        }
    }

    private function normalizeStep(string $step): ?string
    {
        $step = self::ALIASES[$step] ?? $step;

        return array_key_exists($step, self::STEPS) ? $step : null;
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
}
