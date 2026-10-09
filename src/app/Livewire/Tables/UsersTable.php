<?php

namespace App\Livewire\Tables;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class UsersTable extends BaseTable
{
    public string $sortField = 'name';

    public string $roleFilter = '';

    public string $statusFilter = '';

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

    public function setRoleFilter(string $role): void
    {
        $allowed = array_map(fn (UserRole $item): string => $item->value, UserRole::active());
        $this->roleFilter = in_array($role, $allowed, true) ? $role : '';
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = in_array($status, ['active', 'inactive'], true) ? $status : '';
        $this->resetPage();
    }

    public function editUser(int $userId): void
    {
        $target = User::query()->findOrFail($userId);
        $this->authorize('update', $target);
        $this->dispatch('edit-user', userId: $userId);
    }

    #[On('users-changed')]
    public function refreshUsers(): void
    {
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', User::class);

        return User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'created_at']);
    }

    protected function applyFilters(Builder $query): Builder
    {
        $allowed = array_map(fn (UserRole $item): string => $item->value, UserRole::active());

        if (in_array($this->roleFilter, $allowed, true)) {
            $query->where('role', $this->roleFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
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
        return view('livewire.tables.users-table', [
            ...$this->tableViewData(),
            'roleOptions' => ['' => 'All', ...collect(UserRole::active())->mapWithKeys(
                fn (UserRole $role): array => [$role->value => $role->label()],
            )->all()],
        ]);
    }
}
