@php
    $sourceLabels = [
        'pasted' => 'Portal paste',
        'pdf_text' => 'PDF text',
        'grid_ocr' => 'OCR',
        'generic_ocr' => 'OCR',
        'ai_extracted' => 'Text-assist fallback',
        'manual' => 'Manual',
    ];
    $statusBadge = function ($report): array {
        if ($report->isDraft()) {
            return ['Draft', 'border-gray-200 bg-gray-50 text-gray-700', 'ri-draft-line'];
        }
        if ($report->isSuperseded()) {
            return ['Replaced', 'border-gray-200 bg-white text-gray-600', 'ri-history-line'];
        }

        return ['Current', 'border-brand-200 bg-brand-50 text-brand-900', 'ri-checkbox-circle-line'];
    };
@endphp

<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-sm">
            <label for="table-search-{{ $this->getId() }}" class="sr-only">Search</label>
            <input id="table-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search grade reports…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
        </div>
        <div class="flex items-center gap-2">
            <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
            <select id="per-page-{{ $this->getId() }}" wire:model.live="perPage" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="relative overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm">
        <div wire:loading.flex wire:target="search,perPage,sortBy,gotoPage,nextPage,previousPage" class="absolute inset-x-0 top-0 z-10 h-0.5 bg-brand-400" aria-hidden="true"></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        @foreach ($columns as $column)
                            <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">
                                <button type="button" wire:click="sortBy('{{ $column['key'] }}')">{{ $column['label'] }}</button>
                            </th>
                        @endforeach
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        @php
                            [$statusLabel, $statusClass, $statusIcon] = $statusBadge($row);
                        @endphp
                        <tr wire:key="grade-report-{{ $row->id }}" class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->school_year }}</td>
                            <td class="px-4 py-3">{{ $row->semester }}</td>
                            <td class="px-4 py-3">v{{ $row->version }}</td>
                            <td class="px-4 py-3">{{ $sourceLabels[$row->source] ?? str_replace('_', ' ', $row->source) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium {{ $statusClass }}"><i class="{{ $statusIcon }}" aria-hidden="true"></i>{{ $statusLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <button type="button" wire:click="openView({{ $row->id }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-brand-900 hover:bg-brand-50" aria-label="View {{ $row->school_year }} {{ $row->semester }} version {{ $row->version }}"><i class="ri-eye-line" aria-hidden="true"></i>View</button>
                                @if ($row->isDraft())
                                    <button type="button" wire:click="continueDraft({{ $row->id }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-brand-900 hover:bg-brand-50"><i class="ri-edit-line" aria-hidden="true"></i>Continue</button>
                                    <button type="button" wire:click="deleteReport({{ $row->id }})" data-confirm="The draft is removed. Confirmed grades are not affected." data-confirm-title="Delete this draft?" data-confirm-button="Delete" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm text-red-800 hover:bg-red-50"><i class="ri-delete-bin-line" aria-hidden="true"></i>Delete</button>
                                @elseif ($row->isCurrent())
                                    <button type="button" wire:click="replaceReport({{ $row->id }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-brand-900 hover:bg-brand-50"><i class="ri-refresh-line" aria-hidden="true"></i>Update term</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 px-4 py-3">{{ $rows->links() }}</div>
    </div>

    @if ($viewing)
        <x-dialog :title="'AY '.$viewing->school_year.' · '.$viewing->semester" close="closeView" max-width="4xl"
            :description="'Version '.$viewing->version.' · '.($sourceLabels[$viewing->source] ?? $viewing->source).($viewing->confirmed_at ? ' · confirmed '.$viewing->confirmed_at->timezone(config('app.timezone'))->format('M j, Y') : ' · draft').' · read-only'">
            <div class="space-y-4 px-5 py-4 sm:px-6">
                @if ($viewing->isSuperseded())
                    <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"><i class="ri-history-line mr-1" aria-hidden="true"></i>Replaced on {{ $viewing->superseded_at->timezone(config('app.timezone'))->format('M j, Y') }}@if ($viewing->supersededBy) by version {{ $viewing->supersededBy->version }}@endif. It no longer counts toward your GWA, but predictions made while it was current still show it.</p>
                @elseif ($viewing->isDraft())
                    <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">This draft does not count until you confirm it.</p>
                @endif

                <div class="overflow-x-auto rounded-xl border border-brand-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-brand-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr>
                                <th scope="col" class="px-3 py-2">Code</th>
                                <th scope="col" class="px-3 py-2">Subject</th>
                                <th scope="col" class="px-3 py-2">Units</th>
                                <th scope="col" class="px-3 py-2">Final grade</th>
                                <th scope="col" class="px-3 py-2">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-200/60">
                            @forelse ($viewing->subjectGrades as $grade)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700">{{ $grade->subject_code }}</td>
                                    <td class="px-3 py-2 text-gray-900">{{ $subjectName($grade->subject_name) }}</td>
                                    <td class="px-3 py-2">{{ rtrim(rtrim(number_format($grade->units, 2), '0'), '.') }}</td>
                                    <td class="px-3 py-2 font-mono font-semibold {{ $grade->is_incomplete ? 'text-amber-700' : ($grade->is_failed ? 'text-red-700' : 'text-brand-900') }}">{{ $grade->final_grade !== '' ? $grade->final_grade : '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $grade->remarks !== '' ? ucfirst(strtolower($grade->remarks)) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No subjects recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="text-sm text-gray-700">
                    Term GWA: <span class="font-semibold text-brand-900">{{ $viewingGwa?->roundedGpa !== null ? number_format($viewingGwa->roundedGpa, 2) : '—' }}</span>
                    @if ($viewingGwa?->isProvisional())
                        <span class="ml-2 text-xs text-amber-900">Provisional: {{ $viewingGwa->incompleteCount }} incomplete {{ \Illuminate\Support\Str::plural('subject', $viewingGwa->incompleteCount) }} left out.</span>
                    @endif
                </p>
            </div>
            <div class="flex justify-end border-t border-brand-200 px-5 py-3 sm:px-6">
                <button type="button" wire:click="closeView" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Close</button>
            </div>
        </x-dialog>
    @endif
</div>
