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
    $descriptionId = $titleId.'-description';
@endphp

{{-- Livewire decides when this exists. Focus stays inside without the Alpine Focus plugin. --}}
<div
    class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto px-4 py-6 sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    @if ($description) aria-describedby="{{ $descriptionId }}" @endif
    x-data="{
        previous: null,
        focusables() {
            const selector = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex=\'-1\'])';
            return [...this.$el.querySelectorAll(selector)].filter((el) => !el.closest('[hidden]') && el.tabIndex !== -1);
        },
        init() {
            this.previous = document.activeElement;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => {
                const field = this.$el.querySelector('input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled])');
                const target = field || this.focusables()[0] || this.$refs.panel;
                target?.focus();
            });
        },
        destroy() {
            document.body.classList.remove('overflow-hidden');
            if (this.previous && typeof this.previous.focus === 'function') {
                this.previous.focus();
            }
        },
        trap(event) {
            const items = this.focusables();
            if (items.length === 0) {
                event.preventDefault();
                this.$refs.panel?.focus();
                return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            const active = document.activeElement;
            if (!this.$el.contains(active)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
                return;
            }
            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    x-on:keydown.tab="trap($event)"
    x-on:keydown.escape.window="$wire.{{ $close }}()"
>
    <div class="fixed inset-0 bg-gray-900/50" wire:click="{{ $close }}" aria-hidden="true"></div>

    <div
        x-ref="panel"
        tabindex="-1"
        {{ $attributes->merge(['class' => 'relative w-full '.($widths[$maxWidth] ?? $widths['2xl']).' max-h-[90vh] overflow-y-auto overscroll-contain rounded-2xl bg-white shadow-xl outline-none']) }}
    >
        <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-brand-200 bg-white px-5 py-4 sm:px-6">
            <div>
                <h2 id="{{ $titleId }}" class="text-lg font-semibold text-brand-900">{{ $title }}</h2>
                @if ($description)
                    <p id="{{ $descriptionId }}" class="mt-1 text-sm text-gray-600">{{ $description }}</p>
                @endif
            </div>
            <button type="button" wire:click="{{ $close }}" class="rounded-lg p-1 text-gray-500 hover:bg-brand-50 hover:text-brand-900" aria-label="Close dialog">
                <i class="ri-close-line text-xl" aria-hidden="true"></i>
            </button>
        </div>
        {{ $slot }}
    </div>
</div>
