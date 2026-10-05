<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">University of Caloocan City · EduPredict</p>
            <h2 class="text-lg font-semibold text-brand-900">Hi, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}. Here's your latest snapshot.</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                @if ($student?->program)
                    {{ $student->program->name }} · Year {{ $student->year_level }}
                @endif
                @if ($latest)
                    · Last updated {{ $latest->created_at?->timezone(config('app.timezone'))->format('M j, Y') }}
                @endif
            </p>
            <a href="{{ route('student.results') }}" class="text-sm font-medium text-brand-900 hover:underline">View full results</a>
        </div>

        @if ($completeness)
            <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="dashboard-completeness">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p id="dashboard-completeness" class="text-sm text-gray-700">Profile completeness — more complete data improves prediction accuracy</p>
                    <div class="flex items-center gap-3">
                        <p class="text-sm font-semibold text-brand-900">{{ $completeness['percent'] }}%</p>
                        <a href="{{ route('student.profile') }}" class="text-sm font-medium text-brand-900 hover:underline">Complete profile</a>
                    </div>
                </div>
                <div
                    class="mt-3 h-2 overflow-hidden rounded-full bg-brand-50"
                    role="progressbar"
                    aria-valuenow="{{ $completeness['percent'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-labelledby="dashboard-completeness"
                >
                    <div class="h-full rounded-full bg-brand-400" style="width: {{ $completeness['percent'] }}%"></div>
                </div>
            </section>
        @endif

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
                <x-employability-gauge :score="$latest?->employability_score" />
            </section>
            <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm lg:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic wellness</p>
                @if ($latest && $student)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-risk-badge :level="$latest->dropout_risk" />
                        <x-program-shift :prediction="$latest" compact />
                        @if ($latest->confidence === 'low')
                            <x-confidence-tag />
                        @endif
                    </div>
                    <x-program-shift :prediction="$latest" />
                    <p class="mt-4 text-sm leading-6 text-gray-700">{{ $summary['dropout'] }}</p>
                    @if (in_array($latest->dropout_risk, ['moderate', 'high'], true))
                        <p class="mt-3 text-sm leading-6 text-gray-700">{{ \App\Services\Interventions\RecommendedActionBuilder::STUDENT_MESSAGE }}</p>
                    @endif
                    <div class="mt-4">
                        <livewire:student.request-prediction />
                    </div>
                @elseif ($student)
                    <p class="mt-3 text-sm text-gray-700">Request an estimate when your academic record, socioeconomic profile, skills, and questionnaire are saved.</p>
                    <div class="mt-4">
                        <livewire:student.request-prediction />
                    </div>
                @else
                    <p class="mt-3 text-sm text-gray-700">Your student record is not available yet.</p>
                @endif
                @if ($academic)
                    <p class="mt-4 text-sm text-gray-800">
                        GWA: <span class="font-medium">{{ $academic['rounded_gwa'] !== null ? number_format($academic['rounded_gwa'], 2) : '—' }}</span>
                        · Failed subjects: {{ $academic['failed_subjects'] }}
                        · Semesters completed: {{ $academic['semesters_completed'] }}
                    </p>
                @endif
            </section>
        </div>

        @if (! empty($summary['top']))
            <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="dashboard-factors">
                <h2 id="dashboard-factors" class="text-sm font-semibold text-brand-900">Why these scores? Top contributing factors</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ($summary['top'] as $factor)
                        <li class="rounded-xl border border-brand-200 px-4 py-3">
                            <p class="text-sm font-medium text-brand-900">{{ $factor['label'] }}</p>
                            <p class="mt-1 text-xs text-gray-600">{{ ($factor['direction'] ?? '') === '+' ? 'Supports this estimate' : 'An area where support could help' }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Profile snapshot">
            <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-brand-900">{{ $academic && $academic['rounded_gwa'] !== null ? number_format($academic['rounded_gwa'], 2) : '—' }}</p>
                <p class="text-sm text-gray-600">GWA</p>
            </div>
            <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-brand-900">{{ $skillsLogged }}</p>
                <p class="text-sm text-gray-600">Skills logged</p>
            </div>
            <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-brand-900">{{ ($completeness['sections']['questionnaire'] ?? false) ? 'Done' : 'Not yet' }}</p>
                <p class="text-sm text-gray-600">Questionnaire</p>
            </div>
            <div class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-brand-900">{{ $latest?->created_at?->timezone(config('app.timezone'))->format('M j') ?? '—' }}</p>
                <p class="text-sm text-gray-600">Last assessment</p>
            </div>
        </section>

        <p class="text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.
            @if ($latest)
                Model: {{ $latest->model_version }}
            @endif
        </p>
    </div>
</x-app-layout>
