@props(['score' => null, 'compact' => false])

@php
    $value = $score === null ? null : max(0, min(100, (float) $score));
    $radius = 42;
    $circumference = 2 * M_PI * $radius;
    $offset = $value === null ? $circumference : $circumference * (1 - ($value / 100));
    $band = $value === null ? '' : ($value >= 75 ? 'Good' : ($value >= 50 ? 'Fair' : 'Needs support'));
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center']) }}>
    @unless ($compact)
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Employability score</p>
    @endunless
    <div class="relative {{ $compact ? 'h-28 w-28' : 'mt-4 h-36 w-36' }}" role="img" aria-label="{{ $value === null ? 'No employability score yet' : 'Employability score '.number_format($value, 0).' out of 100'.($band !== '' ? ', '.$band : '') }}">
        <svg viewBox="0 0 120 120" class="h-full w-full" aria-hidden="true">
            <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="#E8F5E9" stroke-width="10" />
            <circle
                cx="60"
                cy="60"
                r="{{ $radius }}"
                fill="none"
                stroke="#1B5E20"
                stroke-width="10"
                stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
                transform="rotate(-90 60 60)"
            />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="{{ $compact ? 'text-2xl' : 'text-3xl' }} font-semibold text-brand-900">{{ $value === null ? '—' : number_format($value, 0) }}</span>
            <span class="text-xs text-gray-500">out of 100</span>
        </div>
    </div>
    @if ($band !== '')
        <p class="{{ $compact ? 'mt-2 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs' : 'mt-2 text-sm' }} font-medium text-brand-900">{{ $band }}</p>
    @endif
    @unless ($compact)
        <p class="mt-1 max-w-xs text-xs text-gray-500">Based on academics, skills, experience, and behavioral factors.</p>
    @endunless
</div>
