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
    <div class="inline-flex max-w-full flex-wrap gap-1 rounded-xl bg-brand-50 p-1 ring-1 ring-brand-200" role="tablist" aria-labelledby="{{ $labelId }}">
        @foreach ($options as $value => $text)
            <button
                type="button"
                role="tab"
                aria-selected="{{ (string) $current === (string) $value ? 'true' : 'false' }}"
                wire:click="{{ $method }}('{{ $value }}')"
                wire:loading.attr="disabled"
                wire:target="{{ $method }}"
                class="{{ (string) $current === (string) $value ? 'bg-white text-brand-900 shadow-sm ring-1 ring-brand-200' : 'text-gray-700 hover:text-brand-900' }} rounded-lg px-3 py-1.5 text-sm font-medium"
            >{{ $text }}</button>
        @endforeach
    </div>
</div>
