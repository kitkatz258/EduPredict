<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\Intervention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\On;

class InterventionsTable extends BaseTable
{
    public string $sortField = 'title';

    public string $riskFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Intervention::class);
    }

    public function updatingRiskFilter(): void
    {
        $this->resetPage();
    }

    public function editIntervention(int $interventionId): void
    {
        $intervention = Intervention::query()->findOrFail($interventionId);
        $this->authorize('update', $intervention);
        $this->dispatch('edit-intervention', interventionId: $interventionId);
    }

    public function viewIntervention(int $interventionId): void
    {
        $intervention = Intervention::query()->findOrFail($interventionId);
        $this->authorize('view', $intervention);
        $this->dispatch('view-intervention', interventionId: $interventionId);
    }

    #[On('catalog-changed')]
    public function refreshCatalog(): void
    {
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', Intervention::class);

        return Intervention::query();
    }

    protected function applyFilters(Builder $query): Builder
    {
        if (in_array($this->riskFilter, ['low', 'moderate', 'high'], true)) {
            $query->where('min_risk_level', $this->riskFilter);
        }

        return $query;
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
        return 'No interventions match the current search or filters.';
    }

    public function render(): View
    {
        return view('livewire.tables.interventions-table', $this->tableViewData());
    }
}
