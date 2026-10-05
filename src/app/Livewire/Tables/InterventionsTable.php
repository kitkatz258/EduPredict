<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\Intervention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class InterventionsTable extends BaseTable
{
    public string $sortField = 'title';

    public function mount(): void
    {
        $this->authorize('viewAny', Intervention::class);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', Intervention::class);

        return Intervention::query();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'sortable' => true],
            ['key' => 'title', 'label' => 'Intervention', 'sortable' => true],
            ['key' => 'min_risk_level', 'label' => 'Minimum risk', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['code', 'title', 'description'];
    }

    protected function emptyMessage(): string
    {
        return 'No interventions match the current search.';
    }

    public function render(): View
    {
        return view('livewire.tables.interventions-table', $this->tableViewData());
    }
}
