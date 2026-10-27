<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\PsocOccupation;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\On;

class PsocOccupationsTable extends BaseTable
{
    public string $sortField = 'id';

    public string $sortDirection = 'desc';

    public string $groupFilter = '';

    public string $activeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PsocOccupation::class);
    }

    public function updatingGroupFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }

    public function editOccupation(int $occupationId): void
    {
        $occupation = PsocOccupation::query()->findOrFail($occupationId);
        $this->authorize('update', $occupation);
        $this->dispatch('edit-occupation', occupationId: $occupationId);
    }

    public function viewOccupation(int $occupationId): void
    {
        $occupation = PsocOccupation::query()->findOrFail($occupationId);
        $this->authorize('view', $occupation);
        $this->dispatch('view-occupation', occupationId: $occupationId);
    }

    public function setOccupationActive(int $occupationId, AuditLogger $audit): void
    {
        $occupation = PsocOccupation::query()->findOrFail($occupationId);
        $this->authorize('update', $occupation);
        $next = ! $occupation->is_active;
        $occupation->update(['is_active' => $next]);
        $audit->record('psoc_saved', $occupation, [
            'psoc_code' => $occupation->psoc_code,
            'is_active' => $next,
        ]);
        $this->toast($next
            ? 'Occupation activated. It can appear in new career suggestions. Stored matches were not rewritten.'
            : 'Occupation deactivated. It will not appear in new career suggestions. Stored matches stay as they were.');
    }

    #[On('catalog-changed')]
    public function refreshCatalog(): void {}

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', PsocOccupation::class);

        return PsocOccupation::query();
    }

    protected function applyFilters(Builder $query): Builder
    {
        if ($this->groupFilter !== '') {
            $query->where('major_group', $this->groupFilter);
        }

        if ($this->activeFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->activeFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'psoc_code', 'label' => 'PSOC code', 'sortable' => true],
            ['key' => 'title', 'label' => 'Title', 'sortable' => true],
            ['key' => 'major_group', 'label' => 'Major group', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['psoc_code', 'title', 'major_group'];
    }

    protected function emptyMessage(): string
    {
        return 'No occupations match the current search or filters.';
    }

    public function render(): View
    {
        return view('livewire.tables.psoc-occupations-table', [
            ...$this->tableViewData(),
            'groups' => PsocOccupation::query()->select('major_group')->distinct()->orderBy('major_group')->pluck('major_group'),
        ]);
    }
}
