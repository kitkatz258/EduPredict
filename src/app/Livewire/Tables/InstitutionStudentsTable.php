<?php

namespace App\Livewire\Tables;

use App\Models\InstitutionStudent;
use Illuminate\Database\Eloquent\Builder;

class InstitutionStudentsTable extends BaseTable
{
    public string $sortField = 'student_number';

    public function mount(): void
    {
        $this->authorize('viewAny', InstitutionStudent::class);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', InstitutionStudent::class);

        return InstitutionStudent::query()->with('program');
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
        return view('livewire.tables.institution-students-table', $this->tableViewData());
    }
}
