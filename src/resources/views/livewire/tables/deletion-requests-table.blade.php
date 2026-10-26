<div>
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="w-full sm:max-w-sm">
            <label for="deletion-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
            <input id="deletion-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Name, email, or reason…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
        </div>
        <div>
            <label for="deletion-status-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Request status</label>
            <select id="deletion-status-{{ $this->getId() }}" wire:model.live="statusFilter" class="ui-field w-auto">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="">All</option>
            </select>
            <p class="mt-1 max-w-sm text-xs text-gray-500">Approval deactivates sign-in. Grades, predictions, and other stored records are kept.</p>
        </div>
    </div>
    <x-updating />
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">When</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Account</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Reason</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                <span class="block font-medium text-brand-900">{{ $row->user?->name }}</span>
                                <span class="text-gray-600">{{ $row->user?->email }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $row->reason ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $statusTone = match ($row->status) {
                                        'pending' => 'bg-amber-50 text-amber-900 ring-amber-200',
                                        'approved' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
                                        'rejected' => 'bg-red-50 text-red-900 ring-red-200',
                                        default => 'bg-gray-50 text-gray-700 ring-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $statusTone }}">{{ ucfirst($row->status) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <x-table-action icon="ri-file-search-line" wire:click="openReview({{ $row->id }})">Review</x-table-action>
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

    @if ($reviewing)
        <x-dialog title="Review account deletion request" close="closeReview" description="Approval deactivates sign-in. Prediction history stays stored.">
            <div class="space-y-4 px-5 py-5 sm:px-6">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Account</dt>
                        <dd class="font-medium text-brand-900">{{ $reviewing->user?->name }}</dd>
                        <dd class="text-gray-600">{{ $reviewing->user?->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd class="font-medium text-brand-900">{{ ucfirst($reviewing->status) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Reason</dt>
                        <dd class="text-gray-800">{{ $reviewing->reason ?: 'No reason was provided.' }}</dd>
                    </div>
                </dl>
                @if ($reviewing->status === 'pending')
                    <div>
                        <label for="deletion-note-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Note</label>
                        <textarea id="deletion-note-{{ $this->getId() }}" wire:model="adminNote" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
                        @error('adminNote') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                @elseif ($reviewing->admin_note)
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900">{{ $reviewing->admin_note }}</p>
                @endif
            </div>
            <x-slot:footer>
                @if ($reviewing->status === 'pending')
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" x-on:click="requestClose()" class="ui-btn ui-btn-secondary">Cancel</button>
                        <button type="button" wire:click="reject({{ $reviewing->id }})" wire:loading.attr="disabled" wire:target="reject,approve" data-confirm="The account stays active." data-confirm-title="Reject this deletion request?" data-confirm-button="Reject" data-confirm-tone="neutral" class="ui-btn ui-btn-secondary">Reject</button>
                        <button type="button" wire:click="approve({{ $reviewing->id }})" wire:loading.attr="disabled" wire:target="approve,reject" data-confirm="The account can no longer sign in. Prediction history is kept." data-confirm-title="Deactivate this account?" data-confirm-button="Approve" class="ui-btn ui-btn-primary">
                            <x-spinner wire:loading wire:target="approve" />
                            Approve
                        </button>
                    </div>
                @else
                    <div class="flex justify-end">
                        <button type="button" x-on:click="requestClose()" class="ui-btn ui-btn-secondary">Close</button>
                    </div>
                @endif
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
