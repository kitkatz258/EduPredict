<div>
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="w-full sm:max-w-xs">
                <label for="department-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                <input id="department-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search departments…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="department-college-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">College</label>
                <select id="department-college-{{ $this->getId() }}" wire:model.live="collegeFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All colleges</option>
                    @foreach ($colleges as $college)
                        <option value="{{ $college->id }}">{{ $college->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="department-scope-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Status</label>
                <select id="department-scope-{{ $this->getId() }}" wire:model.live="scopeFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
        <button type="button" wire:click="$dispatch('add-department')" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">
            <i class="ri-add-line" aria-hidden="true"></i>Add department
        </button>
    </div>
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('code')">Code</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('name')">Department</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">College</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Programs</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->code }}</td>
                            <td class="px-4 py-3">{{ $row->name }}</td>
                            <td class="px-4 py-3">{{ $row->college?->code }}</td>
                            <td class="px-4 py-3">{{ $row->programs_count }}</td>
                            <td class="px-4 py-3">{{ $row->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-4 py-3">
                                <x-table-action icon="ri-pencil-line" wire:click="editDepartment({{ $row->id }})">Edit</x-table-action>
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
