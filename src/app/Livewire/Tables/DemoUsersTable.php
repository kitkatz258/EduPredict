<?php

namespace App\Livewire\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DemoUsersTable extends BaseTable
{
    public string $sortField = 'name';

    public function mount(): void
    {
        // Public demo only. Real tables authorize in mount() and every action.
    }

    protected function baseQuery(): Builder
    {
        return User::query()->select(['id', 'name', 'email', 'created_at']);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'sortable' => true],
            ['key' => 'name', 'label' => 'Name', 'sortable' => true],
            ['key' => 'email', 'label' => 'Email', 'sortable' => true],
            ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['name', 'email'];
    }

    protected function emptyMessage(): string
    {
        return 'No users found. Seed the database to populate this demo table.';
    }
}
