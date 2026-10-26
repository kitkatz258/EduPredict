<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="w-full sm:max-w-xs">
                        <label for="department-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                        <input id="department-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search departments…" class="ui-field">
                    </div>
                    <div>
                        <label for="department-college-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">College</label>
                        <select id="department-college-{{ $this->getId() }}" wire:model.live="collegeFilter" class="ui-field w-auto">
                            <option value="">All colleges</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}">{{ $college->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="department-scope-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Status</label>
                        <select id="department-scope-{{ $this->getId() }}" wire:model.live="scopeFilter" class="ui-field w-auto">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="button" wire:click="$dispatch('add-department')" class="ui-btn ui-btn-primary">
                    <i class="ri-add-line" aria-hidden="true"></i>Add department
                </button>
            </div>
            <x-updating />
        </x-slot:toolbar>
        <thead>
            <tr>
                <th><button type="button" wire:click="sortBy('code')">Code</button></th>
                <th><button type="button" wire:click="sortBy('name')">Department</button></th>
                <th>College</th>
                <th>Programs</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $canDeactivate = ! $row->is_active || (int) $row->college_id !== (int) $pilotCollegeId || $activePilotDepartments > 1;
                @endphp
                <tr>
                    <td>{{ $row->code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->college?->code }}</td>
                    <td>{{ $row->programs_count }}</td>
                    <td>
                        <x-status-pill :tone="$row->is_active ? 'success' : 'neutral'">
                            {{ $row->is_active ? 'Active' : 'Inactive' }}
                        </x-status-pill>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-table-action icon="ri-pencil-line" wire:click="editDepartment({{ $row->id }})">Edit</x-table-action>
                            @if ($canDeactivate)
                                <x-status-toggle
                                    :active="$row->is_active"
                                    wire:click="setDepartmentActive({{ $row->id }})"
                                    :data-confirm="$row->is_active
                                        ? 'Students, programs, and prediction history stay linked. The department leaves active filters. Accounts are not deleted.'
                                        : 'The department is marked active again. Programs keep their current pilot status. No records are deleted.'"
                                    :data-confirm-title="$row->is_active ? 'Deactivate this department?' : 'Activate this department?'"
                                    :data-confirm-button="$row->is_active ? 'Deactivate' : 'Activate'"
                                    :data-confirm-tone="$row->is_active ? 'danger' : 'neutral'"
                                />
                            @else
                                <x-status-toggle :active="true" disabled label-on="CLAS needs this department" />
                                <p class="max-w-xs text-xs text-gray-600">CLAS needs this active department.</p>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-data-table>
</div>
