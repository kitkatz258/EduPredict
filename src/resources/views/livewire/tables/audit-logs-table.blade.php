<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                    <div class="grid w-full gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label for="audit-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                            <input id="audit-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Action, name, or IP…" class="ui-field">
                        </div>
                        <div>
                            <label for="audit-action-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Action</label>
                            <select id="audit-action-{{ $this->getId() }}" wire:model.live="actionFilter" class="ui-field">
                                <option value="">All</option>
                                @foreach ($actions as $action)
                                    <option value="{{ $action }}">{{ \App\Support\AuditRecordLabel::action($action) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="audit-actor-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Performed by</label>
                            <select id="audit-actor-{{ $this->getId() }}" wire:model.live="actorFilter" class="ui-field">
                                <option value="">All</option>
                                @foreach ($actors as $actor)
                                    <option value="{{ $actor->id }}">{{ $actor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="audit-start-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Start date</label>
                                <input id="audit-start-{{ $this->getId() }}" type="date" wire:model.live="startDate" class="ui-field">
                            </div>
                            <div>
                                <label for="audit-end-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">End date</label>
                                <input id="audit-end-{{ $this->getId() }}" type="date" wire:model.live="endDate" class="ui-field">
                            </div>
                        </div>
                    </div>
                    <button type="button" wire:click="export" class="ui-btn ui-btn-secondary">
                        <i class="ri-download-2-line" aria-hidden="true"></i>Export CSV
                    </button>
                </div>
                <p class="text-xs text-gray-500">Private questionnaire and household contents are not included in this log or export.</p>
            </div>
            <x-updating />
        </x-slot:toolbar>
        <thead>
            <tr>
                <th><button type="button" wire:click="sortBy('created_at')">Date &amp; time</button></th>
                <th><button type="button" wire:click="sortBy('action')">Action</button></th>
                <th>Performed by</th>
                <th>Affected record</th>
                <th><button type="button" wire:click="sortBy('ip')">IP address</button></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="whitespace-nowrap">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                    <td>{{ \App\Support\AuditRecordLabel::action($row->action) }}</td>
                    <td>{{ \App\Support\AuditRecordLabel::actor($row) }}</td>
                    <td>{{ \App\Support\AuditRecordLabel::affected($row) }}</td>
                    <td>{{ $row->ip ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-data-table>
</div>
