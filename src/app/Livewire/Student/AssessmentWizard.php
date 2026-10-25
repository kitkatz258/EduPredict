<?php

namespace App\Livewire\Student;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\GradeReport;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Services\Grades\AcademicSummary;
use App\Services\Profile\AssessmentProgress;
use App\Services\Questionnaire\SectionScorePresenter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class AssessmentWizard extends Component
{
    use DispatchesToasts;

    /** Wizard order. Continue and Back walk this list. */
    public const STEPS = [
        'questionnaire' => 'Questionnaire',
        'skills' => 'Skills & Experience',
        'grades' => 'Grades',
        'review' => 'Review & Run',
    ];

    /** Older links used the M15, M22, and household step names. */
    private const ALIASES = [
        'academic_behavior' => 'questionnaire',
        'employability' => 'questionnaire',
        'socioeconomic' => 'skills',
    ];

    #[Url(except: 'questionnaire', history: true)]
    public string $step = 'questionnaire';

    /** `latest` keeps the confirmed grades on file; `update` opens the grade editor. */
    public string $gradeChoice = 'latest';

    public ?int $gradeDraftId = null;

    public ?int $gradeReplaceId = null;

    public int $gradeFormKey = 0;

    public function mount(): void
    {
        $this->authorizeStudent();
        $this->step = $this->normalizeStep($this->step) ?? 'questionnaire';
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
        $this->step = 'skills';
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

    public function render(AcademicSummary $academic, AssessmentProgress $progress, SectionScorePresenter $sectionScores): View
    {
        $student = $this->authorizeStudent();
        $questionnaireDraft = $student->questionnaireResponses()->whereNull('submitted_at')->exists();
        $latestSubmission = $student->questionnaireResponses()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        return view('livewire.student.assessment-wizard', [
            'steps' => self::STEPS,
            'stepIndex' => (int) array_search($this->step, array_keys(self::STEPS), true),
            'academic' => $academic->for($student),
            'progress' => $progress->for($student),
            'drafts' => [
                'questionnaire' => $questionnaireDraft,
            ],
            'submissions' => $student->questionnaireResponses()->latest('id')->limit(8)->get(),
            'sectionScores' => $sectionScores->rows(is_array($latestSubmission?->construct_scores) ? $latestSubmission->construct_scores : null),
            'questionnaireSections' => config('edupredict.questionnaire.sections', []),
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
}
