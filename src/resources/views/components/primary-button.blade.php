@props(['target' => null, 'icon' => null])

<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'group inline-flex items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-[#2E7D32] focus:outline-none focus:ring-2 focus:ring-brand-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60']) }}
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
