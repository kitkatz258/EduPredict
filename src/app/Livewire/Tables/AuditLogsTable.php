<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class AuditLogsTable extends BaseTable
{
    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public string $actionFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', AuditLog::class);

        return AuditLog::query()->with(['user', 'subject']);
    }

    protected function applyFilters(Builder $query): Builder
    {
        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        return $query;
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('action', 'like', '%'.$term.'%')
                ->orWhere('ip', 'like', '%'.$term.'%')
                ->orWhereHas('user', function (Builder $user) use ($term): void {
                    $user->where('name', 'like', '%'.$term.'%');
                });
        });
    }

    protected function columns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Date & time', 'sortable' => true],
            ['key' => 'action', 'label' => 'Action', 'sortable' => true],
            ['key' => 'ip', 'label' => 'IP address', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['action', 'ip'];
    }

    protected function emptyMessage(): string
    {
        return 'No activity matches the current search.';
    }

    public function render(): View
    {
        return view('livewire.tables.audit-logs-table', [
            ...$this->tableViewData(),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
