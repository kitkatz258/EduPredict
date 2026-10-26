<div>
    @if ($statusModal && is_array($summary))
        @if ($summary['high_risk'] > 0)
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4" role="status">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-red-800">
                        <i class="ri-alarm-warning-line mr-1" aria-hidden="true"></i>
                        {{ $summary['high_risk'] === 1 ? '1 student is' : $summary['high_risk'].' students are' }} in the higher dropout-risk range.
                        @if ($summary['unreviewed_high'] > 0)
                            {{ $summary['unreviewed_high'] === 1 ? '1 still needs a review.' : $summary['unreviewed_high'].' still need a review.' }}
                        @endif
                    </p>
                    <button
                        type="button"
                        wire:click="showHighRisk"
                        wire:loading.attr="disabled"
                        wire:target="showHighRisk"
                        class="ui-btn ui-btn-secondary border-red-700 text-red-800 hover:bg-red-50"
                    >
                        <x-spinner wire:loading wire:target="showHighRisk" />
                        Review high-risk students
                    </button>
                </div>
            </div>
        @endif

        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'high' => 'High risk',
                'moderate' => 'Moderate risk',
                'low' => 'Low risk',
            ] as $level => $label)
                <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-brand-900">{{ $summary['risk'][$level] }}</p>
                    <p class="sr-only">{{ $label }}: {{ $summary['risk'][$level] }}</p>
                </article>
            @endforeach
            <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Program concern</p>
                <p class="mt-2 text-2xl font-semibold text-brand-900">{{ $summary['program_concern'] }}</p>
                <p class="sr-only">Program concern: {{ $summary['program_concern'] }}</p>
                <p class="mt-1 text-xs text-gray-500">Program-fit and mixed signals. This is a count, not a percentage.</p>
            </article>
        </div>
    @endif

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between print:hidden">
        <div class="w-full lg:max-w-sm">
            <label for="students-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Search</label>
            <input id="students-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search name, number, or program…" class="ui-field">
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="students-year-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Year</label>
                <select id="students-year-{{ $this->getId() }}" wire:model.live="filters.year_level" class="ui-field w-auto">
                    <option value="">All years</option>
                    @foreach ($yearOptions as $year => $label)
                        <option value="{{ $year }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if ($programs->isNotEmpty())
                <div>
                    <label for="students-program-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Program</label>
                    <select id="students-program-{{ $this->getId() }}" wire:model.live="filters.program_id" class="ui-field w-auto">
                        <option value="">All programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="students-risk-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Risk</label>
                <select id="students-risk-{{ $this->getId() }}" wire:model.live="filters.dropout_risk" class="ui-field w-auto">
                    <option value="">All risks</option>
                    <option value="low">Low</option>
                    <option value="moderate">Moderate</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div>
                <label for="students-per-page-{{ $this->getId() }}" class="mb-1 block text-sm font-medium text-gray-700">Per page</label>
                <select id="students-per-page-{{ $this->getId() }}" wire:model.live="perPage" class="ui-field w-auto">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="ui-btn ui-btn-secondary">
                <x-spinner wire:loading wire:target="export" />
                Export CSV
            </button>
            <p wire:loading wire:target="search,filters,sortBy,showHighRisk" class="pb-2 text-sm text-gray-500" role="status">Updating list…</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60" wire:target="search,filters,perPage,sortBy,gotoPage,nextPage,previousPage,showHighRisk">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">
                            <button type="button" wire:click="sortBy('student_number')">Student number @if ($sortField === 'student_number')<span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Student</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Program</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">
                            <button type="button" wire:click="sortBy('year_level')">Year @if ($sortField === 'year_level')<span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                        </th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Risk</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Employability</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Shift / engagement</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        @php
                            $shift = $row->latestPrediction ? $shiftLabels->present($row->latestPrediction) : null;
                        @endphp
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $row->student_number }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ $row->user?->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->program?->code }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $yearOptions[$row->year_level] ?? $row->year_level }}</td>
                            <td class="px-4 py-3">
                                @if ($row->latestPrediction)
                                    <x-risk-badge :level="$row->latestPrediction->dropout_risk" />
                                @else
                                    <span class="text-gray-500">No prediction yet</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $row->latestPrediction ? number_format((float) $row->latestPrediction->employability_score, 0) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                @if ($shift)
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-xs font-medium text-brand-900">{{ $shift['label'] }}</span>
                                        @if ($shift['engagement'])
                                            <span class="inline-flex items-center rounded-full border border-brand-200 bg-white px-2 py-0.5 text-xs text-brand-900">Engagement: {{ $shift['engagement'] }}</span>
                                        @endif
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 print:hidden">
                                @if ($statusModal)
                                    <x-table-action
                                        icon="ri-eye-line"
                                        wire:click="openView({{ $row->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="openView({{ $row->id }})"
                                    >
                                        View status for {{ $row->user?->name ?? $row->student_number }}
                                    </x-table-action>
                                @else
                                    <a href="{{ route('students.show', $row) }}" class="ui-icon-btn" aria-label="View {{ $row->user?->name ?? $row->student_number }}" title="View {{ $row->user?->name ?? $row->student_number }}">
                                        <i class="ri-eye-line text-base leading-none" aria-hidden="true"></i>
                                        <span class="sr-only">View</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 bg-white px-4 py-3">{{ $rows->links() }}</div>
    </div>

    @if ($statusModal && $viewing)
        @include('livewire.tables.partials.student-status')
    @endif
</div>
