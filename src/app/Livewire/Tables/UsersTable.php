<?php

namespace App\Livewire\Tables;

use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Database\Eloquent\Builder;

class UsersTable extends BaseTable
{
    public string $sortField = 'name';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function toggleActive(int $userId): void
    {
        $target = User::query()->findOrFail($userId);
        $this->authorize('update', $target);

        app(StaffAccountService::class)->setActive(
            $target,
            ! $target->is_active,
            auth()->user(),
            request()->ip(),
        );

        $this->toast($target->is_active ? "{$target->name} can sign in again." : "{$target->name} was deactivated.");
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', User::class);

        return User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'created_at']);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'sortable' => true],
            ['key' => 'email', 'label' => 'Email', 'sortable' => true],
            ['key' => 'role', 'label' => 'Role', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Active', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['name', 'email'];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tables.users-table', $this->tableViewData());
    }
}
