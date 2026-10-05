<?php

namespace App\Livewire\Tables;

use App\Models\QuestionnaireItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class QuestionnaireItemsTable extends BaseTable
{
    public string $sortField = 'sort_order';

    public function mount(): void
    {
        $this->authorize('viewAny', QuestionnaireItem::class);
    }

    public function toggleActive(int $itemId): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        $item->update(['is_active' => ! $item->is_active]);
    }

    public function toggleDraft(int $itemId): void
    {
        $item = QuestionnaireItem::query()->findOrFail($itemId);
        $this->authorize('update', $item);
        $item->update(['is_draft' => ! $item->is_draft]);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', QuestionnaireItem::class);

        return QuestionnaireItem::query();
    }

    protected function columns(): array
    {
        return [
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
        return 'No questionnaire items yet.';
    }

    public function render(): View
    {
        return view('livewire.tables.questionnaire-items-table', $this->tableViewData());
    }
}
