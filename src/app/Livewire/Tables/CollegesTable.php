<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\College;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class CollegesTable extends BaseTable
{
    public string $sortField = 'name';

    public function mount(): void
    {
        $this->authorize('viewAny', College::class);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', College::class);

        return College::query()->withCount('programs');
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
        return 'No colleges match the current search.';
    }

    public function render(): View
    {
        return view('livewire.tables.colleges-table', $this->tableViewData());
    }
}
