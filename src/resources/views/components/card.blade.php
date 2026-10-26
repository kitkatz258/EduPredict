@props(['title' => null])

<section {{ $attributes->merge(['class' => 'rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]']) }}>
    @if ($title || isset($action))
        <div class="mb-4 flex items-start justify-between gap-3">
            @if ($title)
                <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
            @endif
            @isset($action)
                {{ $action }}
            @endisset
        </div>
    @endif
    {{ $slot }}
</section>
