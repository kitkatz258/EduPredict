<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="w-full sm:max-w-sm">
            <label for="history-search-{{ $this->getId() }}" class="sr-only">Search prediction history</label>
            <input id="history-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search risk, confidence, or model…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="history-risk-{{ $this->getId() }}" class="mr-2 text-sm text-gray-600">Risk</label>
                <select id="history-risk-{{ $this->getId() }}" wire:model.live="filters.dropout_risk" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
                    <option value="">All</option>
                    <option value="low">Low</option>
                    <option value="moderate">Moderate</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div>
                <label for="history-per-page-{{ $this->getId() }}" class="mr-2 text-sm text-gray-600">Per page</label>
                <select id="history-per-page-{{ $this->getId() }}" wire:model.live="perPage" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm text-gray-800">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
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
                                        <span class="sr-only">sorted {{ $sortDirection }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3 text-gray-700">{{ $row->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3 font-medium text-brand-900">{{ number_format((float) $row->employability_score, 1) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-risk-badge :level="$row->dropout_risk" />
                                    <x-program-shift :prediction="$row" compact />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ number_format((float) $row->dropout_probability * 100, 1) }}%</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->confidence === 'low' ? 'Lower confidence' : 'Normal' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $row->model_version }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 bg-white px-4 py-3">{{ $rows->links() }}</div>
    </div>
</div>
