<div>
    <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="w-full sm:max-w-xs">
                <label for="program-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                <input id="program-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search programs…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="program-college-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">College</label>
                <select id="program-college-{{ $this->getId() }}" wire:model.live="collegeFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All colleges</option>
                    @foreach ($colleges as $college)
                        <option value="{{ $college->id }}">{{ $college->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="program-department-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Department</label>
                <select id="program-department-{{ $this->getId() }}" wire:model.live="departmentFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="program-scope-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Scope</label>
                <select id="program-scope-{{ $this->getId() }}" wire:model.live="scopeFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="pilot">Pilot</option>
                    <option value="legacy">Legacy</option>
                </select>
            </div>
        </div>
        <button type="button" wire:click="$dispatch('add-program')" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">
            <i class="ri-add-line" aria-hidden="true"></i>Add program
        </button>
    </div>
    <x-updating />
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('code')">Code</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('name')">Program</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">College</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Department</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Scope</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->code }}</td>
                            <td class="px-4 py-3">{{ $row->name }}</td>
                            <td class="px-4 py-3">{{ $row->college?->code }}</td>
                            <td class="px-4 py-3">{{ $row->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row->is_active ? 'Pilot (active)' : 'Legacy (outside pilot)' }}</td>
                            <td class="px-4 py-3">
                                <x-table-action icon="ri-pencil-line" wire:click="editProgram({{ $row->id }})">Edit</x-table-action>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 px-4 py-3">{{ $rows->links() }}</div>
    </div>
</div>
