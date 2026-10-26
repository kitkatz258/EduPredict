<div class="relative">
    <div wire:loading.flex wire:target="request" class="fixed inset-0 z-[70] items-center justify-center bg-white/70 backdrop-blur-sm" role="status" aria-live="assertive">
        <div class="rounded-2xl border border-brand-200 bg-white px-6 py-5 text-center shadow-lg">
            <p class="text-sm font-semibold text-brand-900">Preparing your estimate…</p>
            <p class="mt-1 max-w-xs text-xs text-gray-600">This request is in progress. Wait until it finishes before requesting again.</p>
        </div>
    </div>

    @error('request')
        <p class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950" role="alert">{{ $message }}</p>
    @enderror

    @if ($blocked)
        <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950" role="status">{{ $blocked }}</p>
    @endif

    <p class="mb-3 text-sm text-gray-700" aria-live="polite">
        <span class="font-semibold text-brand-900">{{ $quota['remaining'] }}</span>
        of {{ $quota['limit'] }} predictions left today
        <span class="text-gray-500">(Philippine date, resets {{ $quota['resets_at']->timezone($quota['timezone'])->format('M j, g:i A') }})</span>
        @if ($quota['extra'] > 0)
            <span class="mt-1 block text-xs text-gray-600">Includes {{ $quota['extra'] }} administrator-granted extra {{ \Illuminate\Support\Str::plural('attempt', $quota['extra']) }}. The {{ $quota['base_limit'] }} regular predictions are still counted.</span>
        @endif
    </p>

    <button
        type="button"
        wire:click="request"
        wire:loading.attr="disabled"
        wire:target="request"
        @disabled($blocked || $submitting)
        data-confirm="This saves a new attempt and uses one of today's predictions. Earlier attempts stay in History."
        data-confirm-title="Request a prediction?"
        data-confirm-button="Request prediction"
        data-confirm-tone="neutral"
        class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-[#1f2937] focus:outline-none focus:ring-2 focus:ring-brand-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    >
        <span wire:loading.remove wire:target="request">Request new prediction</span>
        <x-spinner wire:loading wire:target="request" />
        <span wire:loading wire:target="request">Preparing your estimate…</span>
    </button>
</div>
