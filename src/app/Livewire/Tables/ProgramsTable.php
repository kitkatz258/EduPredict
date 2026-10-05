<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ProgramsTable extends BaseTable
{
    public string $sortField = 'name';

    public function mount(): void
    {
        $this->authorize('viewAny', Program::class);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', Program::class);

        return Program::query()->with('college');
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
        return 'No programs match the current search.';
    }

    public function render(): View
    {
        return view('livewire.tables.programs-table', $this->tableViewData());
    }
}
