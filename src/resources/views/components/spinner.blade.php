@props(['label' => null])

<i {{ $attributes->merge(['class' => 'ri-loader-4-line animate-spin motion-reduce:animate-none']) }} aria-hidden="true"></i>
@if ($label)
    <span class="sr-only">{{ $label }}</span>
@endif
