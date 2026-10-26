@props(['constructs' => null, 'unavailable' => 'Not available for this attempt.'])

@php
    $rows = is_array($constructs) ? $constructs : null;
    $hasMean = $rows !== null && collect($rows)->contains(fn (array $row): bool => ($row['presentation'] ?? '') === 'mean');
    $hasPercent = $rows !== null && collect($rows)->contains(fn (array $row): bool => ($row['presentation'] ?? 'percent') === 'percent');
@endphp

<div {{ $attributes }}>
    @if ($rows === null)
        <p class="text-sm text-gray-500">{{ $unavailable }}</p>
    @else
        <ul class="grid auto-rows-fr grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($rows as $construct)
                @if (($construct['presentation'] ?? 'percent') === 'mean')
                    <li @class(['flex h-full flex-col rounded-lg border px-3 py-2', 'border-amber-400 bg-amber-50' => ($construct['context_flag'] ?? null) === true, 'border-brand-200' => ($construct['context_flag'] ?? null) !== true])>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-bold text-brand-900">
                                @if (! empty($construct['code']))<span class="font-mono text-xs font-semibold text-brand-900">{{ $construct['code'] }}</span> @endif
                                {{ $construct['label'] }}
                            </span>
                            <span class="shrink-0 text-right font-mono text-xs font-semibold text-brand-900">
                                {{ $construct['mean'] !== null ? number_format((float) $construct['mean'], 3) : '—' }}
                                @if (! empty($construct['level']))
                                    <span class="block font-sans font-medium text-gray-700">{{ $construct['level'] }}</span>
                                @else
                                    <span class="block font-sans font-normal text-gray-500">Level was not stored</span>
                                @endif
                            </span>
                        </div>
                        @if ($construct['sum'] !== null && $construct['items'])
                            <p class="mt-1 text-xs text-gray-500">Mean {{ rtrim(rtrim(number_format((float) $construct['sum'], 3), '0'), '.') }} ÷ {{ $construct['items'] }}.</p>
                        @endif
                        @if ($construct['readiness_percent'] !== null)
                            <p class="mt-1 text-xs text-gray-600">Readiness {{ number_format((float) $construct['readiness_percent'], 2) }}% from ((mean − 1) ÷ 4) × 100. This is not a chance of being hired.</p>
                        @endif
                        @if (($construct['context_flag'] ?? null) === true)
                            <p class="mt-2 inline-flex rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-950" role="status">Context flag: stored mean is 2.60 or below</p>
                        @elseif (($construct['role'] ?? null) === 'context')
                            <p class="mt-1 text-xs text-gray-500">No context flag. Context only, not a model input.</p>
                        @elseif (($construct['role'] ?? null) === 'descriptive')
                            <p class="mt-1 text-xs text-gray-500">Your own view of employability. Not a model input.</p>
                        @elseif (($construct['role'] ?? null) === 'not_connected')
                            <p class="mt-1 text-xs text-gray-500">Stored for a future employability model. Not sent to the current predictor.</p>
                        @endif
                    </li>
                @else
                    <li @class(['flex h-full flex-col rounded-lg border px-3 py-2', 'border-amber-400 bg-amber-50' => ($construct['context_flag'] ?? null) === true, 'border-brand-200' => ($construct['context_flag'] ?? null) !== true])>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-bold text-brand-900">{{ $construct['label'] }}</span>
                            <span class="font-mono text-xs font-semibold text-brand-900">{{ number_format((float) $construct['score'], 0) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-brand-200/40" aria-hidden="true">
                            <div class="h-full rounded-full bg-brand-400" style="width: {{ $construct['score'] }}%"></div>
                        </div>
                    </li>
                @endif
            @endforeach
        </ul>
        @if ($hasMean)
            <details class="mt-3 rounded-lg border border-brand-200 bg-brand-50/50 px-3 py-2">
                <summary class="cursor-pointer text-xs font-semibold text-brand-900">How section scores are described</summary>
                <p class="mt-2 text-xs leading-5 text-gray-600">Section means use the stored 1–5 answers from this submission. Bands are a descriptive convention, not tested cut-offs. Academic, socioeconomic, and behavioral results are context. Self-perceived employability is descriptive only. Competence and psychological capital are not sent to a trained model. Not a clinical or diagnostic assessment, and not psychometrically validated for UCC CLAS students.</p>
            </details>
        @endif
        @if ($hasPercent)
            <p class="mt-3 text-xs text-gray-500">Self-report category scores on a 0–100 scale, saved with this attempt. Procrastination rises with delaying behavior; the other categories rise with the positive behavior. Not a clinical or diagnostic measure.</p>
        @endif
    @endif
</div>
