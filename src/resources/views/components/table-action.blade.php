@props([
    'icon',
    'tone' => 'neutral',
    'label' => null,
])

@php
    $text = trim(strip_tags((string) $slot));
    $accessible = $label ?: ($text !== '' ? $text : 'Action');
    $toneClass = match ($tone) {
        'danger' => 'is-danger',
        'success' => 'is-success',
        default => '',
    };
@endphp

<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'ui-icon-btn '.$toneClass,
    'aria-label' => $accessible,
    'title' => $accessible,
]) }}>
    <i class="{{ $icon }} text-base leading-none" aria-hidden="true"></i>
    <span class="sr-only">{{ $accessible }}</span>
</button>
