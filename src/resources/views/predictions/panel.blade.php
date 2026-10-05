<section class="space-y-6">
    <div class="flex flex-col gap-4 rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-700">
                {{ $student->program?->name }}
                · Year {{ $student->year_level }}
                @if ($latest)
                    · Last updated {{ $latest->created_at?->timezone(config('app.timezone'))->format('M j, Y') }}
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-500">Model: {{ $latest->model_version ?? 'placeholder-heuristic-v0' }}</p>
        </div>
        @if ($showRequest)
            <livewire:student.request-prediction />
        @endif
    </div>

    @if ($latest === null)
        <section class="rounded-2xl border border-brand-200 bg-white p-8 text-center shadow-sm">
            <h2 class="text-lg font-semibold text-brand-900">No prediction yet</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-gray-600">Complete the profile, then request an estimate. Nothing here decides admission, academic standing, employment, or discipline.</p>
        </section>
    @else
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm" aria-labelledby="employability-heading">
                <h2 id="employability-heading" class="sr-only">Employability</h2>
                <x-employability-gauge :score="$latest->employability_score" />
            </section>

            <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm lg:col-span-2" aria-labelledby="wellness-heading">
                <p id="wellness-heading" class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic wellness</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-risk-badge :level="$latest->dropout_risk" />
                    <x-program-shift :prediction="$latest" compact />
                    @if ($latest->confidence === 'low')
                        <x-confidence-tag />
                    @endif
                </div>
                <x-program-shift :prediction="$latest" />
                <p class="mt-4 text-sm leading-6 text-gray-700">{{ $summary['dropout'] }}</p>
                <p class="mt-3 text-sm leading-6 text-gray-700">{{ $summary['employability'] }}</p>
                @if (($forStudent ?? false) && in_array($latest->dropout_risk, ['moderate', 'high'], true))
                    <p class="mt-3 text-sm leading-6 text-gray-700">{{ \App\Services\Interventions\RecommendedActionBuilder::STUDENT_MESSAGE }}</p>
                @endif

                <div class="mt-5">
                    <p class="text-xs font-medium text-gray-600">Estimated dropout probability {{ number_format((float) $latest->dropout_probability * 100, 0) }}%</p>
                    <div class="relative mt-2 h-2 rounded-full bg-gradient-to-r from-green-700 via-amber-500 to-red-700" aria-hidden="true">
                        <span class="absolute top-1/2 h-4 w-1 -translate-y-1/2 rounded bg-brand-900" style="left: {{ max(0, min(100, (float) $latest->dropout_probability * 100)) }}%"></span>
                    </div>
                    <div class="mt-1 flex justify-between text-xs text-gray-600">
                        <span>Low under 30%</span>
                        <span>Moderate under 60%</span>
                        <span>High from 60%</span>
                    </div>
                </div>
            </section>
        </div>

        <section class="grid gap-4 lg:grid-cols-2" aria-labelledby="meaning-heading">
            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
                <h2 id="meaning-heading" class="text-sm font-semibold text-brand-900">What this means</h2>
                <p class="mt-2 text-sm leading-6 text-gray-700">These numbers are a starting point for a conversation about support, skills, and study patterns. They describe patterns in the information on file.</p>
            </div>
            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-brand-900">What this does not mean</h2>
                <p class="mt-2 text-sm leading-6 text-gray-700">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline. The questionnaire is not a clinical or diagnostic assessment.</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2" aria-labelledby="factors-heading">
            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
                <h2 id="factors-heading" class="text-sm font-semibold text-brand-900">Employability factors</h2>
                <p class="mt-1 text-xs text-gray-500">Green bars helped the estimate. Amber bars lowered it.</p>
                <div class="relative mt-4 h-64" wire:ignore>
                    <canvas id="employability-factors-{{ $student->id }}" class="h-full w-full" aria-label="Employability contributing factors" role="img"></canvas>
                </div>
            </div>
            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-brand-900">Dropout-risk factors</h2>
                <p class="mt-1 text-xs text-gray-500">Amber bars are areas where support could help.</p>
                <div class="relative mt-4 h-64" wire:ignore>
                    <canvas id="dropout-factors-{{ $student->id }}" class="h-full w-full" aria-label="Dropout risk contributing factors" role="img"></canvas>
                </div>
            </div>
        </section>

        @if ($summary['top'] !== [])
            <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="top-factors-heading">
                <h2 id="top-factors-heading" class="text-sm font-semibold text-brand-900">Why these scores? Top contributing factors</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ($summary['top'] as $factor)
                        <li class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
                            <p class="text-sm font-medium text-brand-900">{{ $factor['label'] }}</p>
                            <p class="mt-1 text-xs text-gray-600">{{ ($factor['direction'] ?? '') === '+' ? 'Supports this estimate' : 'An area where support could help' }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif

    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="history-heading">
        <h2 id="history-heading" class="text-sm font-semibold text-brand-900">History</h2>
        <p class="mt-1 text-xs text-gray-500">Each request is kept. Earlier results are not replaced.</p>
        <div class="relative mt-4 h-56" wire:ignore>
            <canvas id="prediction-history-{{ $student->id }}" class="h-full w-full" aria-label="Employability and dropout probability over time" role="img"></canvas>
        </div>
        <div class="mt-6">
            <livewire:tables.prediction-history-table :student-id="$student->id" />
        </div>
    </section>

    <p class="text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>

    <script type="application/json" id="prediction-chart-data-{{ $student->id }}">@json($charts)</script>
    <script>
        (function () {
            const boot = function () {
                if (!window.Chart) {
                    return;
                }
                const node = document.getElementById('prediction-chart-data-{{ $student->id }}');
                if (!node || node.dataset.ready === '1') {
                    return;
                }
                node.dataset.ready = '1';
                const data = JSON.parse(node.textContent);
                const bars = function (id, payload) {
                    const canvas = document.getElementById(id);
                    if (!canvas || !payload || !payload.labels || payload.labels.length === 0) {
                        return;
                    }
                    new window.Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: payload.labels,
                            datasets: [{
                                data: payload.magnitudes,
                                backgroundColor: payload.colors,
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: { legend: { display: false } },
                            scales: { x: { beginAtZero: true, ticks: { color: '#374151' } }, y: { ticks: { color: '#1B5E20' } } },
                        },
                    });
                };
                bars('employability-factors-{{ $student->id }}', data.employability);
                bars('dropout-factors-{{ $student->id }}', data.dropout);
                const historyCanvas = document.getElementById('prediction-history-{{ $student->id }}');
                if (historyCanvas && data.history && data.history.labels.length > 0) {
                    new window.Chart(historyCanvas, {
                        type: 'line',
                        data: {
                            labels: data.history.labels,
                            datasets: [
                                { label: 'Employability', data: data.history.employability, borderColor: '#1B5E20', backgroundColor: '#1B5E20', tension: 0.2 },
                                { label: 'Dropout probability (%)', data: data.history.dropout, borderColor: '#B45309', backgroundColor: '#B45309', tension: 0.2 },
                            ],
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: { y: { min: 0, max: 100 } },
                            plugins: { legend: { labels: { color: '#1B5E20' } } },
                        },
                    });
                }
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
</section>
