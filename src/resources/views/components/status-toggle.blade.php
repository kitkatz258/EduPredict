@props([
    'active' => false,
    'disabled' => false,
    'labelOn' => 'Deactivate',
    'labelOff' => 'Activate',
])

@php
    $label = $active ? $labelOn : $labelOff;
@endphp

<button
    {{ $attributes->merge([
        'type' => 'button',
        'role' => 'switch',
        'aria-checked' => $active ? 'true' : 'false',
        'aria-label' => $label,
        'title' => $label,
        'class' => 'ui-switch'.($active ? ' is-on' : '').($disabled ? ' is-disabled' : ''),
    ]) }}
    @disabled($disabled)
>
    <span class="ui-switch-thumb" aria-hidden="true"></span>
    <span class="sr-only">{{ $label }}</span>
</button>
