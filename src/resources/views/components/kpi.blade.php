@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'sand',
    'valueClass' => 'text-gray-900',
])

@php
    $tones = [
        'sand' => 'bg-[#fdf3ea]',
        'mint' => 'bg-[#ecf6ef]',
        'indigo' => 'bg-[#eef2fd]',
        'lilac' => 'bg-[#f6eff6]',
        'rose' => 'bg-[#fdecec]',
        'plain' => 'border border-[#e8eaee] bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]',
    ];
    $toneClass = $tones[$tone] ?? $tones['sand'];
@endphp

<article {{ $attributes->merge(['class' => 'flex h-full flex-col rounded-[14px] border border-black/[0.03] px-5 py-[18px] '.$toneClass]) }}>
    <div class="flex items-center justify-between gap-3 text-sm font-medium text-gray-500">
        <span>{{ $label }}</span>
        @if ($icon)
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-[9px] bg-white/70 text-gray-900">
                <i class="{{ $icon }} text-base leading-none" aria-hidden="true"></i>
            </span>
        @endif
    </div>
    <p class="mt-2.5 text-[28px] font-bold leading-none tracking-[-0.02em] {{ $valueClass }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-3 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    {{ $slot }}
</article>
