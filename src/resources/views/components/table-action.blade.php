@props([
    'icon',
    'tone' => 'neutral',
])

@php
    $tones = [
        'neutral' => 'border-[#e8eaee] bg-white text-[#111827] hover:bg-[#f3f4f6]',
        'danger' => 'border-red-200 bg-white text-red-800 hover:bg-red-50',
    ];
    $toneClass = $tones[$tone] ?? $tones['neutral'];
@endphp

<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-medium disabled:opacity-50 '.$toneClass]) }}>
    <i class="{{ $icon }} text-sm leading-none" aria-hidden="true"></i>
    <span class="inline-flex items-center gap-1">{{ $slot }}</span>
</button>
