<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full sm:max-w-sm">
            <label for="table-search-{{ $this->getId() }}" class="sr-only">Search questionnaire items</label>
            <input id="table-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search items…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
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
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        @foreach ($columns as $column)
                            <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">
                                <button type="button" wire:click="sortBy('{{ $column['key'] }}')" class="inline-flex items-center gap-1">
                                    {{ $column['label'] }}
                                    @if ($sortField === $column['key'])
                                        <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->sort_order }}</td>
                            <td class="px-4 py-3">{{ config('edupredict.questionnaire.constructs.'.$row->construct, $row->construct) }}</td>
                            <td class="px-4 py-3">{{ $row->text }}</td>
                            <td class="px-4 py-3">{{ $row->reverse_scored ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $row->is_active ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $row->is_draft ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route('admin.questionnaire', ['item' => $row->id]) }}" class="font-medium text-brand-900 underline">Edit</a>
                                    <button type="button" wire:click="toggleActive({{ $row->id }})" class="font-medium text-brand-900 underline">{{ $row->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    <button type="button" wire:click="toggleDraft({{ $row->id }})" class="font-medium text-brand-900 underline">{{ $row->is_draft ? 'Mark published' : 'Mark draft' }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 1 }}" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 px-4 py-3">{{ $rows->links() }}</div>
    </div>
</div>
