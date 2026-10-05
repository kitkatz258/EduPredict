@props(['prediction' => null, 'compact' => false])

@php
    $shift = $prediction
        ? app(\App\Services\Prediction\ProgramShiftEvaluator::class)->present($prediction)
        : null;
@endphp

@if ($shift)
    @if ($compact)
        <span {{ $attributes->merge(['class' => 'text-xs font-medium text-brand-900']) }}>{{ $shift['label'] }}</span>
    @else
        <div {{ $attributes->merge(['class' => 'mt-4 border-t border-brand-200 pt-4']) }}>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Program-shift indicator</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-900">{{ $shift['label'] }}</span>
                @if ($shift['engagement'])
                    <span class="inline-flex items-center rounded-full border border-brand-200 bg-white px-2.5 py-1 text-xs font-medium text-brand-900">Engagement: {{ $shift['engagement'] }}</span>
                @endif
            </div>
            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $shift['message'] }}</p>
            @if ($shift['factors'] !== [])
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700">
                    @foreach ($shift['factors'] as $factor)
                        <li>{{ $factor['label'] }} — {{ $factor['note'] }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-2 text-xs text-gray-500">Qualitative reading of the factors already in this estimate. It is not a trained model and it has no percentage.</p>
        </div>
    @endif
@endif
