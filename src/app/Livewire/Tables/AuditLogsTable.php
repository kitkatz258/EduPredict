<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditRecordLabel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogsTable extends BaseTable
{
    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public string $actionFilter = '';

    public string $actorFilter = '';

    public string $startDate = '';

    public string $endDate = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingActorFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStartDate(): void
    {
        $this->resetPage();
    }

    public function updatingEndDate(): void
    {
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);
        $this->validateRange();

        $rows = $this->filteredQuery()->limit(5000)->get();

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date and time', 'Action', 'Performed by', 'Affected record', 'IP address']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    AuditRecordLabel::action($row->action),
                    AuditRecordLabel::actor($row),
                    AuditRecordLabel::affected($row),
                    $row->ip ?: '',
                ]);
            }
            fclose($handle);
        }, 'activity-log.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', AuditLog::class);

        return AuditLog::query()->with(['user', 'subject']);
    }

    protected function applyFilters(Builder $query): Builder
    {
        $this->validateRange();

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        if (ctype_digit($this->actorFilter)) {
            $query->where('user_id', (int) $this->actorFilter);
        }

        $timezone = (string) config('app.timezone', 'Asia/Manila');
        if ($this->startDate !== '') {
            $start = Carbon::createFromFormat('Y-m-d', $this->startDate, $timezone)?->startOfDay();
            if ($start) {
                $query->where('created_at', '>=', $start->timezone(config('app.timezone')));
            }
        }
        if ($this->endDate !== '') {
            $end = Carbon::createFromFormat('Y-m-d', $this->endDate, $timezone)?->endOfDay();
            if ($end) {
                $query->where('created_at', '<=', $end->timezone(config('app.timezone')));
            }
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
            $inner->whereLike('action', '%'.$term.'%')
                ->orWhereLike('ip', '%'.$term.'%')
                ->orWhereHas('user', function (Builder $user) use ($term): void {
                    $user->whereLike('name', '%'.$term.'%');
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
            'actors' => User::query()->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    private function validateRange(): void
    {
        if ($this->startDate !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startDate)) {
            $this->startDate = '';
        }
        if ($this->endDate !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->endDate)) {
            $this->endDate = '';
        }
        if ($this->startDate !== '' && $this->endDate !== '' && $this->startDate > $this->endDate) {
            $this->endDate = $this->startDate;
        }
    }
}
