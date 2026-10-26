<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="w-full sm:max-w-sm">
                        <label for="intervention-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                        <input id="intervention-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search interventions…" class="ui-field">
                    </div>
                    <div>
                        <label for="intervention-risk-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Eligible from risk level</label>
                        <select id="intervention-risk-{{ $this->getId() }}" wire:model.live="riskFilter" class="ui-field w-auto">
                            <option value="">All levels</option>
                            <option value="low">Low</option>
                            <option value="moderate">Moderate</option>
                            <option value="high">High</option>
                        </select>
                        <p class="mt-1 max-w-xs text-xs text-gray-500">Moderate applies to Moderate and High. High applies only to High. The rule engine still chooses from relevant factors. Stored actions on earlier predictions stay as they were.</p>
                    </div>
                </div>
                <button type="button" wire:click="$dispatch('add-intervention')" class="ui-btn ui-btn-primary">
                    <i class="ri-add-line" aria-hidden="true"></i>Add intervention
                </button>
            </div>
            <x-updating />
        </x-slot:toolbar>
        <thead>
            <tr>
                <th><button type="button" wire:click="sortBy('code')">Code</button></th>
                <th><button type="button" wire:click="sortBy('title')">Intervention</button></th>
                <th><button type="button" wire:click="sortBy('min_risk_level')">Eligible from risk level</button></th>
                <th>Status</th>
                <th>Factors</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->code }}</td>
                    <td>{{ $row->title }}</td>
                    <td>{{ ucfirst($row->min_risk_level) }}</td>
                    <td>{{ $row->is_active ? 'Active' : 'Inactive' }}</td>
                    <td>{{ \App\Support\CommaList::display($row->targets_factor) }}</td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-table-action icon="ri-eye-line" wire:click="viewIntervention({{ $row->id }})">View</x-table-action>
                            <x-table-action icon="ri-pencil-line" wire:click="editIntervention({{ $row->id }})">Edit</x-table-action>
                            <x-status-toggle
                                :active="$row->is_active"
                                wire:click="setInterventionActive({{ $row->id }})"
                                :data-confirm="$row->is_active
                                    ? 'This intervention stays on existing predictions. It will not be chosen for a new prediction.'
                                    : 'This intervention can be selected again for new predictions. Existing stored actions are not rewritten.'"
                                :data-confirm-title="$row->is_active ? 'Deactivate this intervention?' : 'Activate this intervention?'"
                                :data-confirm-button="$row->is_active ? 'Deactivate' : 'Activate'"
                                :data-confirm-tone="$row->is_active ? 'danger' : 'neutral'"
                            />
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-data-table>
</div>
