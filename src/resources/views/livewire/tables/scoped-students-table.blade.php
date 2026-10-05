<div>
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="w-full lg:max-w-sm">
            <label for="students-search-{{ $this->getId() }}" class="sr-only">Search students</label>
            <input id="students-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search name, number, or program…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="students-year-{{ $this->getId() }}" class="mr-2 text-sm text-gray-600">Year</label>
                <select id="students-year-{{ $this->getId() }}" wire:model.live="filters.year_level" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                    <option value="">All</option>
                    @foreach ([1, 2, 3, 4] as $year)
                        <option value="{{ $year }}">Year {{ $year }}</option>
                    @endforeach
                </select>
            </div>
            @if ($programs->isNotEmpty())
                <div>
                    <label for="students-program-{{ $this->getId() }}" class="mr-2 text-sm text-gray-600">Program</label>
                    <select id="students-program-{{ $this->getId() }}" wire:model.live="filters.program_id" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                        <option value="">All</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="students-risk-{{ $this->getId() }}" class="mr-2 text-sm text-gray-600">Risk</label>
                <select id="students-risk-{{ $this->getId() }}" wire:model.live="filters.dropout_risk" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                    <option value="">All</option>
                    <option value="low">Low</option>
                    <option value="moderate">Moderate</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div>
                <label for="students-per-page-{{ $this->getId() }}" class="mr-2 text-sm text-gray-600">Per page</label>
                <select id="students-per-page-{{ $this->getId() }}" wire:model.live="perPage" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="export" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">
                Export CSV
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
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
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Latest risk</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Employability</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Program shift</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">History</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $row->student_number }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ $row->user?->name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->program?->code }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->year_level }}</td>
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
                                {{ $row->latestPrediction ? $shiftLabels->label((string) $row->latestPrediction->program_shift_flag) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('students.show', $row) }}" class="font-medium text-brand-900 underline">View</a>
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
</div>
