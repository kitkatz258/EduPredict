@props(['target' => null, 'label' => 'Working…'])

{{-- Place inside a `relative` container. Covers it while the Livewire action runs. --}}
<div
    wire:loading.flex
    @if ($target) wire:target="{{ $target }}" @endif
    {{ $attributes->merge(['class' => 'absolute inset-0 z-20 flex-col items-center justify-center gap-2 rounded-2xl bg-white/75 text-sm font-medium text-brand-900 backdrop-blur-[1px]']) }}
    role="status"
    aria-live="polite"
>
    <x-spinner class="text-2xl" />
    <span>{{ $label }}</span>
</div>
