@props(['constructs' => null, 'unavailable' => 'Not available for this attempt.'])

{{-- Scores are the 0–100 values stored with the attempt; no bands or weights are implied. --}}
<div {{ $attributes }}>
    @if ($constructs === null)
        <p class="text-sm text-gray-500">{{ $unavailable }}</p>
    @else
        <ul class="grid gap-x-6 gap-y-2.5 sm:grid-cols-2">
            @foreach ($constructs as $construct)
                <li>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-gray-800">{{ $construct['label'] }}</span>
                        <span class="font-mono text-xs font-semibold text-brand-900">{{ number_format($construct['score'], 0) }}</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-brand-200/40" aria-hidden="true">
                        <div class="h-full rounded-full bg-brand-400" style="width: {{ $construct['score'] }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-gray-500">Self-report category scores on a 0–100 scale, saved with this attempt. Procrastination rises with delaying behavior; the other categories rise with the positive behavior. Not a clinical or diagnostic measure.</p>
    @endif
</div>
