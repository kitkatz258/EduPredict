<?php

namespace App\Livewire\Admin;

use App\Http\Requests\Admin\QuestionnaireItemRequest;
use App\Livewire\Concerns\ConfirmsAdminSaves;
use App\Livewire\Concerns\DispatchesToasts;
use App\Models\QuestionnaireItem;
use App\Services\Audit\AuditLogger;
use App\Services\Questionnaire\QuestionnaireItemOrder;
use App\Services\Questionnaire\QuestionnaireVersionCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class QuestionnaireItemForm extends Component
{
    use ConfirmsAdminSaves;
    use DispatchesToasts;

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
        $this->section = $this->defaultSection();
        $this->syncCategory();
        if ($itemId) {
            $this->loadItem($itemId);
            $this->show = true;
        }
    }

    #[On('add-questionnaire-item')]
    public function startCreate(?string $version = null): void
    {
        $this->authorize('create', QuestionnaireItem::class);
        $this->reset(['itemId', 'text', 'reverse_scored', 'sort_order', 'statusMessage']);
        $this->definition_version = is_string($version) && $version !== ''
            ? $version
            : app(QuestionnaireVersionCatalog::class)->currentCode();
        $this->section = $this->defaultSection();
        $this->construct = 'study_habits';
        $this->syncCategory();
        $this->is_active = true;
        $this->is_draft = true;
        $this->locked = ! app(QuestionnaireVersionCatalog::class)->isMutable($this->definition_version);
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

    public function updatedDefinitionVersion(): void
    {
        if (! $this->allowsReverseScoring()) {
            $this->reverse_scored = false;
        }
        if ($this->itemId === null) {
            $this->locked = ! app(QuestionnaireVersionCatalog::class)->isMutable($this->definition_version);
        }
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
        if (! $this->allowsReverseScoring($validated['definition_version'])) {
            $this->reverse_scored = false;
        }

        $catalog = app(QuestionnaireVersionCatalog::class);
        $isEdit = $this->itemId !== null;
        $item = null;
        if ($isEdit) {
            $item = QuestionnaireItem::query()->findOrFail($this->itemId);
            $this->authorize('update', $item);
            if ($item->definition_version !== $validated['definition_version']) {
                $this->addError('item', 'A question stays on the version it belongs to. Create a new version instead.');

                return;
            }
            $block = $catalog->mutationBlock($item);
            if ($block !== null) {
                $this->addError('item', $block);

                return;
            }
        } elseif (! $catalog->isMutable($validated['definition_version'])) {
            $this->addError('item', 'Published questionnaires cannot be edited. Create an editable copy.');

            return;
        }

        if (! $this->consumeAdminSaveConfirmation($isEdit)) {
            return;
        }

        $payload = [
            'section' => $validated['section'],
            'definition_version' => $validated['definition_version'],
            'construct' => $validated['construct'],
            'text' => $validated['text'],
            'reverse_scored' => $this->allowsReverseScoring($validated['definition_version']) && $this->reverse_scored,
            'is_active' => (bool) $this->is_active,
            'is_draft' => true,
        ];

        $order = app(QuestionnaireItemOrder::class);
        if ($isEdit) {
            DB::transaction(function () use ($item, $payload, $order): void {
                if ($item->section !== $payload['section']) {
                    $order->relocate($item, $payload['section']);
                    $item->refresh();
                }
                $item->update($payload);
            });
        } else {
            DB::transaction(function () use (&$item, $payload, $order): void {
                $payload['sort_order'] = $order->nextPosition($payload['definition_version'], $payload['section']);
                $item = QuestionnaireItem::query()->create($payload);
            });
        }

        $audit->record('questionnaire_item_saved', $item, [
            'construct' => $item->construct,
            'is_active' => $item->is_active,
        ]);
        $this->show = false;
        $this->locked = false;
        $this->reset(['itemId', 'text', 'reverse_scored', 'sort_order', 'statusMessage']);
        $this->resetValidation();
        $this->dispatch('catalog-changed');
        $this->notifyAdminSaveSuccess($isEdit);
    }

    public function render(): View
    {
        $this->authorize('create', QuestionnaireItem::class);

        $version = app(QuestionnaireVersionCatalog::class)->find($this->definition_version);

        return view('livewire.admin.questionnaire-item-form', [
            'categories' => $this->categoryOptions(),
            'sections' => $this->sectionChoices(),
            'allowsReverse' => $this->allowsReverseScoring(),
            'editingLabel' => ($version?->name ?? $this->definition_version).' · '.($version?->isMutable() ? 'Draft' : ($version?->stateLabel() ?? 'Draft')),
            'positionLabel' => $this->itemId
                ? 'Position in section: '.$this->sort_order.'. Use Move up or Move down in the list to change it.'
                : 'Position in section is assigned automatically as the next open position when you save.',
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

    private function defaultSection(): string
    {
        $sections = config('edupredict.questionnaire.versions.'.$this->definition_version.'.sections', []);
        if (is_array($sections) && isset($sections[0]) && is_string($sections[0])) {
            return $sections[0];
        }

        return 'academic_behavior';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sectionChoices(): array
    {
        $allowed = app(QuestionnaireVersionCatalog::class)->sections($this->definition_version);
        $sections = config('edupredict.questionnaire.sections', []);
        if ($allowed === [] || ! is_array($sections)) {
            return is_array($sections) ? $sections : [];
        }

        $choices = [];
        foreach ($allowed as $key) {
            if (isset($sections[$key]) && is_array($sections[$key])) {
                $choices[$key] = $sections[$key];
            }
        }

        if ($this->section !== '' && ! array_key_exists($this->section, $choices) && isset($sections[$this->section]) && is_array($sections[$this->section])) {
            $choices = [$this->section => $sections[$this->section]] + $choices;
        }

        return $choices;
    }

    private function allowsReverseScoring(?string $version = null): bool
    {
        $version ??= $this->definition_version;

        return app(QuestionnaireVersionCatalog::class)->allowsReverseScoring($version);
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
        $this->locked = app(QuestionnaireVersionCatalog::class)->mutationBlock($item) !== null;
    }
}
