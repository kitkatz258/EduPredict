<?php

namespace App\Livewire\Tables;

use App\Models\InstitutionStudent;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

class InstitutionStudentsTable extends BaseTable
{
    public string $sortField = 'student_number';

    public string $programFilter = '';

    public string $yearFilter = '';

    public string $registeredFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', InstitutionStudent::class);
    }

    public function updatingProgramFilter(): void
    {
        $this->resetPage();
    }

    public function updatingYearFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRegisteredFilter(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', InstitutionStudent::class);

        return InstitutionStudent::query()->with('program');
    }

    protected function applyFilters(Builder $query): Builder
    {
        if (ctype_digit($this->programFilter)) {
            $query->where('program_id', (int) $this->programFilter);
        }

        if (in_array($this->yearFilter, ['1', '2', '3', '4'], true)) {
            $query->where('year_level', (int) $this->yearFilter);
        }

        if ($this->registeredFilter === 'yes') {
            $query->where('is_registered', true);
        } elseif ($this->registeredFilter === 'no') {
            $query->where('is_registered', false);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'student_number', 'label' => 'Student number', 'sortable' => true],
            ['key' => 'last_name', 'label' => 'Last name', 'sortable' => true],
            ['key' => 'first_name', 'label' => 'First name', 'sortable' => true],
            ['key' => 'year_level', 'label' => 'Year', 'sortable' => true],
            ['key' => 'is_registered', 'label' => 'Registered', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['student_number', 'last_name', 'first_name'];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tables.institution-students-table', [
            ...$this->tableViewData(),
            'programs' => Program::query()->orderBy('code')->get(['id', 'code']),
        ]);
    }
}
