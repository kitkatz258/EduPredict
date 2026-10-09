<div class="space-y-6">
    <div class="flex flex-col gap-3 rounded-2xl border border-brand-200 bg-white p-4 shadow-sm lg:flex-row lg:items-end lg:justify-between">
        <p class="text-sm text-gray-600">Totals use each student's latest estimate. Earlier requests stay in history and are not averaged in.@if ($stats['period_applied']) This period includes students whose latest estimate falls in the selected window.@endif</p>
        <div class="flex flex-wrap items-end gap-3">
            @if ($departmentOptions->count() > 1)
                <div>
                    <label for="analytics-department-{{ $this->getId() }}" class="mb-1 block text-sm text-gray-600">Department</label>
                    <select id="analytics-department-{{ $this->getId() }}" wire:model.live="departmentId" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                        <option value="">All departments</option>
                        @foreach ($departmentOptions as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($programs->count() > 1)
                <div>
                    <label for="analytics-program-{{ $this->getId() }}" class="mb-1 block text-sm text-gray-600">Program</label>
                    <select id="analytics-program-{{ $this->getId() }}" wire:model.live="programId" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                        <option value="">All programs</option>
                        @foreach ($programs as $program)
                            @continue($departmentId !== '' && (string) $program->department_id !== $departmentId)
                            <option value="{{ $program->id }}">{{ $program->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="analytics-year-{{ $this->getId() }}" class="mb-1 block text-sm text-gray-600">Year</label>
                <select id="analytics-year-{{ $this->getId() }}" wire:model.live="yearLevel" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                    <option value="">All years</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div>
                <label for="analytics-period-{{ $this->getId() }}" class="mb-1 block text-sm text-gray-600">Period</label>
                <select id="analytics-period-{{ $this->getId() }}" wire:model.live="period" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                    <option value="">All periods</option>
                    <option value="this_year">This year</option>
                    <option value="last_12_months">Last 12 months</option>
                </select>
            </div>
            <p wire:loading class="pb-2 text-sm text-gray-500">Updating totals…</p>
        </div>
    </div>

    @php
        $averageLabel = $stats['average_employability'] === null ? '—' : number_format((float) $stats['average_employability'], 1);
    @endphp

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $stats['period_applied'] ? 'Students in period' : 'Total students' }}</p>
            <p class="mt-2 text-3xl font-semibold text-brand-900">{{ $stats['students'] }}</p>
            <p class="sr-only">Total students: {{ $stats['students'] }}</p>
        </article>
        <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Average employability</p>
            <p class="mt-2 text-3xl font-semibold text-brand-900">{{ $averageLabel }}</p>
            <p class="sr-only">Average employability is {{ $averageLabel }}</p>
        </article>
        <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">High-risk students</p>
            <p class="mt-2 text-3xl font-semibold text-red-800">{{ $stats['high_risk'] }}</p>
            <p class="sr-only">High-risk students: {{ $stats['high_risk'] }}</p>
        </article>
        <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assessments this year</p>
            <p class="mt-2 text-3xl font-semibold text-brand-900">{{ $stats['assessments_this_year'] }}</p>
            <p class="sr-only">Assessments this year: {{ $stats['assessments_this_year'] }}</p>
        </article>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            'program_fit' => 'Program-fit concern',
            'disengagement' => 'Broader disengagement',
            'mixed' => 'Mixed signals',
            'none' => 'No shift pattern',
        ] as $flag => $label)
            <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-brand-900">{{ $stats['program_shift'][$flag] }}</p>
                <p class="sr-only">{{ $label }}: {{ $stats['program_shift'][$flag] }}</p>
            </article>
        @endforeach
    </div>
    <p class="text-xs text-gray-500">Program-shift figures are counts of students. The indicator itself is qualitative and has no percentage.</p>

    <div
        wire:ignore
        class="grid gap-4 lg:grid-cols-2"
        x-data="{
            charts: {},
            draw(payload) {
                if (!payload || !window.Chart) {
                    return;
                }
                const legend = { labels: { color: '#1B5E20' } };
                this.upsert('cohort-risk-{{ $this->getId() }}', {
                    type: 'doughnut',
                    data: {
                        labels: payload.risk.labels,
                        datasets: [{ data: payload.risk.data, backgroundColor: payload.risk.colors, borderWidth: 0 }],
                    },
                    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', ...legend } } },
                });
                this.upsert('cohort-bands-{{ $this->getId() }}', {
                    type: 'bar',
                    data: {
                        labels: payload.bands.labels,
                        datasets: [{ label: 'Students', data: payload.bands.data, backgroundColor: '#1B5E20', borderWidth: 0 }],
                    },
                    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });
                this.upsert('cohort-trend-{{ $this->getId() }}', {
                    type: 'line',
                    data: {
                        labels: payload.trend.labels,
                        datasets: [
                            { label: 'Average employability', data: payload.trend.employability, borderColor: '#1B5E20', backgroundColor: '#1B5E20', tension: 0.2 },
                            { label: 'High risk', data: payload.trend.high, borderColor: '#B91C1C', backgroundColor: '#B91C1C', tension: 0.2 },
                        ],
                    },
                    options: { maintainAspectRatio: false, plugins: { legend }, scales: { y: { beginAtZero: true } } },
                });
                this.upsert('cohort-years-{{ $this->getId() }}', {
                    type: 'bar',
                    data: {
                        labels: payload.years.labels,
                        datasets: [
                            { label: 'Low', data: payload.years.low, backgroundColor: '#15803D' },
                            { label: 'Moderate', data: payload.years.moderate, backgroundColor: '#D97706' },
                            { label: 'High', data: payload.years.high, backgroundColor: '#B91C1C' },
                        ],
                    },
                    options: { maintainAspectRatio: false, plugins: { legend }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
                });
                if (payload.programs.labels.length > 1) {
                    this.upsert('cohort-programs-{{ $this->getId() }}', {
                        type: 'bar',
                        data: {
                            labels: payload.programs.labels,
                            datasets: [{ label: 'Average employability', data: payload.programs.averages, backgroundColor: '#1B5E20', borderWidth: 0 }],
                        },
                        options: { maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100 } } },
                    });
                }
            },
            upsert(id, config) {
                const canvas = document.getElementById(id);
                if (!canvas) {
                    return;
                }
                if (this.charts[id]) {
                    this.charts[id].data = config.data;
                    this.charts[id].update();
                    return;
                }
                this.charts[id] = new window.Chart(canvas, config);
            },
        }"
        x-init="draw(@js($charts))"
        x-on:analytics-updated.window="draw($event.detail.charts ?? $event.detail)"
    >
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-brand-900">Dropout risk distribution</h3>
            <div class="relative mt-4 h-64">
                <canvas id="cohort-risk-{{ $this->getId() }}" aria-label="Dropout risk distribution" role="img"></canvas>
            </div>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-brand-900">Employability score distribution</h3>
            <div class="relative mt-4 h-64">
                <canvas id="cohort-bands-{{ $this->getId() }}" aria-label="Employability score distribution" role="img"></canvas>
            </div>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-brand-900">Trend by month</h3>
            <p class="mt-1 text-xs text-gray-500">Each point is the latest estimate for students whose latest request falls in that month.</p>
            <div class="relative mt-4 h-64">
                <canvas id="cohort-trend-{{ $this->getId() }}" aria-label="Employability and high-risk trend" role="img"></canvas>
            </div>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-brand-900">Risk by year level</h3>
            <div class="relative mt-4 h-64">
                <canvas id="cohort-years-{{ $this->getId() }}" aria-label="Risk counts by year level" role="img"></canvas>
            </div>
        </section>
        @if (count($charts['programs']['labels']) > 1)
            <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h3 class="text-sm font-semibold text-brand-900">Employability by program</h3>
                <div class="relative mt-4 h-64">
                    <canvas id="cohort-programs-{{ $this->getId() }}" aria-label="Average employability by program" role="img"></canvas>
                </div>
            </section>
        @endif
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="year-breakdown-heading">
            <h3 id="year-breakdown-heading" class="text-sm font-semibold text-brand-900">Year-level breakdown</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-3 font-medium">Year</th>
                            <th class="py-2 pr-3 font-medium">Students</th>
                            <th class="py-2 pr-3 font-medium">Low</th>
                            <th class="py-2 pr-3 font-medium">Moderate</th>
                            <th class="py-2 pr-3 font-medium">High</th>
                            <th class="py-2 font-medium">Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['year_levels'] as $level)
                            <tr class="border-t border-brand-200">
                                <td class="py-2 pr-3">Year {{ $level['year'] }}</td>
                                <td class="py-2 pr-3">{{ $level['students'] }}</td>
                                <td class="py-2 pr-3">{{ $level['low'] }}</td>
                                <td class="py-2 pr-3">{{ $level['moderate'] }}</td>
                                <td class="py-2 pr-3">{{ $level['high'] }}</td>
                                <td class="py-2">{{ $level['average_employability'] === null ? '—' : number_format((float) $level['average_employability'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="trend-table-heading">
            <h3 id="trend-table-heading" class="text-sm font-semibold text-brand-900">Trend by month</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-3 font-medium">Month</th>
                            <th class="py-2 pr-3 font-medium">Average</th>
                            <th class="py-2 pr-3 font-medium">Low</th>
                            <th class="py-2 pr-3 font-medium">Moderate</th>
                            <th class="py-2 font-medium">High</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stats['trend'] as $point)
                            <tr class="border-t border-brand-200">
                                <td class="py-2 pr-3">{{ $point['label'] }}</td>
                                <td class="py-2 pr-3">{{ number_format((float) $point['average_employability'], 1) }}</td>
                                <td class="py-2 pr-3">{{ $point['low'] }}</td>
                                <td class="py-2 pr-3">{{ $point['moderate'] }}</td>
                                <td class="py-2">{{ $point['high'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-gray-500">No estimates in this scope yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if (count($stats['departments']) > 1)
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="department-comparison-heading">
            <h3 id="department-comparison-heading" class="text-sm font-semibold text-brand-900">Department comparison</h3>
            <p class="mt-1 text-xs text-gray-500">Counts only. This table has no student names or records.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2 pr-3 font-medium">Department</th>
                            <th class="py-2 pr-3 font-medium">Students</th>
                            <th class="py-2 pr-3 font-medium">Average</th>
                            <th class="py-2 pr-3 font-medium">Low</th>
                            <th class="py-2 pr-3 font-medium">Moderate</th>
                            <th class="py-2 font-medium">High</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['departments'] as $department)
                            <tr class="border-t border-brand-200">
                                <td class="py-2 pr-3 font-medium text-brand-900">{{ $department['name'] }}</td>
                                <td class="py-2 pr-3">{{ $department['students'] }}</td>
                                <td class="py-2 pr-3">{{ $department['average_employability'] === null ? '—' : number_format((float) $department['average_employability'], 1) }}</td>
                                <td class="py-2 pr-3">{{ $department['low'] }}</td>
                                <td class="py-2 pr-3">{{ $department['moderate'] }}</td>
                                <td class="py-2">{{ $department['high'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="program-comparison-heading">
        <h3 id="program-comparison-heading" class="text-sm font-semibold text-brand-900">Program comparison</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2 pr-3 font-medium">Program</th>
                        <th class="py-2 pr-3 font-medium">Students</th>
                        <th class="py-2 pr-3 font-medium">Average</th>
                        <th class="py-2 pr-3 font-medium">Low</th>
                        <th class="py-2 pr-3 font-medium">Moderate</th>
                        <th class="py-2 font-medium">High</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stats['programs'] as $program)
                        <tr class="border-t border-brand-200">
                            <td class="py-2 pr-3 font-medium text-brand-900">{{ $program['code'] }}</td>
                            <td class="py-2 pr-3">{{ $program['students'] }}</td>
                            <td class="py-2 pr-3">{{ $program['average_employability'] === null ? '—' : number_format((float) $program['average_employability'], 1) }}</td>
                            <td class="py-2 pr-3">{{ $program['low'] }}</td>
                            <td class="py-2 pr-3">{{ $program['moderate'] }}</td>
                            <td class="py-2">{{ $program['high'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-gray-500">No programs are in this scope.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
