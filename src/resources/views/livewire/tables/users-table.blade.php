<div>
    <div class="mb-4 flex flex-col gap-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <x-segmented-control label="Role" :options="$roleOptions" :current="$roleFilter" method="setRoleFilter" />
            <button type="button" wire:click="$dispatch('open-staff-form')" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">
                <i class="ri-user-add-line" aria-hidden="true"></i>Add user
            </button>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="w-full sm:max-w-sm">
                <label for="table-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                <input id="table-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search users…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
            </div>
            <x-segmented-control label="Status" :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" :current="$statusFilter" method="setStatusFilter" />
            <div class="flex items-center gap-2">
                <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
                <select id="per-page-{{ $this->getId() }}" wire:model.live="perPage" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <x-updating />
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        @foreach ($columns as $column)
                            <th class="px-4 py-3 text-left font-semibold text-brand-900">
                                <button type="button" wire:click="sortBy('{{ $column['key'] }}')">{{ $column['label'] }}</button>
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->name }}</td>
                            <td class="px-4 py-3">{{ $row->email }}</td>
                            <td class="px-4 py-3">{{ $row->role->label() }}</td>
                            <td class="px-4 py-3">{{ $row->is_active ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <x-table-action icon="ri-pencil-line" wire:click="editUser({{ $row->id }})">Edit</x-table-action>
                                    <x-table-action
                                        :icon="$row->is_active ? 'ri-forbid-2-line' : 'ri-checkbox-circle-line'"
                                        :tone="$row->is_active ? 'danger' : 'neutral'"
                                        wire:click="toggleActive({{ $row->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleActive({{ $row->id }})"
                                        :data-confirm="$row->is_active ? $row->name.' will not be able to sign in until the account is activated again.' : null"
                                        :data-confirm-title="$row->is_active ? 'Deactivate this account?' : null"
                                        :data-confirm-button="$row->is_active ? 'Deactivate' : null"
                                    >
                                        <x-spinner wire:loading wire:target="toggleActive({{ $row->id }})" />
                                        {{ $row->is_active ? 'Deactivate' : 'Activate' }}
                                    </x-table-action>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 px-4 py-3">{{ $rows->links() }}</div>
    </div>
</div>
