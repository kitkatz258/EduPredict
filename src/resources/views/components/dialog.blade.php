@props([
    'title',
    'close',
    'description' => null,
    'maxWidth' => '2xl',
    'unsaved' => false,
    'discard' => null,
])

@php
    $widths = [
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
    ];
    $titleId = 'dialog-title-'.\Illuminate\Support\Str::slug($title).'-'.substr(md5($title.$close), 0, 6);
    $descriptionId = $titleId.'-description';
@endphp

{{-- Livewire decides when this exists. Focus stays inside without the Alpine Focus plugin. --}}
<div
    data-dialog-close="{{ $close }}"
    @if ($discard) data-dialog-discard="{{ $discard }}" @endif
    data-unsaved="{{ $unsaved ? '1' : '0' }}"
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    @if ($description) aria-describedby="{{ $descriptionId }}" @endif
    x-data="{
        previous: null,
        focusables() {
            const selector = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex=\'-1\'])';
            return [...this.$el.querySelectorAll(selector)].filter((el) => !el.closest('[hidden]') && el.tabIndex !== -1 && !el.hasAttribute('disabled'));
        },
        leaving: false,
        init() {
            this.previous = document.activeElement;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => {
                const field = this.$el.querySelector('input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled])');
                const target = field || this.focusables()[0] || this.$refs.panel;
                target?.focus();
            });
        },
        closeMethod() {
            const host = this.$el?.closest?.('[data-dialog-close]') ?? this.$el;
            if (typeof window.edupredictDialogClose === 'function') {
                return window.edupredictDialogClose(host);
            }
            const method = host?.dataset?.dialogClose ?? '';

            return method !== 'undefined' && /^[A-Za-z_][A-Za-z0-9_]*$/.test(method) ? method : '';
        },
        discardMethod() {
            const method = this.$el?.dataset?.dialogDiscard ?? '';

            return method !== 'undefined' && /^[A-Za-z_][A-Za-z0-9_]*$/.test(method) ? method : '';
        },
        beginLeave(method) {
            if (this.leaving || method === '') {
                return;
            }
            const invoke = () => this.$wire.$call(method);
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                invoke();
                return;
            }
            this.leaving = true;
            setTimeout(invoke, 150);
        },
        requestClose() {
            if (this.leaving) {
                return;
            }
            const discard = this.discardMethod();
            if (this.$el.dataset.unsaved === '1' && discard !== '' && typeof window.confirmAction === 'function') {
                window.confirmAction({
                    title: 'Discard unsaved answers?',
                    text: 'Answers already saved in the draft stay on file. Only answers that have not been saved are discarded.',
                    confirmText: 'Discard unsaved answers',
                    danger: true,
                }).then((ok) => {
                    if (ok) {
                        this.beginLeave(discard);
                    }
                });
                return;
            }
            this.beginLeave(this.closeMethod());
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
    x-on:keydown.escape.window="requestClose()"
>
    <div class="dialog-backdrop fixed inset-0 bg-gray-900/50" x-bind:class="leaving && 'is-leaving'" x-on:click="requestClose()" aria-hidden="true"></div>

    <div
        x-ref="panel"
        tabindex="-1"
        x-bind:class="leaving && 'is-leaving'"
        {{ $attributes->merge(['class' => 'dialog-panel relative flex w-full '.($widths[$maxWidth] ?? $widths['2xl']).' max-h-[calc(100dvh-0.75rem)] flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl outline-none sm:max-h-[min(92dvh,calc(100dvh-2rem))] sm:rounded-2xl']) }}
    >
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-200 bg-white px-5 py-4 sm:px-6">
            <div class="min-w-0 flex-1">
                <h2 id="{{ $titleId }}" class="text-lg font-bold text-brand-900">{{ $title }}</h2>
                @if ($description)
                    <p id="{{ $descriptionId }}" class="mt-1 text-sm text-gray-600">{{ $description }}</p>
                @endif
                @isset($subhead)
                    <div class="mt-3">{{ $subhead }}</div>
                @endisset
            </div>
            <button type="button" x-on:click="requestClose()" class="rounded-lg p-1 text-gray-500 hover:bg-brand-50 hover:text-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400" aria-label="Close dialog">
                <i class="ri-close-line text-xl" aria-hidden="true"></i>
            </button>
        </div>
        <div class="dialog-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain" tabindex="0" role="region" aria-label="Dialog content">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="shrink-0 border-t border-brand-200 bg-white px-5 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
