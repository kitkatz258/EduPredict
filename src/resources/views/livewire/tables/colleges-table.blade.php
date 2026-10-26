<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="w-full sm:max-w-xs">
                        <label for="college-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                        <input id="college-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search colleges…" class="ui-field">
                    </div>
                    <div>
                        <label for="college-scope-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Scope</label>
                        <select id="college-scope-{{ $this->getId() }}" wire:model.live="scopeFilter" class="ui-field w-auto">
                            <option value="">All</option>
                            <option value="pilot">Pilot</option>
                            <option value="legacy">Legacy</option>
                        </select>
                    </div>
                </div>
                <button type="button" wire:click="$dispatch('add-college')" class="ui-btn ui-btn-primary">
                    <i class="ri-add-line" aria-hidden="true"></i>Add college
                </button>
            </div>
            <x-updating />
        </x-slot:toolbar>
        <thead>
            <tr>
                <th><button type="button" wire:click="sortBy('code')">Code</button></th>
                <th><button type="button" wire:click="sortBy('name')">College</button></th>
                <th>Programs</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->programs_count }}</td>
                    <td>
                        <x-status-pill :tone="$row->is_active ? 'success' : 'neutral'">
                            {{ $row->is_active ? 'Active' : 'Inactive' }}
                        </x-status-pill>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-table-action icon="ri-pencil-line" wire:click="editCollege({{ $row->id }})">Edit</x-table-action>
                            @if ($row->code === $clasCode)
                                <x-status-toggle :active="true" disabled label-on="CLAS stays active" />
                                <p class="max-w-xs text-xs text-gray-600">The CLAS pilot college stays active.</p>
                            @else
                                <x-status-toggle :active="$row->is_active" disabled :label-on="'Inactive colleges stay outside the pilot'" :label-off="'Activating would widen registration'" />
                                <p class="max-w-xs text-xs text-gray-600">Inactive colleges stay outside the pilot.</p>
                            @endif
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
