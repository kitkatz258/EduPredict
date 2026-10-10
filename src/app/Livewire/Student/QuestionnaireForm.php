<?php

namespace App\Livewire\Student;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\Student;
use App\Services\Questionnaire\QuestionnaireScorer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class QuestionnaireForm extends Component
{
    use DispatchesToasts;

    /** @var array<int|string, int|string|null> */
    public array $answers = [];

    public string $statusMessage = '';

    public string $definitionVersion = 'draft-v1';

    public string $construct = 'study_habits';

    public function mount(): void
    {
        $student = $this->student();
        $draft = $this->openDraft($student);
        $this->definitionVersion = $draft?->definition_version
            ?? (string) config('edupredict.questionnaire.current_version', 'draft-v1');

        if ($draft !== null) {
            $this->authorize('view', $draft);
            foreach ($draft->answers as $answer) {
                $this->answers[$answer->questionnaire_item_id] = $answer->value;
            }
        }

        $this->construct = $this->firstConstruct();
    }

    public function saveDraft(): void
    {
        $this->persist($this->student(), submit: false);
        $this->statusMessage = 'Questionnaire draft saved. You can finish it later.';
        $this->toast($this->statusMessage);
    }

    public function saveAndContinue(): void
    {
        $this->persist($this->student(), submit: false);
        $this->statusMessage = 'Your answers are saved.';
        $this->nextConstruct();
        $this->toast('Questionnaire progress saved.');
    }

    public function submit(QuestionnaireScorer $scorer): void
    {
        $student = $this->student();
        if ($this->openDraft($student) === null && $student->questionnaireResponses()->whereNotNull('submitted_at')->exists()) {
            $this->statusMessage = 'Start a new response before answering again. Earlier submissions are kept.';

            return;
        }

        $items = $this->activeItems();
        $missing = $items->first(fn (QuestionnaireItem $item): bool => ! isset($this->answers[$item->id]) || $this->answers[$item->id] === '');
        if ($missing !== null) {
            $this->construct = $missing->construct;
            $this->addError('answers.'.$missing->id, 'Choose a response for this statement.');
            $this->statusMessage = 'Answer every statement before submitting.';

            return;
        }

        $rules = [];
        foreach ($items as $item) {
            $rules['answers.'.$item->id] = ['required', 'integer', 'between:1,5'];
        }
        $this->validate($rules);

        $this->persist($student, submit: true, scorer: $scorer);
        $this->statusMessage = 'Questionnaire submitted. Your construct scores are saved with this response.';
        $this->toast('Questionnaire submitted.');
        $this->dispatch('questionnaire-submitted');
    }

    public function retake(): void
    {
        $student = $this->student();
        $this->authorize('create', QuestionnaireResponse::class);

        if ($this->openDraft($student) !== null) {
            return;
        }

        $this->definitionVersion = (string) config('edupredict.questionnaire.current_version', 'draft-v1');
        $student->questionnaireResponses()->create([
            'definition_version' => $this->definitionVersion,
            'submitted_at' => null,
            'construct_scores' => null,
        ]);
        $this->answers = [];
        $this->construct = $this->firstConstruct();
        $this->statusMessage = 'A new questionnaire response is ready. Earlier submissions stay in history.';
        $this->toast($this->statusMessage);
    }

    public function selectConstruct(string $construct): void
    {
        if (in_array($construct, $this->constructKeys(), true)) {
            $this->construct = $construct;
            $this->resetValidation();
        }
    }

    public function previousConstruct(): void
    {
        $keys = $this->constructKeys();
        $index = array_search($this->construct, $keys, true);
        if (is_int($index) && $index > 0) {
            $this->construct = $keys[$index - 1];
            $this->resetValidation();
        }
    }

    public function nextConstruct(): void
    {
        $keys = $this->constructKeys();
        $index = array_search($this->construct, $keys, true);
        if (is_int($index) && isset($keys[$index + 1])) {
            $this->construct = $keys[$index + 1];
            $this->resetValidation();
        }
    }

    public function render(): View
    {
        $student = $this->student();
        $allItems = $this->activeItems();
        $keys = $this->constructKeys($allItems);
        if (! in_array($this->construct, $keys, true)) {
            $this->construct = $keys[0] ?? 'study_habits';
        }
        $answered = $allItems
            ->filter(fn (QuestionnaireItem $item): bool => isset($this->answers[$item->id]) && $this->answers[$item->id] !== '')
            ->count();
        $currentIndex = array_search($this->construct, $keys, true);

        $latestSubmission = $student->questionnaireResponses()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        return view('livewire.student.questionnaire-form', [
            'items' => $allItems->where('construct', $this->construct)->values(),
            'allItems' => $allItems,
            'constructKeys' => $keys,
            'constructs' => config('edupredict.questionnaire.constructs', []),
            'likert' => config('edupredict.questionnaire.likert', []),
            'version' => config('edupredict.questionnaire.versions.'.$this->definitionVersion, []),
            'answeredCount' => $answered,
            'totalCount' => $allItems->count(),
            'currentIndex' => is_int($currentIndex) ? $currentIndex : 0,
            'latestSubmission' => $latestSubmission,
            'hasDraft' => $this->openDraft($student) !== null,
        ]);
    }

    private function student(): Student
    {
        $this->authorize('create', QuestionnaireResponse::class);
        $student = auth()->user()?->student;
        abort_unless($student instanceof Student, 403);

        return $student;
    }

    private function openDraft(Student $student): ?QuestionnaireResponse
    {
        return $student->questionnaireResponses()
            ->whereNull('submitted_at')
            ->with('answers')
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, QuestionnaireItem>
     */
    private function activeItems(): Collection
    {
        return QuestionnaireItem::query()
            ->where('definition_version', $this->definitionVersion)
            ->where('section', 'academic_behavior')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function persist(Student $student, bool $submit, ?QuestionnaireScorer $scorer = null): void
    {
        $allowed = $this->activeItems()->pluck('id')->all();
        $clean = [];
        foreach ($this->answers as $itemId => $value) {
            if (! in_array((int) $itemId, $allowed, true) || $value === null || $value === '') {
                continue;
            }
            $clean[(int) $itemId] = (int) $value;
        }

        DB::transaction(function () use ($student, $submit, $scorer, $clean): void {
            $response = $this->openDraft($student);
            if ($response === null) {
                $this->authorize('create', QuestionnaireResponse::class);
                $response = $student->questionnaireResponses()->create([
                    'definition_version' => $this->definitionVersion,
                    'submitted_at' => null,
                ]);
            } else {
                $this->authorize('update', $response);
            }

            foreach ($clean as $itemId => $value) {
                $response->answers()->updateOrCreate(
                    ['questionnaire_item_id' => $itemId],
                    ['value' => $value],
                );
            }

            if ($submit) {
                $response->submitted_at = now();
                $response->construct_scores = ($scorer ?? app(QuestionnaireScorer::class))->score($response->fresh('answers.item'));
                $response->save();
            }
        });
    }

    /**
     * @param  Collection<int, QuestionnaireItem>|null  $items
     * @return list<string>
     */
    private function constructKeys(?Collection $items = null): array
    {
        $available = ($items ?? $this->activeItems())->pluck('construct')->unique()->all();

        return array_values(array_filter(
            array_keys(config('edupredict.questionnaire.constructs', [])),
            fn (string $construct): bool => in_array($construct, $available, true),
        ));
    }

    private function firstConstruct(): string
    {
        return $this->constructKeys()[0] ?? 'study_habits';
    }
}
