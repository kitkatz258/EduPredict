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

    <x-updating target="search,filters,perPage,sortBy,gotoPage,nextPage,previousPage" />
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60" wire:target="search,filters,perPage,sortBy,gotoPage,nextPage,previousPage">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        @foreach ($columns as $column)
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-semibold text-brand-900">
                                <button type="button" wire:click="sortBy('{{ $column['key'] }}')" class="inline-flex items-center gap-1">
                                    {{ $column['label'] }}
                                    @if ($sortField === $column['key'])
                                        <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        <span class="sr-only">sorted {{ $sortDirection }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Status</th>
                        @if ($isOwner)
                            <th scope="col" class="px-4 py-3 text-right font-semibold text-brand-900"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr wire:key="attempt-{{ $row->id }}" class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $row->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3 font-medium text-brand-900">{{ number_format((float) $row->employability_score, 1) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-risk-badge :level="$row->dropout_risk" />
                                    <x-program-shift :prediction="$row" compact />
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->confidence === 'low' ? 'Lower confidence' : 'Normal' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row->model_version }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($row->id === $latestId)
                                        <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-900">Latest</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-600">Archived</span>
                                    @endif
                                    @unless ($snapshots->isComplete($row))
                                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-gray-600" title="Some inputs were not saved with this older attempt.">Partial snapshot</span>
                                    @endunless
                                </div>
                            </td>
                            @if ($isOwner)
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="openView({{ $row->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="openView({{ $row->id }})"
                                        class="inline-flex items-center gap-1 rounded-lg border border-brand-200 px-3 py-1.5 text-xs font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60"
                                        aria-label="View attempt from {{ $row->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}"
                                    >
                                        <i class="ri-eye-line" aria-hidden="true" wire:loading.remove wire:target="openView({{ $row->id }})"></i>
                                        <x-spinner wire:loading wire:target="openView({{ $row->id }})" />
                                        View
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + ($isOwner ? 2 : 1) }}" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 bg-white px-4 py-3">{{ $rows->links() }}</div>
    </div>

    @if ($viewing)
        @include('livewire.tables.partials.attempt-snapshot', ['snapshot' => $viewing, 'isLatest' => $viewing['prediction']->id === $latestId])
    @endif
</div>
