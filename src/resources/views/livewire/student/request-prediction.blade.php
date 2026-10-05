<div>
    @error('request')
        <p class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950" role="alert">{{ $message }}</p>
    @enderror

    @if ($blocked)
        <p class="mb-3 text-sm text-gray-700">{{ $blocked }}</p>
    @endif

    <button
        type="button"
        wire:click="request"
        wire:loading.attr="disabled"
        wire:target="request"
        @disabled($blocked)
        class="inline-flex items-center rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-[#2E7D32] focus:outline-none focus:ring-2 focus:ring-brand-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    >
        <span wire:loading.remove wire:target="request">Request new prediction</span>
        <span wire:loading wire:target="request">Preparing your estimate…</span>
    </button>
</div>
