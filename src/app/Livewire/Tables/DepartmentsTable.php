<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\College;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\On;

class DepartmentsTable extends BaseTable
{
    public string $sortField = 'name';

    public string $collegeFilter = '';

    public string $scopeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Department::class);
    }

    public function updatingCollegeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingScopeFilter(): void
    {
        $this->resetPage();
    }

    public function editDepartment(int $departmentId): void
    {
        $department = Department::query()->findOrFail($departmentId);
        $this->authorize('update', $department);
        $this->dispatch('edit-department', departmentId: $departmentId);
    }

    #[On('catalog-changed')]
    public function refreshCatalog(): void
    {
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', Department::class);

        return Department::query()->with('college')->withCount('programs');
    }

    protected function applyFilters(Builder $query): Builder
    {
        if (ctype_digit($this->collegeFilter)) {
            $query->where('college_id', (int) $this->collegeFilter);
        }

        if ($this->scopeFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->scopeFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Department', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['code', 'name'];
    }

    protected function emptyMessage(): string
    {
        return 'No departments match the current search or filters.';
    }

    public function render(): View
    {
        return view('livewire.tables.departments-table', [
            ...$this->tableViewData(),
            'colleges' => College::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
