@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-2xl font-bold tracking-[-0.02em] text-gray-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
