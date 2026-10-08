@props([
    'title',
    'close',
    'description' => null,
    'maxWidth' => '2xl',
])

@php
    $widths = [
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '4xl' => 'sm:max-w-4xl',
    ];
    $titleId = 'dialog-title-'.\Illuminate\Support\Str::slug($title).'-'.substr(md5($title.$close), 0, 6);
@endphp

{{-- Render inside an @if so Livewire controls visibility. Escape and the backdrop call the close action. --}}
<div
    class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto px-4 py-6 sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    x-data
    x-trap.noscroll="true"
    x-on:keydown.escape.window="$wire.{{ $close }}()"
>
    <div class="fixed inset-0 bg-gray-900/50" wire:click="{{ $close }}" aria-hidden="true"></div>

    <div {{ $attributes->merge(['class' => 'relative w-full '.($widths[$maxWidth] ?? $widths['2xl']).' max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl']) }}>
        <div class="flex items-start justify-between gap-4 border-b border-brand-200 px-5 py-4 sm:px-6">
            <div>
                <h2 id="{{ $titleId }}" class="text-lg font-semibold text-brand-900">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-1 text-sm text-gray-600">{{ $description }}</p>
                @endif
            </div>
            <button type="button" wire:click="{{ $close }}" class="rounded-lg p-1 text-gray-500 hover:bg-brand-50 hover:text-brand-900" aria-label="Close dialog">
                <i class="ri-close-line text-xl" aria-hidden="true"></i>
            </button>
        </div>
        {{ $slot }}
    </div>
</div>
