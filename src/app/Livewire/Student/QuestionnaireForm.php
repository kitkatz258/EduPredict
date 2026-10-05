<?php

namespace App\Livewire\Student;

use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\Student;
use App\Services\Questionnaire\QuestionnaireScorer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class QuestionnaireForm extends Component
{
    /** @var array<int|string, int|string|null> */
    public array $answers = [];

    public string $statusMessage = '';

    public function mount(): void
    {
        $student = $this->student();
        $draft = $this->openDraft($student);
        if ($draft !== null) {
            $this->authorize('view', $draft);
            foreach ($draft->answers as $answer) {
                $this->answers[$answer->questionnaire_item_id] = $answer->value;
            }
        }
    }

    public function saveDraft(): void
    {
        $student = $this->student();
        $this->persist($student, submit: false);
        $this->statusMessage = 'Questionnaire draft saved. You can finish it later.';
    }

    public function submit(QuestionnaireScorer $scorer): void
    {
        $student = $this->student();
        if ($this->openDraft($student) === null && $student->questionnaireResponses()->whereNotNull('submitted_at')->exists()) {
            $this->statusMessage = 'Use Retake to start another response. Earlier submissions are kept.';

            return;
        }

        $items = $this->activeItems();
        $rules = [];
        foreach ($items as $item) {
            $rules['answers.'.$item->id] = ['required', 'integer', 'between:1,5'];
        }
        $this->validate($rules);

        $this->persist($student, submit: true, scorer: $scorer);
        $this->statusMessage = 'Questionnaire submitted. Your construct scores are saved with this response.';
    }

    public function retake(): void
    {
        $student = $this->student();
        $this->authorize('create', QuestionnaireResponse::class);

        if ($this->openDraft($student) !== null) {
            return;
        }

        $student->questionnaireResponses()->create([
            'submitted_at' => null,
            'construct_scores' => null,
        ]);
        $this->answers = [];
        $this->statusMessage = 'A new questionnaire response is ready. Your earlier submissions stay in the history.';
    }

    public function render(): View
    {
        $student = $this->student();
        $items = $this->activeItems()->groupBy('construct');
        $history = $student->questionnaireResponses()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('livewire.student.questionnaire-form', [
            'groupedItems' => $items,
            'constructs' => config('edupredict.questionnaire.constructs', []),
            'likert' => config('edupredict.questionnaire.likert', []),
            'history' => $history,
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
     * @return \Illuminate\Database\Eloquent\Collection<int, QuestionnaireItem>
     */
    private function activeItems()
    {
        return QuestionnaireItem::query()
            ->where('is_active', true)
            ->orderBy('construct')
            ->orderBy('sort_order')
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
}
