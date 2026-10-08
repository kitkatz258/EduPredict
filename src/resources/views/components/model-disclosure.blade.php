@props(['version' => null])

@php
    $version = (string) ($version ?: config('edupredict.predictor.placeholder_version', 'placeholder-heuristic-v0'));
    $isPlaceholder = str_starts_with($version, 'placeholder');
@endphp

<p {{ $attributes->merge(['class' => 'text-xs text-gray-500']) }}>
    <i class="ri-information-line mr-1" aria-hidden="true"></i>Model: <span class="font-mono">{{ $version }}</span>
    @if ($isPlaceholder)
        · A placeholder rule-based estimate, not a trained or validated machine-learning model. Scores are not validated probabilities.
    @endif
</p>
