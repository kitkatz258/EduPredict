@php
    $tz = config('app.timezone');
    $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
    $moderateAt = (int) round((float) config('edupredict.predictor.dropout_moderate_at', 0.30) * 100);
    $highAt = (int) round((float) config('edupredict.predictor.dropout_high_at', 0.60) * 100);
    $riskIndex = $latest ? max(0, min(100, (float) $latest->dropout_probability * 100)) : null;
    $gradesDate = $gradesConfirmedAt ? \Illuminate\Support\Carbon::parse($gradesConfirmedAt)->timezone($tz)->format('M j, Y') : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">University of Caloocan City · EduPredict</p>
                <h2 class="mt-1 text-2xl font-semibold text-brand-900">Hi, {{ $firstName }}. Here's your latest snapshot.</h2>
                <p class="mt-1 text-sm text-gray-600">
                    @if ($student?->program)
                        {{ $student->program->name }} · Year {{ $student->year_level }}
                    @endif
                    @if ($latest)
                        · Latest assessment {{ $latest->created_at?->timezone($tz)->format('M j, Y') }}
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($student === null)
            <section class="rounded-2xl border border-brand-200 bg-white p-8 text-center shadow-sm">
                <p class="text-sm text-gray-700">Your student record is not available yet.</p>
            </section>
        @else
            @if ($progress)
                <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="dashboard-progress">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p id="dashboard-progress" class="text-sm text-gray-700">Assessment workflow progress — saved sections, not a prediction-accuracy score</p>
                        <div class="flex items-center gap-3">
                            <p class="text-sm font-semibold text-brand-900">{{ $progress['percent'] }}%</p>
                            <a href="{{ route('student.assessment') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-900 hover:underline">Update assessment<i class="ri-arrow-right-s-line" aria-hidden="true"></i></a>
                        </div>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-brand-50" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-labelledby="dashboard-progress">
                        <div class="h-full rounded-full bg-brand-400" style="width: {{ $progress['percent'] }}%"></div>
                    </div>
                </section>
            @endif

            @if ($latest === null)
                <section class="rounded-2xl border border-brand-200 bg-white p-8 text-center shadow-sm" aria-labelledby="no-prediction-heading">
                    <i class="ri-line-chart-line text-3xl text-brand-400" aria-hidden="true"></i>
                    <h2 id="no-prediction-heading" class="mt-2 text-lg font-semibold text-brand-900">No prediction yet</h2>
                    <p class="mx-auto mt-2 max-w-lg text-sm text-gray-600">Save the required Assessment sections, then run a prediction. Your results, contributing factors, and career matches will appear here.</p>
                </section>
            @else
                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm" aria-label="Employability">
                        <x-employability-gauge :score="$latest->employability_score" />
                    </section>

                    <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm lg:col-span-2" aria-labelledby="wellness-heading">
                        <p id="wellness-heading" class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic wellness</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <x-risk-badge :level="$latest->dropout_risk" />
                            @if ($latest->confidence === 'low')
                                <x-confidence-tag />
                            @else
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-700">Normal confidence</span>
                            @endif
                        </div>
                        <p class="mt-4 text-sm leading-6 text-gray-700">{{ $summary['dropout'] }}</p>
                        @if (in_array($latest->dropout_risk, ['moderate', 'high'], true))
                            <p class="mt-3 text-sm leading-6 text-gray-700">{{ \App\Services\Interventions\RecommendedActionBuilder::STUDENT_MESSAGE }}</p>
                        @endif

                        <x-program-shift :prediction="$latest" />

                        <div class="mt-5 border-t border-brand-200 pt-4">
                            <p class="text-xs font-medium text-gray-600">Placeholder dropout-risk index: {{ number_format($riskIndex, 0) }} / 100</p>
                            <div class="relative mt-2 h-2 rounded-full bg-gradient-to-r from-green-700 via-amber-500 to-red-700" aria-hidden="true">
                                <span class="absolute top-1/2 h-4 w-1 -translate-y-1/2 rounded bg-brand-900" style="left: {{ $riskIndex }}%"></span>
                            </div>
                            <div class="mt-1 flex justify-between text-xs text-gray-600">
                                <span>Low under {{ $moderateAt }}</span>
                                <span>Moderate {{ $moderateAt }}–{{ $highAt - 1 }}</span>
                                <span>High from {{ $highAt }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">This index comes from transparent placeholder rules. It is not a validated probability from a trained model.</p>
                        </div>
                    </section>
                </div>

                <details class="group rounded-2xl border border-brand-200 bg-white shadow-sm" @if ($summary['top'] !== []) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-brand-900"><i class="ri-book-open-line" aria-hidden="true"></i>Why these scores? Top contributing factors</span>
                        <i class="ri-arrow-down-s-line text-xl text-gray-500 transition group-open:rotate-180" aria-hidden="true"></i>
                    </summary>
                    <div class="space-y-5 border-t border-brand-200 px-5 py-5">
                        @if ($summary['top'] !== [])
                            <ul class="grid gap-3 sm:grid-cols-3">
                                @foreach ($summary['top'] as $factor)
                                    <li class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
                                        <p class="text-sm font-medium text-brand-900">{{ $factor['label'] }}</p>
                                        <p class="mt-1 text-xs text-gray-600">{{ ($factor['direction'] ?? '') === '+' ? 'Supports this estimate' : 'An area where support could help' }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <p class="text-sm leading-6 text-gray-700">{{ $summary['employability'] }}</p>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <h3 class="text-sm font-semibold text-brand-900">Employability factors</h3>
                                <p class="mt-1 text-xs text-gray-500">Green bars helped the estimate. Amber bars lowered it.</p>
                                <div class="relative mt-3 h-60" wire:ignore>
                                    <canvas id="dashboard-employability-factors" class="h-full w-full" aria-label="Employability contributing factors" role="img"></canvas>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-brand-900">Dropout-risk factors</h3>
                                <p class="mt-1 text-xs text-gray-500">Amber bars are areas where support could help.</p>
                                <div class="relative mt-3 h-60" wire:ignore>
                                    <canvas id="dashboard-dropout-factors" class="h-full w-full" aria-label="Dropout risk contributing factors" role="img"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-brand-200 p-4">
                                <h3 class="text-sm font-semibold text-brand-900">What this means</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-700">These numbers are a starting point for a conversation about support, skills, and study patterns. They describe patterns in the information on file.</p>
                            </div>
                            <div class="rounded-xl border border-brand-200 p-4">
                                <h3 class="text-sm font-semibold text-brand-900">What this does not mean</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-700">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline. The questionnaire is not a clinical or diagnostic assessment.</p>
                            </div>
                        </div>
                    </div>
                </details>
            @endif

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Assessment snapshot">
                <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                    <i class="ri-book-2-line text-brand-400" aria-hidden="true"></i>
                    <p class="mt-1 text-2xl font-semibold text-brand-900">{{ $academic && $academic['rounded_gwa'] !== null ? number_format($academic['rounded_gwa'], 2) : '—' }}</p>
                    <p class="text-sm text-gray-600">GWA</p>
                    <p class="text-xs text-gray-500">
                        @if ($academic && $academic['rounded_gwa'] !== null)
                            {{ $academic['gwa_provisional'] ? 'Provisional · incomplete subjects left out' : $academic['semesters_completed'].' '.\Illuminate\Support\Str::plural('semester', $academic['semesters_completed']).' on file' }}
                        @else
                            No confirmed grades yet
                        @endif
                    </p>
                </div>
                <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                    <i class="ri-award-line text-brand-400" aria-hidden="true"></i>
                    <p class="mt-1 text-2xl font-semibold text-brand-900">{{ $skillsLogged }}</p>
                    <p class="text-sm text-gray-600">Skills logged</p>
                    <p class="text-xs text-gray-500">Skills, certifications, experience</p>
                </div>
                <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                    <i class="ri-survey-line text-brand-400" aria-hidden="true"></i>
                    <p class="mt-1 text-2xl font-semibold text-brand-900">{{ ($progress['sections']['questionnaire'] ?? false) ? 'Done' : 'Not yet' }}</p>
                    <p class="text-sm text-gray-600">Questionnaire</p>
                    <p class="text-xs text-gray-500">{{ ($progress['sections']['questionnaire'] ?? false) ? 'Submitted' : 'Not submitted' }}</p>
                </div>
                <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                    <i class="ri-calendar-line text-brand-400" aria-hidden="true"></i>
                    <p class="mt-1 text-2xl font-semibold text-brand-900">{{ $latest?->created_at?->timezone($tz)->format('M j') ?? '—' }}</p>
                    <p class="text-sm text-gray-600">Latest assessment</p>
                    <p class="text-xs text-gray-500">{{ $latest?->created_at?->diffForHumans() ?? 'No prediction yet' }}</p>
                </div>
            </section>

            <livewire:student.career-matches />

            <section aria-labelledby="next-steps-heading">
                <h2 id="next-steps-heading" class="text-xs font-semibold uppercase tracking-wide text-gray-500">Next steps</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <a href="{{ route('student.assessment') }}" class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm hover:bg-brand-50">
                        <p class="inline-flex items-center gap-2 font-semibold text-brand-900"><i class="ri-survey-line" aria-hidden="true"></i>Update Assessment</p>
                        <p class="mt-1 text-sm text-gray-600">Edit only the section you need. Saved sections stay saved.</p>
                    </a>
                    <a href="{{ route('student.assessment', ['step' => 'grades']) }}" class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm hover:bg-brand-50">
                        <p class="inline-flex items-center gap-2 font-semibold text-brand-900"><i class="ri-file-list-3-line" aria-hidden="true"></i>Update Grades</p>
                        <p class="mt-1 text-sm text-gray-600">{{ $gradesDate ? 'Latest confirmed '.$gradesDate : 'No confirmed grades yet' }}</p>
                    </a>
                    <a href="{{ $latest ? '#career-matches' : route('student.assessment') }}" class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm hover:bg-brand-50">
                        <p class="inline-flex items-center gap-2 font-semibold text-brand-900"><i class="ri-compass-3-line" aria-hidden="true"></i>View Career Details</p>
                        <p class="mt-1 text-sm text-gray-600">{{ $latest ? 'Open a match above for skills and details.' : 'Available after your first prediction.' }}</p>
                    </a>
                    <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                        <p class="inline-flex items-center gap-2 font-semibold text-brand-900"><i class="ri-refresh-line" aria-hidden="true"></i>Run a new prediction</p>
                        <p class="mb-3 mt-1 text-sm text-gray-600">Uses your saved Assessment and latest confirmed grades. Earlier results are kept in <a href="{{ route('student.history') }}" class="font-medium text-brand-900 underline">History</a>.</p>
                        <livewire:student.request-prediction />
                    </div>
                </div>
            </section>
        @endif

        <div class="space-y-1">
            <p class="text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
            <x-model-disclosure :version="$latest?->model_version" />
        </div>
    </div>

    @if ($charts)
        <script type="application/json" id="dashboard-chart-data">@json(['employability' => $charts['employability'], 'dropout' => $charts['dropout']])</script>
        <script>
            (function () {
                const boot = function () {
                    const node = document.getElementById('dashboard-chart-data');
                    if (!window.Chart || !node || node.dataset.ready === '1') {
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
                            data: { labels: payload.labels, datasets: [{ data: payload.magnitudes, backgroundColor: payload.colors, borderWidth: 0 }] },
                            options: {
                                maintainAspectRatio: false,
                                indexAxis: 'y',
                                plugins: { legend: { display: false } },
                                scales: { x: { beginAtZero: true, ticks: { color: '#374151' } }, y: { ticks: { color: '#1B5E20' } } },
                            },
                        });
                    };
                    bars('dashboard-employability-factors', data.employability);
                    bars('dashboard-dropout-factors', data.dropout);
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            })();
        </script>
    @endif
</x-app-layout>
