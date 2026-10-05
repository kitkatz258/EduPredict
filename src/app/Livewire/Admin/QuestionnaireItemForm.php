<?php

namespace App\Livewire\Admin;

use App\Http\Requests\Admin\QuestionnaireItemRequest;
use App\Models\QuestionnaireItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QuestionnaireItemForm extends Component
{
    public ?int $itemId = null;

    public string $construct = 'study_habits';

    public string $text = '';

    public bool $reverse_scored = false;

    public bool $is_active = true;

    public bool $is_draft = true;

    public ?int $sort_order = 0;

    public string $statusMessage = '';

    public function mount(?int $itemId = null): void
    {
        $this->authorize('create', QuestionnaireItem::class);
        if ($itemId) {
            $this->loadItem($itemId);
        }
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('create', QuestionnaireItem::class);
        $validated = $this->validate(QuestionnaireItemRequest::fieldRules());
        $payload = [
            'construct' => $validated['construct'],
            'text' => $validated['text'],
            'reverse_scored' => (bool) $this->reverse_scored,
            'is_active' => (bool) $this->is_active,
            'is_draft' => (bool) $this->is_draft,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];

        if ($this->itemId) {
            $item = QuestionnaireItem::query()->findOrFail($this->itemId);
            $this->authorize('update', $item);
            $item->update($payload);
            $this->statusMessage = 'Questionnaire item updated.';
        } else {
            $item = QuestionnaireItem::query()->create($payload);
            $this->itemId = $item->id;
            $this->statusMessage = 'Questionnaire item added.';
        }

        $audit->record('questionnaire_item_saved', $item, [
            'construct' => $item->construct,
            'is_active' => $item->is_active,
        ]);
    }

    public function render(): View
    {
        $this->authorize('create', QuestionnaireItem::class);

        return view('livewire.admin.questionnaire-item-form', [
            'constructs' => config('edupredict.questionnaire.constructs', []),
        ]);
    }

    private function loadItem(int $itemId): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        $this->itemId = $item->id;
        $this->construct = $item->construct;
        $this->text = $item->text;
        $this->reverse_scored = $item->reverse_scored;
        $this->is_active = $item->is_active;
        $this->is_draft = $item->is_draft;
        $this->sort_order = $item->sort_order;
    }
}
