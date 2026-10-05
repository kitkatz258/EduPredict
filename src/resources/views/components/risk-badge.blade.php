@props(['level' => 'low'])

@php
    $styles = [
        'low' => ['label' => 'Low risk', 'class' => 'border-green-700 bg-green-50 text-green-800'],
        'moderate' => ['label' => 'Moderate risk', 'class' => 'border-amber-600 bg-amber-50 text-amber-950'],
        'high' => ['label' => 'High risk', 'class' => 'border-red-700 bg-red-50 text-red-800'],
    ];
    $style = $styles[$level] ?? ['label' => 'Unknown risk', 'class' => 'border-gray-400 bg-white text-gray-800'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold '.$style['class']]) }}>
    @if ($level === 'low')
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l3.1 3.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd" /></svg>
    @elseif ($level === 'moderate')
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.5 3.5a1.8 1.8 0 013 0l6.2 10.8A1.8 1.8 0 0116.2 17H3.8a1.8 1.8 0 01-1.5-2.7L8.5 3.5zM10 7a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
    @else
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 6a1 1 0 012 0v5a1 1 0 01-2 0V6zm1 9a1.2 1.2 0 100-2.4A1.2 1.2 0 0010 15z" clip-rule="evenodd" /></svg>
    @endif
    <span>{{ $style['label'] }}</span>
</span>
