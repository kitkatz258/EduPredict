<?php

namespace App\Livewire\Admin;

use App\Http\Requests\Admin\QuestionnaireItemRequest;
use App\Models\QuestionnaireItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class QuestionnaireItemForm extends Component
{
    public bool $show = false;

    public bool $locked = false;

    public ?int $itemId = null;

    public string $section = 'academic_behavior';

    public string $definition_version = 'draft-v1';

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
        $this->definition_version = (string) config('edupredict.questionnaire.current_version', 'draft-v1');
        if ($itemId) {
            $this->loadItem($itemId);
            $this->show = true;
        }
    }

    #[On('add-questionnaire-item')]
    public function startCreate(): void
    {
        $this->authorize('create', QuestionnaireItem::class);
        $this->reset(['itemId', 'text', 'reverse_scored', 'sort_order', 'statusMessage']);
        $this->section = 'academic_behavior';
        $this->construct = 'study_habits';
        $this->definition_version = (string) config('edupredict.questionnaire.current_version', 'draft-v1');
        $this->is_active = true;
        $this->is_draft = true;
        $this->locked = false;
        $this->resetValidation();
        $this->show = true;
    }

    #[On('edit-questionnaire-item')]
    public function startEdit(int $itemId): void
    {
        $this->resetValidation();
        $this->statusMessage = '';
        $this->loadItem($itemId);
        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
    }

    public function updatedSection(): void
    {
        $this->syncCategory();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('create', QuestionnaireItem::class);
        $validated = $this->validate(QuestionnaireItemRequest::fieldRules(), [], [
            'construct' => 'category',
        ]);
        $sectionConstructs = config('edupredict.questionnaire.sections.'.$validated['section'].'.constructs', []);
        if (! in_array($validated['construct'], $sectionConstructs, true)) {
            $this->addError('construct', 'Choose a category configured for this questionnaire section.');

            return;
        }
        $payload = [
            'section' => $validated['section'],
            'definition_version' => $validated['definition_version'],
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
            if ($item->answers()->exists()) {
                $this->addError('item', 'This definition has student answers and is locked. Create a new version instead.');

                return;
            }
            $item->update($payload);
            $this->statusMessage = 'Questionnaire item updated.';
        } else {
            $item = QuestionnaireItem::query()->create($payload);
            $this->itemId = $item->id;
            $this->statusMessage = 'Questionnaire item added.';
        }

        $this->show = true;
        $this->locked = $item->answers()->exists();
        $audit->record('questionnaire_item_saved', $item, [
            'construct' => $item->construct,
            'is_active' => $item->is_active,
        ]);
        $this->dispatch('catalog-changed');
    }

    public function render(): View
    {
        $this->authorize('create', QuestionnaireItem::class);

        return view('livewire.admin.questionnaire-item-form', [
            'categories' => $this->categoryOptions(),
            'sections' => config('edupredict.questionnaire.sections', []),
            'versions' => config('edupredict.questionnaire.versions', []),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        $options = $this->categoriesFor($this->section);
        if ($this->construct !== '' && ! array_key_exists($this->construct, $options)) {
            $known = config('edupredict.questionnaire.constructs.'.$this->construct);
            $options = [$this->construct => is_string($known) && $known !== '' ? $known : $this->construct] + $options;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function categoriesFor(string $section): array
    {
        $allowed = config('edupredict.questionnaire.sections.'.$section.'.constructs', []);
        $labels = config('edupredict.questionnaire.constructs', []);
        $options = [];
        foreach ($allowed as $key) {
            if (! is_string($key) || ! isset($labels[$key]) || ! is_string($labels[$key])) {
                continue;
            }
            $options[$key] = $labels[$key];
        }

        return $options;
    }

    private function syncCategory(): void
    {
        $allowed = $this->categoriesFor($this->section);
        if (! array_key_exists($this->construct, $allowed)) {
            $this->construct = (string) (array_key_first($allowed) ?? '');
        }
    }

    private function loadItem(int $itemId): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        $this->itemId = $item->id;
        $this->section = $item->section;
        $this->definition_version = $item->definition_version;
        $this->construct = $item->construct;
        $this->text = $item->text;
        $this->reverse_scored = $item->reverse_scored;
        $this->is_active = $item->is_active;
        $this->is_draft = $item->is_draft;
        $this->sort_order = $item->sort_order;
        $this->locked = $item->answers()->exists();
    }
}
