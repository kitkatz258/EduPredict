<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="w-full sm:max-w-sm">
            <label for="deletion-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
            <input id="deletion-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Name, email, or reason…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
        </div>
        <div>
            <label for="deletion-status-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Status</label>
            <select id="deletion-status-{{ $this->getId() }}" wire:model.live="statusFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>
    <div class="mb-4">
        <label for="deletion-note-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Note for the next decision</label>
        <textarea id="deletion-note-{{ $this->getId() }}" wire:model="adminNote" rows="2" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
        @error('adminNote') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">When</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Account</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Reason</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Decision</th>
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
                            <td class="px-4 py-3">{{ ucfirst($row->status) }}</td>
                            <td class="px-4 py-3">
                                @if ($row->status === 'pending')
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="approve({{ $row->id }})" data-confirm="The account can no longer sign in. Prediction history is kept." data-confirm-title="Deactivate this account?" data-confirm-button="Approve" class="rounded-lg bg-brand-900 px-3 py-1.5 text-xs font-medium text-white">Approve</button>
                                        <button type="button" wire:click="reject({{ $row->id }})" data-confirm="The account stays active." data-confirm-title="Reject this deletion request?" data-confirm-button="Reject" data-confirm-tone="neutral" class="rounded-lg border border-brand-200 px-3 py-1.5 text-xs font-medium text-brand-900">Reject</button>
                                    </div>
                                @else
                                    <span class="text-gray-600">{{ $row->admin_note }}</span>
                                @endif
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
