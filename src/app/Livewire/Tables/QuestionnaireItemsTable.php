<?php

namespace App\Livewire\Tables;

use App\Models\QuestionnaireItem;
use App\Services\Audit\AuditLogger;
use App\Services\Questionnaire\QuestionnaireItemOrder;
use App\Services\Questionnaire\QuestionnaireVersionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class QuestionnaireItemsTable extends BaseTable
{
    #[Url(except: 100)]
    public int $perPage = 100;

    /** @var list<int> */
    protected array $perPageOptions = [25, 50, 100];

    public string $sortField = 'sort_order';

    public string $sectionFilter = '';

    public string $versionFilter = '';

    public string $activeFilter = '';

    public string $draftFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', QuestionnaireItem::class);
        if ($this->versionFilter === '') {
            $stored = session('questionnaire.admin_version');
            $catalog = app(QuestionnaireVersionCatalog::class);
            $this->versionFilter = is_string($stored) && $catalog->find($stored) !== null
                ? $stored
                : $catalog->currentCode();
        }
    }

    #[On('questionnaire-version-selected')]
    public function onVersionSelected(string $code): void
    {
        if (app(QuestionnaireVersionCatalog::class)->find($code) === null) {
            return;
        }

        $this->versionFilter = $code;
        $this->resetPage();
    }

    public function addItem(): void
    {
        $this->authorize('create', QuestionnaireItem::class);
        if (! app(QuestionnaireVersionCatalog::class)->isMutable($this->versionFilter)) {
            $this->toast('Published questionnaires cannot be edited. Create an editable copy.', 'error');

            return;
        }

        $this->dispatch('add-questionnaire-item', version: $this->versionFilter);
    }

    public function moveUp(int $itemId): void
    {
        $this->move($itemId, -1);
    }

    public function moveDown(int $itemId): void
    {
        $this->move($itemId, 1);
    }

    public function removeItem(int $itemId, AuditLogger $audit): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        $block = app(QuestionnaireVersionCatalog::class)->mutationBlock($item);
        if ($block !== null) {
            $this->toast($block, 'error');

            return;
        }

        DB::transaction(function () use ($item, $audit): void {
            $version = $item->definition_version;
            $section = $item->section;
            $id = $item->id;
            $item->delete();
            app(QuestionnaireItemOrder::class)->compact($version, $section);
            $audit->record('questionnaire_item_removed', null, [
                'questionnaire_item_id' => $id,
                'definition_version' => $version,
                'section' => $section,
            ]);
        });
        $this->dispatch('catalog-changed');
        $this->toast('Question removed from this draft.');
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
    public function refreshCatalog(): void {}

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

    public function toggleDraft(int $itemId): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        $this->toast('Publish the questionnaire from Review & publish. One question cannot be published on its own.', 'error');
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->applyFilters($this->applySearch($this->baseQuery()));
        $allowed = collect($this->columns())->pluck('key')->all();
        $field = in_array($this->sortField, $allowed, true) ? $this->sortField : 'sort_order';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        if ($field === 'sort_order') {
            $sections = app(QuestionnaireVersionCatalog::class)->sections($this->versionFilter);
            if ($sections !== []) {
                $sql = 'CASE section ';
                $bindings = [];
                foreach ($sections as $index => $section) {
                    $sql .= 'WHEN ? THEN '.(int) $index.' ';
                    $bindings[] = $section;
                }
                $sql .= 'ELSE 99 END';
                $query->orderByRaw($sql, $bindings);
            }

            return $query->orderBy('sort_order', $direction)->orderBy('id');
        }

        $query->orderBy($field, $direction);
        if ($field !== 'id') {
            $query->orderBy('id');
        }

        return $query;
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', QuestionnaireItem::class);

        return QuestionnaireItem::query();
    }

    protected function applyFilters(Builder $query): Builder
    {
        $sections = array_keys(config('edupredict.questionnaire.sections', []));

        if (in_array($this->sectionFilter, $sections, true)) {
            $query->where('section', $this->sectionFilter);
        }

        if ($this->versionFilter !== '') {
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
            ['key' => 'section', 'label' => 'Section', 'sortable' => true],
            ['key' => 'sort_order', 'label' => 'Position', 'sortable' => true],
            ['key' => 'construct', 'label' => 'Category', 'sortable' => true],
            ['key' => 'text', 'label' => 'Item', 'sortable' => true],
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
        $positions = [];
        $grouped = QuestionnaireItem::query()
            ->where('definition_version', $this->versionFilter)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('section');
        foreach ($grouped as $group) {
            $count = $group->count();
            foreach ($group->values() as $index => $item) {
                $positions[$item->id] = ['index' => $index, 'count' => $count];
            }
        }

        return view('livewire.tables.questionnaire-items-table', [
            ...$this->tableViewData(),
            'sections' => config('edupredict.questionnaire.sections', []),
            'mutable' => app(QuestionnaireVersionCatalog::class)->isMutable($this->versionFilter),
            'positions' => $positions,
        ]);
    }

    private function move(int $itemId, int $direction): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        if ($this->locked($item)) {
            return;
        }

        $before = $item->sort_order;
        app(QuestionnaireItemOrder::class)->move($item, $direction);
        $item->refresh();
        if ((int) $item->sort_order !== (int) $before) {
            app(AuditLogger::class)->record('questionnaire_item_reordered', $item, [
                'definition_version' => $item->definition_version,
                'section' => $item->section,
                'sort_order' => $item->sort_order,
            ]);
        }
        $this->dispatch('catalog-changed');
    }

    private function locked(QuestionnaireItem $item): bool
    {
        $block = app(QuestionnaireVersionCatalog::class)->mutationBlock($item);
        if ($block === null) {
            return false;
        }

        $this->toast($block, 'error');

        return true;
    }
}
