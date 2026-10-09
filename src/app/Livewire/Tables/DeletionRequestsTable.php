<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\AccountDeletionRequest;
use App\Services\Admin\StaffAccountService;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DeletionRequestsTable extends BaseTable
{
    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public string $statusFilter = 'pending';

    public string $adminNote = '';

    public ?int $reviewingId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', AccountDeletionRequest::class);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        if (! in_array($status, ['pending', 'approved', 'rejected', ''], true)) {
            return;
        }

        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function openReview(int $requestId): void
    {
        $request = AccountDeletionRequest::query()->findOrFail($requestId);
        $this->authorize('view', $request);
        $this->reviewingId = $request->id;
        $this->adminNote = (string) ($request->admin_note ?? '');
        $this->resetValidation();
    }

    public function closeReview(): void
    {
        $this->reviewingId = null;
        $this->adminNote = '';
        $this->resetValidation();
    }

    public function approve(int $requestId, StaffAccountService $accounts, AuditLogger $audit): void
    {
        $this->decide($requestId, 'approved', $accounts, $audit);
    }

    public function reject(int $requestId, StaffAccountService $accounts, AuditLogger $audit): void
    {
        $this->decide($requestId, 'rejected', $accounts, $audit);
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', AccountDeletionRequest::class);

        return AccountDeletionRequest::query()->with('user');
    }

    protected function applyFilters(Builder $query): Builder
    {
        if (in_array($this->statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $this->statusFilter);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Requested', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['status', 'reason'];
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('reason', 'like', '%'.$term.'%')
                ->orWhere('status', 'like', '%'.$term.'%')
                ->orWhereHas('user', function (Builder $user) use ($term): void {
                    $user->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                });
        });
    }

    protected function emptyMessage(): string
    {
        return 'No deletion requests match this filter.';
    }

    public function render(): View
    {
        return view('livewire.tables.deletion-requests-table', [
            ...$this->tableViewData(),
            'reviewing' => $this->reviewingId
                ? AccountDeletionRequest::query()->with('user')->find($this->reviewingId)
                : null,
        ]);
    }

    private function decide(int $requestId, string $status, StaffAccountService $accounts, AuditLogger $audit): void
    {
        $request = AccountDeletionRequest::query()->with('user')->findOrFail($requestId);
        $this->authorize('process', $request);
        $this->validate([
            'adminNote' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->status !== 'pending' || $request->user === null) {
            return;
        }

        if ($status === 'approved') {
            $accounts->setActive($request->user, false, auth()->user(), request()->ip());
        }

        $request->update([
            'status' => $status,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
            'admin_note' => $this->adminNote !== '' ? $this->adminNote : null,
        ]);

        $audit->record('account_deletion_processed', $request, [
            'decision' => $status,
            'account_user_id' => $request->user_id,
        ]);

        $this->adminNote = '';
        if ($this->reviewingId === $requestId) {
            $this->reviewingId = null;
        }
        $this->toast($status === 'approved'
            ? 'Account deactivated. Stored predictions were kept.'
            : 'Deletion request rejected. The account stays active.');
    }
}
