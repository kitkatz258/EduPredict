<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\College;
use App\Models\Department;
use App\Services\Academic\AcademicActivation;
use App\Services\Academic\ClasStructureSynchronizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;

class DepartmentsTable extends BaseTable
{
    use DispatchesToasts;

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

    public function setDepartmentActive(int $departmentId, AcademicActivation $activation): void
    {
        $department = Department::query()->findOrFail($departmentId);
        $this->authorize('update', $department);
        $next = ! $department->is_active;

        try {
            $activation->setDepartmentActive($department, $next);
        } catch (ValidationException $exception) {
            $this->toast($exception->validator->errors()->first() ?: 'That status change is not allowed.', 'error');

            return;
        }

        $this->toast($next ? 'Department activated. Linked programs and students were kept.' : 'Department deactivated. Linked programs, students, and predictions were kept.');
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
        $pilotId = College::query()->where('code', ClasStructureSynchronizer::COLLEGE_CODE)->value('id');

        return view('livewire.tables.departments-table', [
            ...$this->tableViewData(),
            'colleges' => College::query()->orderBy('code')->get(['id', 'code', 'name']),
            'activePilotDepartments' => $pilotId
                ? Department::query()->where('college_id', $pilotId)->where('is_active', true)->count()
                : 0,
            'pilotCollegeId' => $pilotId,
        ]);
    }
}
