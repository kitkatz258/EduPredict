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
                </div>
                <button type="button" wire:click="$dispatch('add-occupation')" class="ui-btn ui-btn-primary">
                    <i class="ri-add-line" aria-hidden="true"></i>Add occupation
                </button>
            </div>
            <x-updating />
        </x-slot:toolbar>
        <thead>
            <tr>
                <th><button type="button" wire:click="sortBy('psoc_code')">PSOC code</button></th>
                <th><button type="button" wire:click="sortBy('title')">Title</button></th>
                <th><button type="button" wire:click="sortBy('major_group')">Major group</button></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="font-mono">{{ $row->psoc_code }}</td>
                    <td>{{ $row->title }}</td>
                    <td>{{ $row->major_group }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-table-action icon="ri-eye-line" wire:click="viewOccupation({{ $row->id }})">View</x-table-action>
                            <x-table-action icon="ri-pencil-line" wire:click="editOccupation({{ $row->id }})">Edit</x-table-action>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-data-table>
</div>
