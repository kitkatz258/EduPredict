<form id="deletion-request" wire:submit="submit" class="relative rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
    <x-loading-overlay target="submit" label="Sending request…" />
    <h3 class="text-base font-semibold text-brand-900">Request account deletion</h3>
    <p class="mt-1 text-sm text-gray-600">An administrator reviews the request. Your account stays active until it is approved. Approval deactivates the login and keeps prediction history.</p>
    @if ($pending)
        <p class="mt-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">A request is already waiting for review.</p>
    @endif
    @if ($statusMessage !== '')
        <p class="mt-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif
    <div class="mt-4">
        <label for="deletion-reason" class="mb-1 block text-sm font-medium">Reason (optional)</label>
        <textarea id="deletion-reason" wire:model="reason" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" @disabled($pending)></textarea>
        @error('reason') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
    <button type="submit" wire:loading.attr="disabled" wire:target="submit" @disabled($pending) data-confirm="Your login stays active until an administrator approves this request. Prediction history is kept." data-confirm-title="Request account deletion?" data-confirm-button="Submit request" data-confirm-tone="neutral" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-50"><x-spinner wire:loading wire:target="submit" />Submit request</button>
</form>
