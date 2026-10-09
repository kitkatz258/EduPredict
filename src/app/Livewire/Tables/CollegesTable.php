<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\College;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\On;

class CollegesTable extends BaseTable
{
    public string $sortField = 'name';

    public string $scopeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', College::class);
    }

    public function updatingScopeFilter(): void
    {
        $this->resetPage();
    }

    public function editCollege(int $collegeId): void
    {
        $college = College::query()->findOrFail($collegeId);
        $this->authorize('update', $college);
        $this->dispatch('edit-college', collegeId: $collegeId);
    }

    #[On('catalog-changed')]
    public function refreshCatalog(): void
    {
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', College::class);

        return College::query()->withCount('programs');
    }

    protected function applyFilters(Builder $query): Builder
    {
        if ($this->scopeFilter === 'pilot') {
            $query->where('is_active', true);
        } elseif ($this->scopeFilter === 'legacy') {
            $query->where('is_active', false);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'sortable' => true],
            ['key' => 'name', 'label' => 'College', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['code', 'name'];
    }

    protected function emptyMessage(): string
    {
        return 'No colleges match the current search or filters.';
    }

    public function render(): View
    {
        return view('livewire.tables.colleges-table', $this->tableViewData());
    }
}
