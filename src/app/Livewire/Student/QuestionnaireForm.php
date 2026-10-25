<?php

namespace App\Livewire\Student;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\Student;
use App\Services\Questionnaire\QuestionnaireScorer;
use App\Services\Questionnaire\SectionScorePresenter;
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

    public string $definitionVersion = 'ucc-panel-approved-v1';

    /** Selected legacy construct. Ignored while the approved instrument is open. */
    public string $construct = 'study_habits';

    /** Selected approved section. */
    public string $section = 'academic';

    public bool $wizardOpen = false;

    public bool $reviewing = false;

    public function mount(): void
    {
        $student = $this->student();
        $this->bindVersion($student);
        $draft = $this->openDraft($student);

        if ($draft !== null) {
            $this->authorize('view', $draft);
            foreach ($draft->answers as $answer) {
                $value = $answer->responseValue();
                if ($value !== null) {
                    $this->answers[$answer->questionnaire_item_id] = $value;
                }
            }
        }

        $this->construct = $this->firstConstruct();
        $this->section = $this->firstIncompleteSection();
        $submitted = $student->questionnaireResponses()->whereNotNull('submitted_at')->exists();
        $this->wizardOpen = $draft !== null || ! $submitted;
    }

    public function openWizard(): void
    {
        $this->student();
        $this->wizardOpen = true;
    }

    public function closeWizard(): void
    {
        if ($this->unsavedChanges()) {
            $this->dispatch('confirm-discard-questionnaire');

            return;
        }

        $this->wizardOpen = false;
        $this->reviewing = false;
    }

    public function discardUnsavedAndClose(): void
    {
        $student = $this->student();
        $this->answers = $this->savedAnswerMap($student);
        $this->wizardOpen = false;
        $this->reviewing = false;
        $this->resetValidation();
        $this->statusMessage = 'Unsaved answers were discarded. The saved draft is unchanged.';
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
        $this->usesConstructNavigation() ? $this->nextConstruct() : $this->nextSection();
        $this->toast('Questionnaire progress saved.');
        $this->dispatch('questionnaire-section-changed');
    }

    public function previousPart(): void
    {
        if ($this->reviewing) {
            $this->reviewing = false;
            $this->resetValidation();
            $this->dispatch('questionnaire-section-changed');

            return;
        }

        $this->persist($this->student(), submit: false);
        $this->usesConstructNavigation() ? $this->previousConstruct() : $this->previousSection();
        $this->dispatch('questionnaire-section-changed');
    }

    public function openReview(): void
    {
        $this->persist($this->student(), submit: false);
        $this->reviewing = true;
        $this->resetValidation();
        $this->statusMessage = 'Saved. Review the sections, then submit.';
        $this->dispatch('questionnaire-section-changed');
    }

    public function selectPart(string $part): void
    {
        if (! in_array($part, $this->partKeys(), true)) {
            return;
        }

        $this->persist($this->student(), submit: false);
        $this->reviewing = false;
        if ($this->usesConstructNavigation()) {
            $this->construct = $part;
        } else {
            $this->section = $part;
        }
        $this->resetValidation();
        $this->dispatch('questionnaire-section-changed');
    }

    public function selectConstruct(string $construct): void
    {
        $this->selectPart($construct);
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

    public function previousSection(): void
    {
        $keys = $this->sectionKeys();
        $index = array_search($this->section, $keys, true);
        if (is_int($index) && $index > 0) {
            $this->section = $keys[$index - 1];
            $this->resetValidation();
        }
    }

    public function nextSection(): void
    {
        $keys = $this->sectionKeys();
        $index = array_search($this->section, $keys, true);
        if (is_int($index) && isset($keys[$index + 1])) {
            $this->section = $keys[$index + 1];
            $this->resetValidation();
        }
    }

    public function submit(QuestionnaireScorer $scorer): void
    {
        $student = $this->student();
        $this->bindVersion($student);
        if ($this->openDraft($student) === null && $student->questionnaireResponses()->whereNotNull('submitted_at')->exists()) {
            $this->statusMessage = 'Start a new response before answering again. Earlier submissions are kept.';

            return;
        }

        $items = $this->activeItems();
        $missing = $items->first(fn (QuestionnaireItem $item): bool => ! isset($this->answers[$item->id]) || $this->answers[$item->id] === '');
        if ($missing !== null) {
            $this->reviewing = false;
            $this->focusItem($missing);
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
        $this->wizardOpen = false;
        $this->reviewing = false;
        $this->statusMessage = 'Thank you for answering this questionnaire. Section results are saved with this submission.';
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

        $this->definitionVersion = (string) config('edupredict.questionnaire.current_version', 'ucc-panel-approved-v1');
        $student->questionnaireResponses()->create([
            'definition_version' => $this->definitionVersion,
            'submitted_at' => null,
            'construct_scores' => null,
        ]);
        $this->answers = [];
        $this->construct = $this->firstConstruct();
        $this->section = $this->firstIncompleteSection();
        $this->reviewing = false;
        $this->wizardOpen = true;
        $this->statusMessage = 'A new questionnaire response is ready. Earlier submissions stay on their original versions.';
        $this->toast($this->statusMessage);
    }

    /**
     * An unfinished draft is not a submission. Discarding it does not change
     * submitted responses or prediction history.
     */
    public function discardDraft(): void
    {
        $student = $this->student();
        $draft = $this->openDraft($student);
        if ($draft === null) {
            return;
        }

        $this->authorize('update', $draft);
        abort_if($draft->submitted_at !== null, 403);
        $draft->answers()->delete();
        $draft->delete();
        $this->definitionVersion = (string) config('edupredict.questionnaire.current_version');
        $this->answers = [];
        $this->construct = $this->firstConstruct();
        $this->section = $this->firstIncompleteSection();
        $this->reviewing = false;
        $this->wizardOpen = false;
        $this->statusMessage = 'The unfinished draft was discarded. Submitted responses were kept.';
        $this->toast($this->statusMessage);
    }

    public function render(SectionScorePresenter $presenter): View
    {
        $student = $this->student();
        $this->bindVersion($student);
        $allItems = $this->activeItems();
        $keys = $this->partKeys($allItems);
        $current = $this->currentPart($keys);
        $saved = $this->savedAnswerMap($student);
        $answered = 0;
        foreach ($allItems as $item) {
            if (isset($saved[$item->id])) {
                $answered++;
            }
        }
        $currentIndex = array_search($current, $keys, true);

        $latestSubmission = $student->questionnaireResponses()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $partLabels = [];
        foreach ($keys as $key) {
            $partLabels[$key] = $this->partLabel($key);
        }

        return view('livewire.student.questionnaire-form', [
            'items' => $allItems->where($this->usesConstructNavigation() ? 'construct' : 'section', $current)->values(),
            'allItems' => $allItems,
            'partKeys' => $keys,
            'partLabels' => $partLabels,
            'partHeadings' => $this->partHeadings(),
            'currentPart' => $current,
            'navigation' => $this->usesConstructNavigation() ? 'construct' : 'section',
            'likert' => $this->likertChoices(),
            'answeredCount' => $this->answeredCount($allItems),
            'unsaved' => $this->unsavedChanges($student),
            'version' => config('edupredict.questionnaire.versions.'.$this->definitionVersion, []),
            'savedCount' => $answered,
            'totalCount' => $allItems->count(),
            'currentIndex' => is_int($currentIndex) ? $currentIndex : 0,
            'latestSubmission' => $latestSubmission,
            'hasDraft' => $this->openDraft($student) !== null,
            'saved' => $saved,
            'sectionScores' => $presenter->rows(is_array($latestSubmission?->construct_scores) ? $latestSubmission->construct_scores : null),
            'legacyDraft' => $this->usesConstructNavigation()
                && $this->definitionVersion !== (string) config('edupredict.questionnaire.current_version'),
        ]);
    }

    private function student(): Student
    {
        $this->authorize('create', QuestionnaireResponse::class);
        $student = auth()->user()?->student;
        abort_unless($student instanceof Student, 403);

        return $student;
    }

    private function bindVersion(Student $student): void
    {
        $draft = $this->openDraft($student);
        $version = $draft?->definition_version
            ?? (string) config('edupredict.questionnaire.current_version', 'ucc-panel-approved-v1');
        $allowed = array_keys(config('edupredict.questionnaire.versions', []));
        if (! in_array($version, $allowed, true)) {
            $version = (string) config('edupredict.questionnaire.current_version');
        }

        $this->definitionVersion = $version;
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
        $sections = config('edupredict.questionnaire.versions.'.$this->definitionVersion.'.sections', []);
        $query = QuestionnaireItem::query()
            ->where('definition_version', $this->definitionVersion)
            ->where('is_active', true);

        if (is_array($sections) && $sections !== []) {
            $query->whereIn('section', $sections);
        }

        $items = $query->orderBy('sort_order')->orderBy('id')->get();
        if (! is_array($sections) || $sections === []) {
            return $items;
        }

        $order = array_flip($sections);

        return $items->sortBy(function (QuestionnaireItem $item) use ($order): string {
            $sectionOrder = $order[$item->section] ?? 99;

            return sprintf('%03d-%05d-%05d', $sectionOrder, $item->sort_order, $item->id);
        })->values();
    }

    /**
     * @return array<int, int>
     */
    private function savedAnswerMap(Student $student): array
    {
        $draft = $this->openDraft($student);
        if ($draft === null) {
            return [];
        }

        $saved = [];
        foreach ($draft->answers as $answer) {
            $value = $answer->responseValue();
            if ($value !== null) {
                $saved[$answer->questionnaire_item_id] = $value;
            }
        }

        return $saved;
    }

    private function persist(Student $student, bool $submit, ?QuestionnaireScorer $scorer = null): void
    {
        $this->bindVersion($student);
        $allowed = $this->activeItems()->keyBy('id');
        $clean = [];
        foreach ($this->answers as $itemId => $value) {
            if (! $allowed->has((int) $itemId) || $value === null || $value === '') {
                continue;
            }
            $clean[(int) $itemId] = (int) $value;
        }

        DB::transaction(function () use ($student, $submit, $scorer, $clean, $allowed): void {
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
                /** @var QuestionnaireItem $item */
                $item = $allowed->get($itemId);
                $response->answers()->updateOrCreate(
                    ['questionnaire_item_id' => $itemId],
                    $item->isSensitive()
                        ? ['value' => 0, 'protected_value' => (string) $value]
                        : ['value' => $value, 'protected_value' => null],
                );
            }

            if ($submit) {
                $response->submitted_at = now();
                $response->construct_scores = ($scorer ?? app(QuestionnaireScorer::class))->score($response->fresh('answers.item'));
                $response->save();
            }
        });
    }

    private function usesConstructNavigation(): bool
    {
        return config('edupredict.questionnaire.versions.'.$this->definitionVersion.'.navigation') === 'construct';
    }

    /**
     * @param  Collection<int, QuestionnaireItem>|null  $items
     * @return list<string>
     */
    private function partKeys(?Collection $items = null): array
    {
        return $this->usesConstructNavigation()
            ? $this->constructKeys($items)
            : $this->sectionKeys($items);
    }

    /**
     * @param  list<string>  $keys
     */
    private function currentPart(array $keys): string
    {
        $current = $this->usesConstructNavigation() ? $this->construct : $this->section;
        if (! in_array($current, $keys, true)) {
            $current = $keys[0] ?? ($this->usesConstructNavigation() ? 'study_habits' : 'academic');
            if ($this->usesConstructNavigation()) {
                $this->construct = $current;
            } else {
                $this->section = $current;
            }
        }

        return $current;
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

    /**
     * @param  Collection<int, QuestionnaireItem>|null  $items
     * @return list<string>
     */
    private function sectionKeys(?Collection $items = null): array
    {
        $configured = config('edupredict.questionnaire.versions.'.$this->definitionVersion.'.sections', []);
        $available = ($items ?? $this->activeItems())->pluck('section')->unique()->all();
        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            $configured,
            fn (mixed $section): bool => is_string($section) && in_array($section, $available, true),
        ));
    }

    private function partLabel(string $key): string
    {
        if ($this->usesConstructNavigation()) {
            return (string) config('edupredict.questionnaire.constructs.'.$key, $key);
        }

        return (string) config('edupredict.questionnaire.sections.'.$key.'.short', config('edupredict.questionnaire.sections.'.$key.'.label', $key));
    }

    /**
     * @return array<string, string>
     */
    private function partHeadings(): array
    {
        $headings = [];
        foreach ($this->partKeys() as $key) {
            $headings[$key] = $this->usesConstructNavigation()
                ? (string) config('edupredict.questionnaire.constructs.'.$key, $key)
                : (string) config('edupredict.questionnaire.sections.'.$key.'.heading', config('edupredict.questionnaire.sections.'.$key.'.label', $key));
        }

        return $headings;
    }

    private function firstConstruct(): string
    {
        return $this->constructKeys()[0] ?? 'study_habits';
    }

    private function firstIncompleteSection(): string
    {
        $items = $this->activeItems();
        $saved = [];
        foreach ($this->answers as $itemId => $value) {
            if ($value !== null && $value !== '') {
                $saved[(int) $itemId] = true;
            }
        }

        foreach ($this->sectionKeys($items) as $section) {
            $sectionItems = $items->where('section', $section);
            $complete = $sectionItems->isNotEmpty() && $sectionItems->every(
                fn (QuestionnaireItem $item): bool => isset($saved[$item->id]),
            );
            if (! $complete) {
                return $section;
            }
        }

        $keys = $this->sectionKeys($items);

        return $keys === [] ? 'academic' : $keys[array_key_last($keys)];
    }

    /**
     * Display order is agreement-first. Stored values stay 5 down to 1.
     *
     * @return array<int, string>
     */
    private function likertChoices(): array
    {
        $labels = config('edupredict.questionnaire.likert', []);
        $choices = [];
        foreach ([5, 4, 3, 2, 1] as $value) {
            if (isset($labels[$value])) {
                $choices[$value] = $labels[$value];
            }
        }
        foreach ($labels as $value => $label) {
            if (! array_key_exists((int) $value, $choices)) {
                $choices[(int) $value] = $label;
            }
        }

        return $choices;
    }

    /**
     * @param  Collection<int, QuestionnaireItem>  $items
     */
    private function answeredCount(Collection $items): int
    {
        $answered = 0;
        foreach ($items as $item) {
            $value = $this->answers[$item->id] ?? $this->answers[(string) $item->id] ?? null;
            if ($value !== null && $value !== '') {
                $answered++;
            }
        }

        return $answered;
    }

    private function unsavedChanges(?Student $student = null): bool
    {
        $student ??= $this->student();
        $saved = $this->savedAnswerMap($student);
        $current = [];
        foreach ($this->activeItems() as $item) {
            $value = $this->answers[$item->id] ?? $this->answers[(string) $item->id] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $current[$item->id] = (int) $value;
        }
        ksort($saved);
        ksort($current);

        return $current !== $saved;
    }

    private function focusItem(QuestionnaireItem $item): void
    {
        if ($this->usesConstructNavigation()) {
            $this->construct = $item->construct;
        } else {
            $this->section = $item->section;
        }
    }
}
