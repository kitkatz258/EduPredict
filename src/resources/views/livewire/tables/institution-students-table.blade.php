<div>
    <div class="mb-4 flex flex-col gap-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
            <div class="w-full sm:max-w-sm">
                <label for="table-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                <input id="table-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search eligible students…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
            </div>
            <div>
                <label for="eligible-program-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Program</label>
                <select id="eligible-program-{{ $this->getId() }}" wire:model.live="programFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}">{{ $program->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="eligible-year-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Year</label>
                <select id="eligible-year-{{ $this->getId() }}" wire:model.live="yearFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All years</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div>
                <label for="eligible-registered-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Registered</label>
                <select id="eligible-registered-{{ $this->getId() }}" wire:model.live="registeredFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="yes">Registered</option>
                    <option value="no">Not registered</option>
                </select>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
            <select id="per-page-{{ $this->getId() }}" wire:model.live="perPage" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
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
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Program</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->student_number }}</td>
                            <td class="px-4 py-3">{{ $row->last_name }}</td>
                            <td class="px-4 py-3">{{ $row->first_name }}</td>
                            <td class="px-4 py-3">{{ $row->year_level }}</td>
                            <td class="px-4 py-3">{{ $row->is_registered ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $row->program?->code }}</td>
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
