@php
    $reviewedAt = $latest?->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
    $model = (string) ($latest->model_version ?? '');
@endphp

<x-dialog
    :title="$viewing->user?->name ?? 'Student status'"
    close="closeView"
    max-width="4xl"
    :description="trim(($viewing->student_number ?? '').' · '.($viewing->program?->code ?? 'Program').' · '.($yearOptions[$viewing->year_level] ?? 'Year '.$viewing->year_level))"
>
    <div class="space-y-6 px-5 py-5 sm:px-6">
        <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
            Private questionnaire answers and socioeconomic details stay with the student and are not part of this review.
        </p>

        @if ($latest === null)
            <section class="rounded-xl border border-brand-200 p-6 text-center">
                <h3 class="text-base font-semibold text-brand-900">No prediction yet</h3>
                <p class="mt-2 text-sm text-gray-600">Nothing here decides admission, academic standing, employment, or discipline.</p>
            </section>
        @else
            <section aria-labelledby="status-results-heading">
                <h3 id="status-results-heading" class="text-sm font-semibold text-brand-900">Latest estimate</h3>
                <p class="mt-1 text-xs text-gray-500">{{ $reviewedAt }}</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-brand-200 p-4">
                        <p class="text-xs text-gray-500">Employability</p>
                        <p class="mt-1 text-2xl font-semibold text-brand-900">{{ number_format((float) $latest->employability_score, 0) }}<span class="text-sm font-normal text-gray-500"> / 100</span></p>
                    </div>
                    <div class="rounded-xl border border-brand-200 p-4">
                        <p class="text-xs text-gray-500">Dropout classification</p>
                        <div class="mt-2"><x-risk-badge :level="$latest->dropout_risk" /></div>
                    </div>
                    <div class="rounded-xl border border-brand-200 p-4">
                        <p class="text-xs text-gray-500">Confidence</p>
                        <div class="mt-2">
                            @if ($latest->confidence === 'low')
                                <x-confidence-tag />
                            @else
                                <span class="text-sm font-medium text-brand-900">Normal confidence</span>
                            @endif
                        </div>
                    </div>
                </div>
                @if ($summaryText)
                    <p class="mt-3 text-sm leading-6 text-gray-700">{{ $summaryText['employability'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-gray-700">{{ $summaryText['dropout'] }}</p>
                @endif
                <p class="mt-3 text-xs text-gray-500">
                    Model: {{ $model !== '' ? $model : 'not recorded' }}.
                    @if ($model === 'placeholder-heuristic-v0')
                        This is a placeholder rule-based estimate, not a trained model or a validated probability.
                    @endif
                </p>
            </section>

            <section aria-labelledby="status-factors-heading">
                <h3 id="status-factors-heading" class="text-sm font-semibold text-brand-900">Contributing factors</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @foreach (['employability' => 'Employability', 'dropout' => 'Dropout risk'] as $group => $label)
                        <div class="rounded-xl border border-brand-200 p-4">
                            <p class="text-xs font-semibold text-gray-600">{{ $label }}</p>
                            @if (($factorGroups[$group] ?? []) === [])
                                <p class="mt-2 text-sm text-gray-500">No factors were stored with this estimate.</p>
                            @else
                                <ul class="mt-2 space-y-1.5 text-sm">
                                    @foreach (array_slice($factorGroups[$group], 0, 6) as $factor)
                                        <li class="flex items-start gap-2">
                                            @if (($factor['direction'] ?? '') === '+')
                                                <i class="ri-arrow-up-line mt-0.5 text-brand-900" aria-hidden="true"></i>
                                                <span class="text-gray-800">{{ $factor['label'] }} <span class="sr-only">(supports this estimate)</span></span>
                                            @else
                                                <i class="ri-arrow-down-line mt-0.5 text-amber-700" aria-hidden="true"></i>
                                                <span class="text-gray-800">{{ $factor['label'] }} <span class="sr-only">(an area where support could help)</span></span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <x-program-shift :prediction="$latest" />

            <livewire:staff.recommended-actions :student-id="$viewing->id" :key="'dept-actions-'.$viewing->id" />
        @endif

        <p class="text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
    </div>
</x-dialog>
