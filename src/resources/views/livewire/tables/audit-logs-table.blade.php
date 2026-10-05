<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="w-full sm:max-w-sm">
            <label for="audit-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
            <input id="audit-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Action, name, or IP…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
        </div>
        <div>
            <label for="audit-action-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Action</label>
            <select id="audit-action-{{ $this->getId() }}" wire:model.live="actionFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}">{{ $action }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('created_at')">When</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('action')">Action</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Actor</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Subject</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('ip')">IP</button></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">{{ $row->action }}</td>
                            <td class="px-4 py-3">{{ $row->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3">{{ class_basename((string) $row->subject_type) }} {{ $row->subject_id }}</td>
                            <td class="px-4 py-3">{{ $row->ip }}</td>
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
