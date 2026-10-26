<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div class="flex flex-wrap items-end gap-3">
            @if ($programs->isNotEmpty())
                <div>
                    <label for="report-program-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Program</label>
                    <select id="report-program-{{ $this->getId() }}" wire:model.live="programId" class="ui-field w-auto">
                        <option value="">All programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="report-year-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Year</label>
                <select id="report-year-{{ $this->getId() }}" wire:model.live="yearLevel" class="ui-field w-auto">
                    <option value="">All years</option>
                    @foreach ($yearOptions as $year => $label)
                        <option value="{{ $year }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="report-risk-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Risk</label>
                <select id="report-risk-{{ $this->getId() }}" wire:model.live="dropoutRisk" class="ui-field w-auto">
                    <option value="">All risks</option>
                    <option value="low">Low</option>
                    <option value="moderate">Moderate</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div>
                <label for="report-from-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">From</label>
                <input id="report-from-{{ $this->getId() }}" type="date" wire:model.live="dateFrom" class="ui-field w-auto">
            </div>
            <div>
                <label for="report-to-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">To</label>
                <input id="report-to-{{ $this->getId() }}" type="date" wire:model.live="dateTo" class="ui-field w-auto">
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

    @include('livewire.staff.partials.report-disclaimer')

    <p class="text-sm text-gray-600">{{ $rows->count() }} authorized {{ $rows->count() === 1 ? 'student' : 'students' }} in this view. Questionnaire answers and household details are not included.</p>

    <div class="overflow-hidden rounded-[14px] border border-[#e8eaee] bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">
        <div class="overflow-x-auto">
            <table class="ui-table min-w-full text-sm">
                <thead>
                    <tr>
                        <th>Student number</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Year</th>
                        <th>Risk</th>
                        <th>Employability</th>
                        <th>Program-shift</th>
                        <th>Estimate date</th>
                        <th>Model</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $shift = $row->latestPrediction ? $shiftLabels->present($row->latestPrediction) : null;
                        @endphp
                        <tr>
                            <td class="font-medium text-gray-800">{{ $row->student_number }}</td>
                            <td>{{ $row->user?->name }}</td>
                            <td>{{ $row->program?->code }}</td>
                            <td>{{ $yearOptions[$row->year_level] ?? $row->year_level }}</td>
                            <td>
                                @if ($row->latestPrediction)
                                    <x-risk-badge :level="$row->latestPrediction->dropout_risk" />
                                @else
                                    <span class="text-gray-500">No prediction yet</span>
                                @endif
                            </td>
                            <td>{{ $row->latestPrediction ? number_format((float) $row->latestPrediction->employability_score, 0) : '—' }}</td>
                            <td>{{ $shift['label'] ?? '—' }}</td>
                            <td>{{ $row->latestPrediction?->created_at?->timezone(config('app.timezone'))->toDateString() ?? '—' }}</td>
                            <td class="font-mono text-xs">{{ $row->latestPrediction?->model_version ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-500">No authorized students match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
