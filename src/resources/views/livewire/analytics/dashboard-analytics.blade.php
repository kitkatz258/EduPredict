<div class="space-y-6">
    <div class="space-y-2">
        <div class="flex flex-wrap items-end gap-3 print:hidden">
            @if ($departmentOptions->count() > 1)
                <div>
                    <label for="analytics-department-{{ $this->getId() }}" class="mb-1 block text-sm text-gray-600">Department</label>
                    <select id="analytics-department-{{ $this->getId() }}" wire:model.live="departmentId" class="h-10 rounded-[11px] border border-[#e8eaee] bg-[#f6f7f9] px-3 text-sm text-gray-900 outline-none focus:border-[#4f46e5] focus:bg-white focus:ring-4 focus:ring-indigo-600/15">
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
                    <select id="analytics-program-{{ $this->getId() }}" wire:model.live="programId" class="h-10 rounded-[11px] border border-[#e8eaee] bg-[#f6f7f9] px-3 text-sm text-gray-900 outline-none focus:border-[#4f46e5] focus:bg-white focus:ring-4 focus:ring-indigo-600/15">
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
                <select id="analytics-year-{{ $this->getId() }}" wire:model.live="yearLevel" class="h-10 rounded-[11px] border border-[#e8eaee] bg-[#f6f7f9] px-3 text-sm text-gray-900 outline-none focus:border-[#4f46e5] focus:bg-white focus:ring-4 focus:ring-indigo-600/15">
                    <option value="">All years</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div>
                <label for="analytics-period-{{ $this->getId() }}" class="mb-1 block text-sm text-gray-600">Period</label>
                <select id="analytics-period-{{ $this->getId() }}" wire:model.live="period" class="h-10 rounded-[11px] border border-[#e8eaee] bg-[#f6f7f9] px-3 text-sm text-gray-900 outline-none focus:border-[#4f46e5] focus:bg-white focus:ring-4 focus:ring-indigo-600/15">
                    <option value="">All periods</option>
                    <option value="this_year">This year</option>
                    <option value="last_12_months">Last 12 months</option>
                </select>
            </div>
            <p wire:loading class="pb-2 text-sm text-gray-500">Updating totals…</p>
        </div>
        <p class="text-xs text-gray-500">Totals use each student's latest estimate. Earlier requests stay in history and are not averaged in.@if ($stats['period_applied']) This period includes students whose latest estimate falls in the selected window.@endif</p>
    </div>

    @php
        $averageLabel = $stats['average_employability'] === null ? '—' : number_format((float) $stats['average_employability'], 1);
    @endphp

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="$stats['period_applied'] ? 'Students in period' : 'Total students'" :value="$stats['students']" icon="ri-team-line" tone="sand">
            <p class="sr-only">Total students: {{ $stats['students'] }}</p>
        </x-kpi>
        <x-kpi label="Average employability" :value="$averageLabel" icon="ri-line-chart-line" tone="mint">
            <p class="sr-only">Average employability is {{ $averageLabel }}</p>
        </x-kpi>
        <x-kpi label="High-risk students" :value="$stats['high_risk']" icon="ri-alert-line" tone="rose" value-class="text-red-800">
            <p class="sr-only">High-risk students: {{ $stats['high_risk'] }}</p>
        </x-kpi>
        <x-kpi label="Assessments this year" :value="$stats['assessments_this_year']" icon="ri-survey-line" tone="indigo">
            <p class="sr-only">Assessments this year: {{ $stats['assessments_this_year'] }}</p>
        </x-kpi>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            'program_fit' => ['Program-fit concern', 'sand'],
            'disengagement' => ['Broader disengagement', 'mint'],
            'mixed' => ['Mixed signals', 'lilac'],
            'none' => ['No shift pattern', 'indigo'],
        ] as $flag => [$label, $tone])
            <x-kpi :label="$label" :value="$stats['program_shift'][$flag]" :tone="$tone">
                <p class="sr-only">{{ $label }}: {{ $stats['program_shift'][$flag] }}</p>
            </x-kpi>
        @endforeach
    </div>
    <p class="text-xs text-gray-500">Program-shift figures are counts of students. The indicator itself is qualitative and has no percentage.</p>

    <div
        wire:ignore
        class="grid min-w-0 gap-4 lg:grid-cols-2"
        x-data="{
            draw(payload) {
                if (!payload || !window.Chart) {
                    return;
                }
                const ink = '#6b7280';
                const grid = '#eef0f3';
                const indigo = '#4f46e5';
                const legend = { labels: { color: ink, boxWidth: 8, usePointStyle: true, pointStyle: 'circle' } };
                const valueAxis = { beginAtZero: true, ticks: { color: ink, precision: 0 }, grid: { color: grid } };
                const categoryAxis = { ticks: { color: ink }, grid: { display: false } };
                this.upsert('cohort-risk-{{ $this->getId() }}', {
                    type: 'doughnut',
                    data: {
                        labels: payload.risk.labels,
                        datasets: [{ data: payload.risk.data, backgroundColor: payload.risk.colors, borderWidth: 0 }],
                    },
                    options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', ...legend } } },
                });
                this.upsert('cohort-bands-{{ $this->getId() }}', {
                    type: 'bar',
                    data: {
                        labels: payload.bands.labels,
                        datasets: [{ label: 'Students', data: payload.bands.data, backgroundColor: '#818cf8', borderRadius: 6, borderSkipped: false, borderWidth: 0 }],
                    },
                    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: categoryAxis, y: valueAxis } },
                });
                this.upsert('cohort-trend-{{ $this->getId() }}', {
                    type: 'line',
                    data: {
                        labels: payload.trend.labels,
                        datasets: [
                            { label: 'Average employability', data: payload.trend.employability, borderColor: indigo, backgroundColor: 'rgba(79, 70, 229, 0.14)', tension: 0.35, fill: true, pointRadius: 0, borderWidth: 2 },
                            { label: 'High risk', data: payload.trend.high, borderColor: '#B91C1C', backgroundColor: '#B91C1C', tension: 0.35, pointRadius: 0, borderWidth: 2 },
                        ],
                    },
                    options: { maintainAspectRatio: false, plugins: { legend }, scales: { x: categoryAxis, y: { beginAtZero: true, ticks: { color: ink }, grid: { color: grid } } } },
                });
                this.upsert('cohort-years-{{ $this->getId() }}', {
                    type: 'bar',
                    data: {
                        labels: payload.years.labels,
                        datasets: [
                            { label: 'Low', data: payload.years.low, backgroundColor: '#15803D', borderRadius: 4 },
                            { label: 'Moderate', data: payload.years.moderate, backgroundColor: '#D97706', borderRadius: 4 },
                            { label: 'High', data: payload.years.high, backgroundColor: '#B91C1C', borderRadius: 4 },
                        ],
                    },
                    options: { maintainAspectRatio: false, plugins: { legend }, scales: { x: { stacked: true, ...categoryAxis }, y: { stacked: true, ...valueAxis } } },
                });
                if (payload.programs && payload.programs.labels.length > 0) {
                    this.upsert('cohort-programs-{{ $this->getId() }}', {
                        type: 'bar',
                        data: {
                            labels: payload.programs.labels,
                            datasets: [{ label: 'Average employability', data: payload.programs.averages, backgroundColor: indigo, borderRadius: 6, borderSkipped: false, borderWidth: 0 }],
                        },
                        options: { maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100, ticks: { color: ink }, grid: { color: grid } }, y: categoryAxis } },
                    });
                }
            },
            upsert(id, config) {
                const canvas = document.getElementById(id);
                if (!canvas) {
                    return;
                }
                // Keep Chart instances off Alpine state. A proxied chart throws on the next filter update.
                const registry = window.__edupredictCharts || (window.__edupredictCharts = {});
                if (registry[id] && typeof registry[id].destroy === 'function') {
                    registry[id].destroy();
                }
                registry[id] = new window.Chart(canvas, config);
            },
        }"
        x-init="draw(@js($charts))"
        x-on:analytics-updated.window="draw($event.detail.charts ?? $event.detail)"
    >
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
            <h3 class="text-base font-semibold text-gray-900">Dropout risk distribution</h3>
            <div class="relative mt-4 h-64 min-w-0 overflow-hidden">
                <canvas id="cohort-risk-{{ $this->getId() }}" aria-label="Dropout risk distribution" role="img"></canvas>
            </div>
        </section>
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
            <h3 class="text-base font-semibold text-gray-900">Employability score distribution</h3>
            <div class="relative mt-4 h-64 min-w-0 overflow-hidden">
                <canvas id="cohort-bands-{{ $this->getId() }}" aria-label="Employability score distribution" role="img"></canvas>
            </div>
        </section>
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
            <h3 class="text-base font-semibold text-gray-900">Trend by month</h3>
            <p class="mt-1 text-xs text-gray-500">Each point is the latest estimate for students whose latest request falls in that month.</p>
            <div class="relative mt-4 h-64 min-w-0 overflow-hidden">
                <canvas id="cohort-trend-{{ $this->getId() }}" aria-label="Employability and high-risk trend" role="img"></canvas>
            </div>
        </section>
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
            <h3 class="text-base font-semibold text-gray-900">Risk by year level</h3>
            <div class="relative mt-4 h-64 min-w-0 overflow-hidden">
                <canvas id="cohort-years-{{ $this->getId() }}" aria-label="Risk counts by year level" role="img"></canvas>
            </div>
        </section>
        @if (count($charts['programs']['labels']) > 1)
            <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)] lg:col-span-2">
                <h3 class="text-base font-semibold text-gray-900">Employability by program</h3>
                <div class="relative mt-4 h-64 min-w-0 overflow-hidden">
                    <canvas id="cohort-programs-{{ $this->getId() }}" aria-label="Average employability by program" role="img"></canvas>
                </div>
            </section>
        @endif
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]" aria-labelledby="year-breakdown-heading">
            <h3 id="year-breakdown-heading" class="text-base font-semibold text-gray-900">Year-level breakdown</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="ui-table min-w-full text-sm">
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
                            <tr class="border-t border-[#e8eaee]">
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
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]" aria-labelledby="trend-table-heading">
            <h3 id="trend-table-heading" class="text-base font-semibold text-gray-900">Trend by month</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="ui-table min-w-full text-sm">
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
                            <tr class="border-t border-[#e8eaee]">
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
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]" aria-labelledby="department-comparison-heading">
            <h3 id="department-comparison-heading" class="text-base font-semibold text-gray-900">Department comparison</h3>
            <p class="mt-1 text-xs text-gray-500">Counts only. This table has no student names or records.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="ui-table min-w-full text-sm">
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
                            <tr class="border-t border-[#e8eaee]">
                                <td class="py-2 pr-3 font-medium text-gray-900">{{ $department['name'] }}</td>
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

    <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]" aria-labelledby="program-comparison-heading">
        <h3 id="program-comparison-heading" class="text-base font-semibold text-gray-900">Program comparison</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="ui-table min-w-full text-sm">
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
                        <tr class="border-t border-[#e8eaee]">
                            <td class="py-2 pr-3 font-medium text-gray-900">{{ $program['code'] }}</td>
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
