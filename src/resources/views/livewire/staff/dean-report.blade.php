<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div class="flex flex-wrap items-end gap-3">
            @if ($departmentOptions->count() > 1)
                <div>
                    <label for="dean-report-department-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                    <select id="dean-report-department-{{ $this->getId() }}" wire:model.live="departmentId" class="ui-field w-auto">
                        <option value="">All departments</option>
                        @foreach ($departmentOptions as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($programs->count() > 1)
                <div>
                    <label for="dean-report-program-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Program</label>
                    <select id="dean-report-program-{{ $this->getId() }}" wire:model.live="programId" class="ui-field w-auto">
                        <option value="">All programs</option>
                        @foreach ($programs as $program)
                            @continue($departmentId !== '' && (string) $program->department_id !== $departmentId)
                            <option value="{{ $program->id }}">{{ $program->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="dean-report-year-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Year</label>
                <select id="dean-report-year-{{ $this->getId() }}" wire:model.live="yearLevel" class="ui-field w-auto">
                    <option value="">All years</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div>
                <label for="dean-report-period-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Period</label>
                <select id="dean-report-period-{{ $this->getId() }}" wire:model.live="period" class="ui-field w-auto">
                    <option value="">All periods</option>
                    <option value="this_year">This year</option>
                    <option value="last_12_months">Last 12 months</option>
                </select>
            </div>
            <div>
                <label for="dean-report-from-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">From</label>
                <input id="dean-report-from-{{ $this->getId() }}" type="date" wire:model.live="dateFrom" class="ui-field w-auto">
            </div>
            <div>
                <label for="dean-report-to-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">To</label>
                <input id="dean-report-to-{{ $this->getId() }}" type="date" wire:model.live="dateTo" class="ui-field w-auto">
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="ui-btn ui-btn-secondary">
                <x-spinner wire:loading wire:target="export" />
                Export CSV
            </button>
            <button type="button" class="ui-btn ui-btn-primary" onclick="window.print()">
                <i class="ri-printer-line" aria-hidden="true"></i>
                Print
            </button>
        </div>
    </div>

    <p class="text-sm text-gray-600">College totals only. Individual students are not listed here.</p>
    @include('livewire.staff.partials.report-disclaimer')

    @php
        $averageLabel = $stats['average_employability'] === null ? '—' : number_format((float) $stats['average_employability'], 1);
    @endphp

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="$stats['period_applied'] ? 'Students in period' : 'Total students'" :value="$stats['students']" icon="ri-team-line" tone="sand" />
        <x-kpi label="Average employability" :value="$averageLabel" icon="ri-line-chart-line" tone="mint" />
        <x-kpi label="High-risk students" :value="$stats['high_risk']" icon="ri-alert-line" tone="rose" value-class="text-red-800" />
        <x-kpi label="Assessments this year" :value="$stats['assessments_this_year']" icon="ri-survey-line" tone="indigo" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            'program_fit' => ['Program-fit concern', 'sand'],
            'disengagement' => ['Broader disengagement', 'mint'],
            'mixed' => ['Mixed signals', 'lilac'],
            'none' => ['No shift pattern', 'indigo'],
        ] as $flag => [$label, $tone])
            <x-kpi :label="$label" :value="$stats['program_shift'][$flag]" :tone="$tone" />
        @endforeach
    </div>
    <p class="text-xs text-gray-500">Program-shift figures are counts of students. The indicator itself is qualitative and has no percentage.</p>

    <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
        <h2 class="text-base font-semibold text-gray-900">Year-level breakdown</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="ui-table min-w-full text-sm">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Students</th>
                        <th>Low</th>
                        <th>Moderate</th>
                        <th>High</th>
                        <th>Average</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats['year_levels'] as $level)
                        <tr>
                            <td>Year {{ $level['year'] }}</td>
                            <td>{{ $level['students'] }}</td>
                            <td>{{ $level['low'] }}</td>
                            <td>{{ $level['moderate'] }}</td>
                            <td>{{ $level['high'] }}</td>
                            <td>{{ $level['average_employability'] === null ? '—' : number_format((float) $level['average_employability'], 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if (count($stats['departments']) > 1)
        <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
            <h2 class="text-base font-semibold text-gray-900">Department comparison</h2>
            <p class="mt-1 text-xs text-gray-500">Counts only. This table has no student names or records.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="ui-table min-w-full text-sm">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Students</th>
                            <th>Average</th>
                            <th>Low</th>
                            <th>Moderate</th>
                            <th>High</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['departments'] as $department)
                            <tr>
                                <td class="font-medium text-gray-900">{{ $department['name'] }}</td>
                                <td>{{ $department['students'] }}</td>
                                <td>{{ $department['average_employability'] === null ? '—' : number_format((float) $department['average_employability'], 1) }}</td>
                                <td>{{ $department['low'] }}</td>
                                <td>{{ $department['moderate'] }}</td>
                                <td>{{ $department['high'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
        <h2 class="text-base font-semibold text-gray-900">Program comparison</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="ui-table min-w-full text-sm">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Students</th>
                        <th>Average</th>
                        <th>Low</th>
                        <th>Moderate</th>
                        <th>High</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stats['programs'] as $program)
                        <tr>
                            <td class="font-medium text-gray-900">{{ $program['code'] }}</td>
                            <td>{{ $program['students'] }}</td>
                            <td>{{ $program['average_employability'] === null ? '—' : number_format((float) $program['average_employability'], 1) }}</td>
                            <td>{{ $program['low'] }}</td>
                            <td>{{ $program['moderate'] }}</td>
                            <td>{{ $program['high'] }}</td>
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
