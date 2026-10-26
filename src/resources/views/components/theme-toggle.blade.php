@props([
    'label' => null,
])

<button
    type="button"
    data-theme-toggle
    aria-pressed="false"
    aria-label="Switch to dark mode"
    title="Switch to dark mode"
    {{ $attributes->merge(['class' => 'inline-flex h-10 items-center justify-center gap-2 rounded-[11px] border border-[var(--ed-line)] bg-[var(--ed-surface)] px-2.5 text-sm font-medium text-[var(--ed-ink)] hover:bg-[var(--ed-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ed-accent)]']) }}
>
    <i class="ri-moon-line text-lg dark:hidden" aria-hidden="true"></i>
    <i class="ri-sun-line hidden text-lg dark:inline" aria-hidden="true"></i>
    @if ($label)
        <span class="dark:hidden">{{ $label === true ? 'Dark mode' : $label }}</span>
        <span class="hidden dark:inline">{{ $label === true ? 'Light mode' : $label }}</span>
    @else
        <span class="sr-only">Toggle light and dark mode</span>
    @endif
</button>
