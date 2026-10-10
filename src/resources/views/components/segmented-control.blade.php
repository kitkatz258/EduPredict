@props([
    'label',
    'options',
    'current',
    'method',
])

@php
    $labelId = 'segment-'.substr(md5($label.$method), 0, 8);
@endphp

<div>
    <p id="{{ $labelId }}" class="mb-1 text-sm font-medium text-gray-700">{{ $label }}</p>
    <div
        class="relative inline-flex max-w-full rounded-xl bg-brand-50 p-1 ring-1 ring-brand-200"
        x-data="segmentedTrack()"
        x-on:keydown.right.prevent="focusNext(1)"
        x-on:keydown.left.prevent="focusNext(-1)"
    >
        <div x-ref="track" class="relative inline-flex max-w-full flex-wrap gap-1" role="tablist" aria-labelledby="{{ $labelId }}">
            <span
                x-ref="indicator"
                aria-hidden="true"
                class="segment-indicator pointer-events-none absolute left-0 top-0 rounded-lg bg-white shadow-sm ring-1 ring-brand-200 motion-reduce:transition-none"
                x-bind:class="ready ? 'opacity-100' : 'opacity-0'"
                x-bind:style="`width: ${width}px; height: ${height}px; transform: translate(${left}px, ${top}px);`"
            ></span>
            @foreach ($options as $value => $text)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ (string) $current === (string) $value ? 'true' : 'false' }}"
                    wire:click="{{ $method }}('{{ $value }}')"
                    wire:loading.attr="disabled"
                    wire:target="{{ $method }}"
                    x-on:click="select($el)"
                    class="{{ (string) $current === (string) $value ? 'bg-white text-brand-900 shadow-sm ring-1 ring-brand-200' : 'text-gray-700 hover:text-brand-900' }} relative z-10 rounded-lg px-3 py-1.5 text-sm font-medium"
                    x-bind:class="ready ? 'bg-transparent shadow-none ring-transparent' : ''"
                >{{ $text }}</button>
            @endforeach
        </div>
    </div>
    <p wire:loading wire:target="{{ $method }}" class="mt-1 text-xs text-gray-500" role="status">Updating…</p>
</div>
