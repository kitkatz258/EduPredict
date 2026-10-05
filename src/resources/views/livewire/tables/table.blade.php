<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-sm">
            <label for="table-search-{{ $this->getId() }}" class="sr-only">Search</label>
            <input
                id="table-search-{{ $this->getId() }}"
                type="search"
                wire:model.live.debounce.400ms="search"
                placeholder="Search…"
                class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200"
            >
        </div>
        <div class="flex items-center gap-2">
            <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
            <select
                id="per-page-{{ $this->getId() }}"
                wire:model.live="perPage"
                class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200"
            >
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
                            @php
                                $sortable = $column['sortable'] ?? true;
                                $align = $column['align'] ?? 'left';
                            @endphp
                            <th scope="col" class="px-4 py-3 font-semibold text-brand-900 @if($align === 'right') text-right @else text-left @endif">
                                @if ($sortable)
                                    <button
                                        type="button"
                                        wire:click="sortBy('{{ $column['key'] }}')"
                                        class="inline-flex items-center gap-1 hover:text-brand-400"
                                    >
                                        {{ $column['label'] }}
                                        @if ($sortField === $column['key'])
                                            <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                            <span class="sr-only">sorted {{ $sortDirection }}</span>
                                        @endif
                                    </button>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }} hover:bg-brand-200/40">
                            @foreach ($columns as $column)
                                @php $align = $column['align'] ?? 'left'; @endphp
                                <td class="px-4 py-3 text-gray-700 @if($align === 'right') text-right @endif">
                                    {{ $row->{$column['key']} }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-4 py-12 text-center text-gray-500">
                                {{ $emptyMessage }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 bg-white px-4 py-3">
            {{ $rows->links() }}
        </div>
    </div>
</div>
