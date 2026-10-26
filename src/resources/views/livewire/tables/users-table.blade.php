<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid w-full gap-3 sm:grid-cols-2 lg:max-w-xl">
                        <div>
                            <label for="user-role-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                            <select id="user-role-{{ $this->getId() }}" wire:model.live="roleFilter" class="ui-field">
                                @foreach ($roleOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="user-status-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                            <select id="user-status-{{ $this->getId() }}" wire:model.live="statusFilter" class="ui-field">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" wire:click="$dispatch('open-staff-form')" class="ui-btn ui-btn-primary">
                        <i class="ri-user-add-line" aria-hidden="true"></i>Add user
                    </button>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="w-full sm:max-w-sm">
                        <label for="table-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                        <div class="relative">
                            <i class="ri-search-line pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" aria-hidden="true"></i>
                            <input id="table-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search by name or email…" class="ui-field has-search">
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
                        <select id="per-page-{{ $this->getId() }}" wire:model.live="perPage" class="ui-field w-auto">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <x-updating />
        </x-slot:toolbar>

        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>
                        <button type="button" wire:click="sortBy('{{ $column['key'] }}')" class="inline-flex items-center gap-1 {{ $sortField === $column['key'] ? 'text-gray-900' : '' }}">
                            {{ $column['label'] }}
                            @if ($sortField === $column['key'])
                                <i class="{{ $sortDirection === 'asc' ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line' }} text-base leading-none" aria-hidden="true"></i>
                                <span class="sr-only">sorted {{ $sortDirection === 'asc' ? 'ascending' : 'descending' }}</span>
                            @endif
                        </button>
                    </th>
                @endforeach
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $initials = collect(preg_split('/\s+/', trim((string) $row->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                    $avatarTones = ['bg-indigo-100 text-indigo-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-800', 'bg-sky-100 text-sky-700', 'bg-rose-100 text-rose-700'];
                    $avatarTone = $avatarTones[abs(crc32((string) $row->name)) % count($avatarTones)];
                @endphp
                <tr>
                    <td>
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[11px] font-bold {{ $avatarTone }}">{{ $initials !== '' ? $initials : 'U' }}</span>
                            <span class="font-medium text-gray-900">{{ $row->name }}</span>
                        </div>
                    </td>
                    <td class="text-gray-600">{{ $row->email }}</td>
                    <td>{{ $row->role->label() }}</td>
                    <td>
                        <x-status-pill :tone="$row->is_active ? 'success' : 'neutral'">
                            {{ $row->is_active ? 'Active' : 'Inactive' }}
                        </x-status-pill>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-table-action icon="ri-pencil-line" wire:click="editUser({{ $row->id }})">Edit</x-table-action>
                            <x-table-action
                                :icon="$row->is_active ? 'ri-user-unfollow-line' : 'ri-user-follow-line'"
                                :tone="$row->is_active ? 'danger' : 'success'"
                                wire:click="toggleActive({{ $row->id }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleActive({{ $row->id }})"
                                :data-confirm="$row->is_active ? $row->name.' will not be able to sign in until the account is activated again.' : null"
                                :data-confirm-title="$row->is_active ? 'Deactivate this account?' : null"
                                :data-confirm-button="$row->is_active ? 'Deactivate' : null"
                            >
                                {{ $row->is_active ? 'Deactivate' : 'Activate' }}
                            </x-table-action>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>

        <x-slot:footer>
            {{ $rows->links() }}
        </x-slot:footer>
    </x-data-table>
</div>
