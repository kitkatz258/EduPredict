<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\PsocOccupation;
use Illuminate\Database\Eloquent\Builder;

class PsocOccupationsTable extends BaseTable
{
    public string $sortField = 'title';

    public function mount(): void
    {
        $this->authorize('viewAny', PsocOccupation::class);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', PsocOccupation::class);

        return PsocOccupation::query();
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
        return 'No occupations match the current search.';
    }
}
