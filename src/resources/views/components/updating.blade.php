@props(['target' => null, 'label' => 'Updating the list…'])

<p
    wire:loading
    @if ($target) wire:target="{{ $target }}" @endif
    {{ $attributes->merge(['class' => 'mb-2 text-sm text-gray-500']) }}
    role="status"
>{{ $label }}</p>
