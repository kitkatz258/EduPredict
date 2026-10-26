<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\PredictionAttemptGrant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\On;

class PredictionGrantsTable extends BaseTable
{
    public string $sortField = 'id';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->authorize('viewAny', PredictionAttemptGrant::class);
    }

    #[On('prediction-grants-changed')]
    public function refreshGrants(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', PredictionAttemptGrant::class);

        return PredictionAttemptGrant::query()->with(['student.user', 'grantor']);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Granted', 'sortable' => true],
            ['key' => 'manila_date', 'label' => 'Manila date', 'sortable' => true],
            ['key' => 'extra_attempts', 'label' => 'Extra slots', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['reason'];
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->whereLike('reason', '%'.$term.'%')
                ->orWhereHas('student', function (Builder $student) use ($term): void {
                    $student->whereLike('student_number', '%'.$term.'%')
                        ->orWhereHas('user', fn (Builder $user) => $user->whereLike('name', '%'.$term.'%'));
                });
        });
    }

    protected function emptyMessage(): string
    {
        return 'No prediction grants match the current search.';
    }

    public function render(): View
    {
        return view('livewire.tables.prediction-grants-table', $this->tableViewData());
    }
}
