<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="w-full sm:max-w-sm">
                        <label for="psoc-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                        <input id="psoc-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search code, title, or group…" class="ui-field">
                    </div>
                    <div>
                        <label for="psoc-group-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Major group</label>
                        <select id="psoc-group-{{ $this->getId() }}" wire:model.live="groupFilter" class="ui-field w-auto max-w-xs">
                            <option value="">All groups</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group }}">{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="psoc-active-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Status</label>
                        <select id="psoc-active-{{ $this->getId() }}" wire:model.live="activeFilter" class="ui-field w-auto">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="button" wire:click="$dispatch('add-occupation')" class="ui-btn ui-btn-primary">
                    <i class="ri-add-line" aria-hidden="true"></i>Add occupation
                </button>
            </div>
            <x-updating />
        </x-slot:toolbar>
        <thead>
            <tr>
                <th class="text-center"><button type="button" wire:click="sortBy('psoc_code')">PSOC code</button></th>
                <th><button type="button" wire:click="sortBy('title')">Title</button></th>
                <th><button type="button" wire:click="sortBy('major_group')">Major group</button></th>
                <th class="text-center">Status</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center font-mono">{{ $row->psoc_code }}</td>
                    <td>{{ $row->title }}</td>
                    <td>{{ $row->major_group }}</td>
                    <td class="text-center">
                        <x-status-pill :tone="$row->is_active ? 'success' : 'neutral'">{{ $row->is_active ? 'Active' : 'Inactive' }}</x-status-pill>
                    </td>
                    <td>
                        <div class="flex flex-nowrap items-center justify-center gap-1.5">
                            <x-table-action icon="ri-eye-line" wire:click="viewOccupation({{ $row->id }})">View</x-table-action>
                            <x-table-action icon="ri-pencil-line" wire:click="editOccupation({{ $row->id }})">Edit</x-table-action>
                            <x-status-toggle
                                :active="$row->is_active"
                                wire:click="setOccupationActive({{ $row->id }})"
                                :data-confirm="$row->is_active
                                    ? 'This occupation will not appear in new career suggestions. Stored matches on earlier predictions stay as they were.'
                                    : 'This occupation can appear in new career suggestions. Stored matches on earlier predictions are not rewritten.'"
                                :data-confirm-title="$row->is_active ? 'Deactivate this occupation?' : 'Activate this occupation?'"
                                :data-confirm-button="$row->is_active ? 'Deactivate' : 'Activate'"
                                :data-confirm-tone="$row->is_active ? 'danger' : 'neutral'"
                            />
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-data-table>
</div>
