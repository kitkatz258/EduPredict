<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="w-full sm:max-w-sm">
                    <label for="grant-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                    <input id="grant-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Student number, name, or reason…" class="ui-field">
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label for="grant-per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
                    <select id="grant-per-page-{{ $this->getId() }}" wire:model.live="perPage" class="ui-field w-auto">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="$dispatch('add-prediction-grant')" class="ui-btn ui-btn-primary">
                        <i class="ri-add-line" aria-hidden="true"></i>Grant slots
                    </button>
                </div>
            </div>
            <x-updating />
        </x-slot:toolbar>

        <thead>
            <tr>
                <th>Granted</th>
                <th>Student</th>
                <th><button type="button" wire:click="sortBy('manila_date')" class="inline-flex items-center gap-1">Manila date</button></th>
                <th><button type="button" wire:click="sortBy('extra_attempts')" class="inline-flex items-center gap-1">Extra slots</button></th>
                <th>Granted by</th>
                <th>Justification</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="whitespace-nowrap">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                    <td>
                        <span class="block font-medium text-gray-900">{{ $row->student?->user?->name ?: '—' }}</span>
                        <span class="text-xs text-gray-500">{{ $row->student?->student_number }}</span>
                    </td>
                    <td>{{ $row->manila_date?->toDateString() }}</td>
                    <td>{{ $row->extra_attempts }}</td>
                    <td>{{ $row->grantor?->name ?: '—' }}</td>
                    <td>{{ $row->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>

        <x-slot:footer>
            {{ $rows->links() }}
        </x-slot:footer>
    </x-data-table>
</div>
