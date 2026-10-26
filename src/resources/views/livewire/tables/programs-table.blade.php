<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="w-full sm:max-w-xs">
                        <label for="program-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                        <input id="program-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search programs…" class="ui-field">
                    </div>
                    <div>
                        <label for="program-college-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">College</label>
                        <select id="program-college-{{ $this->getId() }}" wire:model.live="collegeFilter" class="ui-field w-auto">
                            <option value="">All colleges</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}">{{ $college->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="program-department-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Department</label>
                        <select id="program-department-{{ $this->getId() }}" wire:model.live="departmentFilter" class="ui-field w-auto">
                            <option value="">All departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="program-scope-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Scope</label>
                        <select id="program-scope-{{ $this->getId() }}" wire:model.live="scopeFilter" class="ui-field w-auto">
                            <option value="">All</option>
                            <option value="pilot">Pilot</option>
                            <option value="legacy">Legacy</option>
                        </select>
                    </div>
                </div>
                <button type="button" wire:click="$dispatch('add-program')" class="ui-btn ui-btn-primary">
                    <i class="ri-add-line" aria-hidden="true"></i>Add program
                </button>
            </div>
            <x-updating />
        </x-slot:toolbar>
        <thead>
            <tr>
                <th><button type="button" wire:click="sortBy('code')">Code</button></th>
                <th><button type="button" wire:click="sortBy('name')">Program</button></th>
                <th>College</th>
                <th>Department</th>
                <th>Scope</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->college?->code }}</td>
                    <td>{{ $row->department?->name ?? '—' }}</td>
                    <td>{{ $row->is_active ? 'Pilot (active)' : 'Legacy (outside pilot)' }}</td>
                    <td>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-table-action icon="ri-pencil-line" wire:click="editProgram({{ $row->id }})">Edit</x-table-action>
                            @if ($row->is_active && $activeProgramCount > 1)
                                <x-status-toggle
                                    :active="true"
                                    wire:click="setProgramActive({{ $row->id }})"
                                    data-confirm="New students in this program cannot register, and it leaves CLAS aggregates. Existing students, grades, and predictions stay linked."
                                    data-confirm-title="Deactivate this program?"
                                    data-confirm-button="Deactivate"
                                />
                            @elseif ($row->is_active)
                                <x-status-toggle :active="true" disabled label-on="CLAS needs this program" />
                                <p class="max-w-xs text-xs text-gray-600">CLAS needs this active program.</p>
                            @else
                                <x-status-toggle :active="false" disabled label-off="Activating would allow new registrations" />
                                <p class="max-w-xs text-xs text-gray-600">Inactive. Activating this program would allow new registrations, so it stays outside the pilot.</p>
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
