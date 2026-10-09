<?php

namespace App\Livewire\Tables;

use App\Models\QuestionnaireItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\On;

class QuestionnaireItemsTable extends BaseTable
{
    public string $sortField = 'sort_order';

    public string $sectionFilter = '';

    public string $versionFilter = '';

    public string $activeFilter = '';

    public string $draftFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', QuestionnaireItem::class);
    }

    public function updatingSectionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingVersionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDraftFilter(): void
    {
        $this->resetPage();
    }

    public function editItem(int $itemId): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        $this->dispatch('edit-questionnaire-item', itemId: $itemId);
    }

    #[On('catalog-changed')]
    public function refreshCatalog(): void
    {
    }

    public function toggleActive(int $itemId, AuditLogger $audit): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        if ($this->locked($item)) {
            return;
        }
        $item->update(['is_active' => ! $item->is_active]);
        $audit->record('questionnaire_item_saved', $item, ['is_active' => $item->is_active]);
    }

    public function toggleDraft(int $itemId, AuditLogger $audit): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        if ($this->locked($item)) {
            return;
        }
        $item->update(['is_draft' => ! $item->is_draft]);
        $audit->record('questionnaire_item_saved', $item, ['is_draft' => $item->is_draft]);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', QuestionnaireItem::class);

        return QuestionnaireItem::query();
    }

    protected function applyFilters(Builder $query): Builder
    {
        $sections = array_keys(config('edupredict.questionnaire.sections', []));
        $versions = array_keys(config('edupredict.questionnaire.versions', []));

        if (in_array($this->sectionFilter, $sections, true)) {
            $query->where('section', $this->sectionFilter);
        }

        if (in_array($this->versionFilter, $versions, true)) {
            $query->where('definition_version', $this->versionFilter);
        }

        if ($this->activeFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->activeFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($this->draftFilter === 'draft') {
            $query->where('is_draft', true);
        } elseif ($this->draftFilter === 'published') {
            $query->where('is_draft', false);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'definition_version', 'label' => 'Version', 'sortable' => true],
            ['key' => 'section', 'label' => 'Section', 'sortable' => true],
            ['key' => 'sort_order', 'label' => 'Order', 'sortable' => true],
            ['key' => 'construct', 'label' => 'Construct', 'sortable' => true],
            ['key' => 'text', 'label' => 'Item', 'sortable' => true],
            ['key' => 'reverse_scored', 'label' => 'Reverse', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Active', 'sortable' => true],
            ['key' => 'is_draft', 'label' => 'Draft', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['text', 'construct'];
    }

    protected function emptyMessage(): string
    {
        return 'No questionnaire items match the current search or filters.';
    }

    public function render(): View
    {
        return view('livewire.tables.questionnaire-items-table', [
            ...$this->tableViewData(),
            'sections' => config('edupredict.questionnaire.sections', []),
            'versions' => config('edupredict.questionnaire.versions', []),
        ]);
    }

    private function locked(QuestionnaireItem $item): bool
    {
        if (! $item->answers()->exists()) {
            return false;
        }

        $this->toast('This used definition is locked. Create a new version instead.', 'error');

        return true;
    }
}
