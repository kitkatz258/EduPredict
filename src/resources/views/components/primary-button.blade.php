@props(['target' => null, 'icon' => null])

<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'group inline-flex min-h-10 items-center justify-center gap-2 rounded-[11px] bg-[#111827] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#1f2937] focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-600/15 disabled:cursor-not-allowed disabled:opacity-60']) }}
    @if ($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif
>
    @if ($target)
        <x-spinner wire:loading wire:target="{{ $target }}" />
        @if ($icon)
            <i class="{{ $icon }}" wire:loading.remove wire:target="{{ $target }}" aria-hidden="true"></i>
        @endif
    @else
        <x-spinner class="hidden group-aria-busy:inline-block" />
        @if ($icon)
            <i class="{{ $icon }} group-aria-busy:hidden" aria-hidden="true"></i>
        @endif
    @endif
    {{ $slot }}
</button>
