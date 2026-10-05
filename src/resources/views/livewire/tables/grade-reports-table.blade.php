<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-sm">
            <label for="table-search-{{ $this->getId() }}" class="sr-only">Search</label>
            <input id="table-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search grade reports…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
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
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm">
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
                            <td class="px-4 py-3">{{ $row->school_year }}</td>
                            <td class="px-4 py-3">{{ $row->semester }}</td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $row->source) }}</td>
                            <td class="px-4 py-3">{{ $row->status }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('student.grades', ['report' => $row->id]) }}" class="text-sm font-medium text-brand-900 underline">Open</a>
                                <button type="button" wire:click="deleteReport({{ $row->id }})" wire:confirm="Delete this grade report?" class="ml-3 text-sm text-red-800 underline">Delete</button>
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
