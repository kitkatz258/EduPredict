@props(['tone' => 'neutral'])

@php
    $tones = [
        'success' => 'bg-emerald-100 text-emerald-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'danger' => 'bg-red-100 text-red-800',
        'info' => 'bg-indigo-100 text-indigo-800',
        'neutral' => 'bg-gray-100 text-gray-600',
    ];
    $toneClass = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$toneClass]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    <span>{{ $slot }}</span>
</span>
