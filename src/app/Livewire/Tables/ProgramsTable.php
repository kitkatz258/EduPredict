<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Services\Academic\AcademicActivation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;

class ProgramsTable extends BaseTable
{
    use DispatchesToasts;

    public string $sortField = 'name';

    public string $collegeFilter = '';

    public string $departmentFilter = '';

    public string $scopeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Program::class);
    }

    public function updatingCollegeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCollegeFilter(): void
    {
        if ($this->departmentFilter === '' || $this->collegeFilter === '') {
            return;
        }

        $matches = Department::query()
            ->whereKey((int) $this->departmentFilter)
            ->where('college_id', (int) $this->collegeFilter)
            ->exists();

        if (! $matches) {
            $this->departmentFilter = '';
        }
    }

    public function updatingDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingScopeFilter(): void
    {
        $this->resetPage();
    }

    public function editProgram(int $programId): void
    {
        $program = Program::query()->findOrFail($programId);
        $this->authorize('update', $program);
        $this->dispatch('edit-program', programId: $programId);
    }

    public function setProgramActive(int $programId, AcademicActivation $activation): void
    {
        $program = Program::query()->findOrFail($programId);
        $this->authorize('update', $program);
        $next = ! $program->is_active;

        try {
            $activation->setProgramActive($program, $next);
        } catch (ValidationException $exception) {
            $this->toast($exception->validator->errors()->first() ?: 'That status change is not allowed.', 'error');

            return;
        }

        $this->toast($next
            ? 'Program activated.'
            : 'Program deactivated. Students, grades, and predictions stay linked.');
    }

    #[On('catalog-changed')]
    public function refreshCatalog(): void {}

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', Program::class);

        return Program::query()->with(['college', 'department']);
    }

    protected function applyFilters(Builder $query): Builder
    {
        if (ctype_digit($this->collegeFilter)) {
            $query->where('college_id', (int) $this->collegeFilter);
        }

        if (ctype_digit($this->departmentFilter)) {
            $query->where('department_id', (int) $this->departmentFilter);
        }

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
            ['key' => 'name', 'label' => 'Program', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['code', 'name'];
    }

    protected function emptyMessage(): string
    {
        return 'No programs match the current search or filters.';
    }

    public function render(): View
    {
        return view('livewire.tables.programs-table', [
            ...$this->tableViewData(),
            'activeProgramCount' => Program::query()->where('is_active', true)->count(),
            'colleges' => College::query()->orderBy('code')->get(['id', 'code']),
            'departments' => Department::query()
                ->when(ctype_digit($this->collegeFilter), fn (Builder $query) => $query->where('college_id', (int) $this->collegeFilter))
                ->orderBy('name')
                ->get(['id', 'name', 'college_id']),
        ]);
    }
}
