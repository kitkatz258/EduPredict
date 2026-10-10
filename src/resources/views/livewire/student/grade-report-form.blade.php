@php
    $modes = [
        'upload' => ['ri-upload-2-line', 'Upload Document'],
        'paste' => ['ri-clipboard-line', 'Paste from UCC Portal'],
        'manual' => ['ri-file-edit-line', 'Manual Entry'],
    ];
    $sourceLabels = [
        'pasted' => 'Pasted from the UCC portal',
        'pdf_text' => 'Read from the PDF text layer',
        'grid_ocr' => 'Read with OCR (table grid)',
        'generic_ocr' => 'Read with OCR',
        'ai_extracted' => 'Text-assist fallback',
        'manual' => 'Entered manually',
    ];
    $inputClass = 'w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200';
    $cellInput = 'w-full rounded-md border border-brand-200 bg-white px-2 py-1.5 text-sm focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200';
    $gradeTone = function (array $row): string {
        if (! empty($row['is_incomplete'])) {
            return 'text-amber-700';
        }
        if (! empty($row['is_failed'])) {
            return 'text-red-700';
        }

        return 'text-brand-900';
    };
    $termLabel = trim(($school_year !== '' ? 'AY '.str_replace('-', '–', $school_year) : 'Academic year not set').' · '.($semesters[$semester] ?? 'Semester not set'));
@endphp

<div class="space-y-5">
    @if ($replacesId !== null)
        <div class="flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 sm:flex-row sm:items-center sm:justify-between" role="status">
            <div class="text-sm text-brand-900">
                <p class="font-semibold"><i class="ri-history-line mr-1" aria-hidden="true"></i>Updating {{ $replacingLabel }}</p>
                <p class="mt-1 text-gray-700">Saving creates a new version, for example after an INC is completed. The current version stays in your history, and earlier predictions keep the grades they used.</p>
            </div>
            <button type="button" wire:click="startOver" class="shrink-0 rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Cancel update</button>
        </div>
    @endif

    <div class="relative inline-flex max-w-full rounded-xl bg-brand-50 p-1 ring-1 ring-brand-200" x-data="segmentedTrack()">
        <div x-ref="track" class="relative inline-flex max-w-full flex-wrap gap-1" role="tablist" aria-label="How to add grades">
            <span
                x-ref="indicator"
                aria-hidden="true"
                class="segment-indicator pointer-events-none absolute left-0 top-0 rounded-lg bg-white shadow-sm ring-1 ring-brand-200 motion-reduce:transition-none"
                x-bind:class="ready ? 'opacity-100' : 'opacity-0'"
                x-bind:style="`width: ${width}px; height: ${height}px; transform: translate(${left}px, ${top}px);`"
            ></span>
            @foreach ($modes as $key => [$icon, $label])
                <button
                    type="button"
                    role="tab"
                    id="grade-mode-{{ $key }}"
                    aria-selected="{{ $mode === $key ? 'true' : 'false' }}"
                    aria-controls="grade-mode-panel"
                    wire:click="setMode('{{ $key }}')"
                    wire:loading.attr="disabled"
                    wire:target="setMode"
                    x-on:click="select($el)"
                    class="{{ $mode === $key ? 'bg-white text-brand-900 shadow-sm ring-1 ring-brand-200' : 'text-gray-700 hover:text-brand-900' }} relative z-10 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium"
                    x-bind:class="ready ? 'bg-transparent shadow-none ring-transparent' : ''"
                >
                    <i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div id="grade-mode-panel" role="tabpanel" aria-labelledby="grade-mode-{{ $mode }}" class="space-y-5">
        @if ($mode === 'upload' && $stage === 'input')
            <section class="relative rounded-2xl border-2 border-dashed border-brand-200 bg-white p-6 text-center shadow-sm sm:p-10" x-data="{ dragging: false }" x-bind:class="dragging && 'border-brand-400 bg-brand-50'">
                <x-loading-overlay target="parseUpload" label="Reading your grades…" />
                <label for="grade-upload" class="relative block cursor-pointer rounded-xl focus-within:ring-2 focus-within:ring-brand-200"
                    x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop="dragging = false">
                    <input id="grade-upload" type="file" wire:model="upload" accept=".pdf,.png,.jpg,.jpeg,.webp" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" aria-describedby="grade-upload-help">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-2xl text-brand-900"><i class="ri-image-add-line" aria-hidden="true"></i></span>
                    <span class="mt-4 block text-base font-semibold text-gray-900">Drop your grade report here</span>
                    <span id="grade-upload-help" class="mt-1 block text-sm text-gray-600">or click to browse. PDF, PNG, JPG, or WEBP up to {{ (int) round(config('edupredict.grades.upload_max_kb', 10240) / 1024) }} MB.</span>
                </label>

                <p class="mt-3 text-sm text-brand-900" wire:loading wire:target="upload" role="status"><x-spinner class="mr-1" />Uploading…</p>
                @error('upload') <p class="mt-3 text-sm text-red-700">{{ $message }}</p> @enderror

                @if ($upload && ! $errors->has('upload'))
                    <div class="mx-auto mt-5 flex max-w-md flex-col items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 sm:flex-row sm:justify-between">
                        <p class="truncate text-sm text-brand-900"><i class="ri-file-line mr-1" aria-hidden="true"></i>{{ $upload->getClientOriginalName() }}</p>
                        <button type="button" wire:click="parseUpload" wire:loading.attr="disabled" wire:target="parseUpload,upload" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                            <x-spinner wire:loading wire:target="parseUpload" />Read grades
                        </button>
                    </div>
                @endif

                <p class="mx-auto mt-5 max-w-xl text-xs leading-5 text-gray-500">EduPredict reads the table from the PDF text or with OCR. You review every row before anything is saved. Upload only the part showing your grades; instructor names and sections are not saved.</p>
            </section>
        @elseif ($mode === 'paste' && $stage === 'input')
            <section class="relative rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
                <x-loading-overlay target="parsePaste" label="Reading the pasted table…" />
                <h3 class="text-base font-semibold text-brand-900">Paste from the UCC portal</h3>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-600">
                    <li>Open your grades in the UCC student portal.</li>
                    <li>Select the whole grades table, including the header row and the School Year and Semester line.</li>
                    <li>Copy it (Ctrl+C) and paste it below (Ctrl+V).</li>
                </ol>
                <label for="pastedText" class="sr-only">Pasted grades table</label>
                <textarea id="pastedText" wire:model="pastedText" rows="10" class="{{ $inputClass }} mt-4 font-mono text-xs" placeholder="Subject Code    Description    Units    Midterm    Final    Final Grade    Remarks"></textarea>
                @error('pastedText') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">Works offline. Instructor names and sections are left out of your record.</p>
                    <button type="button" wire:click="parsePaste" wire:loading.attr="disabled" wire:target="parsePaste" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                        <x-spinner wire:loading wire:target="parsePaste" />Read pasted table
                    </button>
                </div>
            </section>
        @elseif ($mode === 'manual')
            <section class="relative overflow-hidden rounded-2xl border border-brand-200 bg-white shadow-sm">
                <x-loading-overlay target="confirm,saveDraft" label="Saving grades…" />
                <div class="flex flex-col gap-4 border-b border-brand-200 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-900">Manual grade entry</h3>
                        <p class="mt-1 text-sm text-gray-600">Enter your grades from your grade sheet or portal. Use INC for an incomplete subject.</p>
                    </div>
                    <div class="flex gap-2">
                        <div>
                            <label for="manual-school-year" class="sr-only">Academic year</label>
                            <select id="manual-school-year" wire:model.live="school_year" class="{{ $inputClass }}">
                                <option value="">Academic year</option>
                                @foreach ($schoolYears as $year)<option value="{{ $year }}">AY {{ $year }}</option>@endforeach
                            </select>
                            @error('school_year') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="manual-semester" class="sr-only">Semester</label>
                            <select id="manual-semester" wire:model.live="semester" class="{{ $inputClass }}">
                                <option value="">Semester</option>
                                @foreach ($semesters as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                            @error('semester') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    @error('rows') <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
                    <div class="hidden grid-cols-[8rem_1fr_5rem_7rem_2rem] gap-3 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 md:grid">
                        <span>Subject code</span><span>Subject name</span><span>Units</span><span>Final grade</span><span class="sr-only">Remove</span>
                    </div>
                    <ul class="space-y-3">
                        @foreach ($rows as $index => $row)
                            <li wire:key="manual-row-{{ $index }}" class="grid grid-cols-2 gap-2 rounded-xl border border-brand-200 p-3 md:grid-cols-[8rem_1fr_5rem_7rem_2rem] md:items-start md:gap-3 md:border-0 md:p-0">
                                <input aria-label="Subject code, row {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.subject_code" placeholder="CCS 106" class="{{ $cellInput }}">
                                <input aria-label="Subject name, row {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.subject_name" placeholder="Subject name" class="{{ $cellInput }} col-span-2 md:col-span-1 order-first md:order-none">
                                <input aria-label="Units, row {{ $index + 1 }}" type="text" inputmode="decimal" wire:model.blur="rows.{{ $index }}.units" placeholder="3" class="{{ $cellInput }}">
                                <input aria-label="Final grade, row {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.final_grade" placeholder="1.00–5.00 or INC" class="{{ $cellInput }} {{ $gradeTone($row) }} font-semibold">
                                <button type="button" wire:click="removeRow({{ $index }})" class="justify-self-end rounded-md p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-800" aria-label="Remove row {{ $index + 1 }}"><i class="ri-close-line" aria-hidden="true"></i></button>
                                @if (! empty($row['is_incomplete']))
                                    <p class="col-span-full text-xs text-amber-800">Incomplete: left out of the GWA and not counted as failed. Update this term when the final grade is out.</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <button type="button" wire:click="addRow" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-900 hover:underline"><i class="ri-add-line" aria-hidden="true"></i>Add subject</button>
                    @include('livewire.student.partials.grade-gwa-summary')
                </div>

                <div class="flex flex-col gap-3 border-t border-brand-200 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs text-gray-600">Saved grades become your academic record and are used in your next prediction.</p>
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,confirm" class="text-sm font-medium text-brand-900 hover:underline disabled:opacity-60">Save draft</button>
                        <button type="button" wire:click="confirm" data-confirm="They will count toward your GWA and your next prediction." data-confirm-title="Save these grades?" data-confirm-button="Save grades" data-confirm-tone="neutral" wire:loading.attr="disabled" wire:target="confirm,saveDraft" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                            <x-spinner wire:loading wire:target="confirm" />Save grades<i class="ri-arrow-right-s-line" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </section>
        @endif

        @if ($mode !== 'manual' && $stage === 'review')
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm" role="status">
                <p class="font-semibold text-brand-900"><i class="ri-file-search-line mr-1" aria-hidden="true"></i>{{ $sourceLabels[$source] ?? str_replace('_', ' ', $source) }}@if ($mode === 'upload' && $upload) · {{ $upload->getClientOriginalName() }}@endif</p>
                <p class="mt-1 text-gray-700">Check each row and use the pencil to fix anything. Nothing is saved until you choose Confirm &amp; Save Grades.</p>
                @if ($usedAiFallback)
                    <p class="mt-1 text-xs text-gray-600">Parsing confidence was low, so cleaned subject-row text (no name, student number, or image) was sent to an optional text-assist service. Review carefully.</p>
                @endif
            </div>

            <section class="relative overflow-hidden rounded-2xl border border-brand-200 bg-white shadow-sm">
                <x-loading-overlay target="confirm,saveDraft" label="Saving grades…" />
                <div class="flex flex-col gap-3 border-b border-brand-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-semibold text-brand-900">{{ $termLabel }}</h3>
                        <div class="flex gap-2">
                            <label for="review-school-year" class="sr-only">Academic year</label>
                            <select id="review-school-year" wire:model.live="school_year" class="rounded-md border border-brand-200 px-2 py-1 text-xs">
                                <option value="">Academic year</option>
                                @foreach ($schoolYears as $year)<option value="{{ $year }}">AY {{ $year }}</option>@endforeach
                            </select>
                            <label for="review-semester" class="sr-only">Semester</label>
                            <select id="review-semester" wire:model.live="semester" class="rounded-md border border-brand-200 px-2 py-1 text-xs">
                                <option value="">Semester</option>
                                @foreach ($semesters as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    @if ($reviewCount > 0)
                        <p class="inline-flex items-center gap-1 text-xs font-medium text-amber-800"><i class="ri-error-warning-line" aria-hidden="true"></i>{{ $reviewCount }} {{ \Illuminate\Support\Str::plural('row', $reviewCount) }} need review. Check the highlighted rows.</p>
                    @endif
                </div>
                @error('school_year') <p class="px-5 pt-3 text-sm text-red-700 sm:px-6">{{ $message }}</p> @enderror
                @error('semester') <p class="px-5 pt-3 text-sm text-red-700 sm:px-6">{{ $message }}</p> @enderror
                @error('rows') <p class="mx-5 mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 sm:mx-6" role="alert">{{ $message }}</p> @enderror

                @if ($warnings !== [])
                    <ul class="mx-5 mt-3 list-disc space-y-1 rounded-lg bg-amber-50 py-2 pl-8 pr-3 text-sm text-amber-900 sm:mx-6">
                        @foreach ($warnings as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th scope="col" class="px-5 py-3 sm:px-6">Subject code</th>
                                <th scope="col" class="px-3 py-3">Subject name</th>
                                <th scope="col" class="px-3 py-3">Units</th>
                                <th scope="col" class="px-3 py-3">Final grade</th>
                                <th scope="col" class="px-3 py-3">Remarks</th>
                                <th scope="col" class="px-5 py-3 sm:px-6"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-200/60">
                            @foreach ($rows as $index => $row)
                                <tr wire:key="review-row-{{ $index }}" class="{{ ! empty($row['needs_review']) ? 'bg-amber-50' : 'bg-white' }} align-top">
                                    @if ($editingRow === $index)
                                        <td class="px-5 py-2 sm:px-6"><input aria-label="Subject code, row {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.subject_code" class="{{ $cellInput }} w-28 font-mono"></td>
                                        <td class="px-3 py-2"><input aria-label="Subject name, row {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.subject_name" class="{{ $cellInput }} min-w-56"></td>
                                        <td class="px-3 py-2"><input aria-label="Units, row {{ $index + 1 }}" type="text" inputmode="decimal" wire:model.blur="rows.{{ $index }}.units" class="{{ $cellInput }} w-16"></td>
                                        <td class="px-3 py-2"><input aria-label="Final grade, row {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.final_grade" class="{{ $cellInput }} w-24"></td>
                                        <td class="px-3 py-2"><input aria-label="Remarks, row {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.remarks" class="{{ $cellInput }} w-28"></td>
                                        <td class="whitespace-nowrap px-5 py-2 text-right sm:px-6">
                                            <button type="button" wire:click="stopEditing" class="rounded-md p-1.5 text-brand-900 hover:bg-brand-50" aria-label="Done editing row {{ $index + 1 }}"><i class="ri-check-line" aria-hidden="true"></i></button>
                                            <button type="button" wire:click="removeRow({{ $index }})" class="rounded-md p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-800" aria-label="Remove row {{ $index + 1 }}"><i class="ri-delete-bin-line" aria-hidden="true"></i></button>
                                        </td>
                                    @else
                                        <td class="whitespace-nowrap px-5 py-3 font-mono text-gray-700 sm:px-6">{{ $row['subject_code'] !== '' ? $row['subject_code'] : '—' }}</td>
                                        <td class="px-3 py-3 text-gray-900">
                                            {{ $row['subject_name'] !== '' ? $row['subject_name'] : '—' }}
                                            @if (! empty($row['warnings']))
                                                <p class="mt-1 text-xs text-amber-800"><i class="ri-error-warning-line mr-0.5" aria-hidden="true"></i>{{ implode(' ', $row['warnings']) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-gray-700">{{ $row['units'] !== '' ? $row['units'] : '—' }}</td>
                                        <td class="px-3 py-3 font-mono font-semibold {{ $gradeTone($row) }}">{{ $row['final_grade'] !== '' ? $row['final_grade'] : '—' }}</td>
                                        <td class="px-3 py-3 text-gray-700">{{ $row['remarks'] !== '' ? ucfirst(strtolower($row['remarks'])) : '—' }}</td>
                                        <td class="px-5 py-3 text-right sm:px-6">
                                            <button type="button" wire:click="editRow({{ $index }})" class="rounded-md p-1.5 text-gray-500 hover:bg-brand-50 hover:text-brand-900" aria-label="Edit row {{ $index + 1 }} ({{ $row['subject_code'] !== '' ? $row['subject_code'] : 'blank' }})"><i class="ri-pencil-line" aria-hidden="true"></i></button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-5 pb-4 sm:px-6">
                    <button type="button" wire:click="addRow" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-900 hover:underline"><i class="ri-add-line" aria-hidden="true"></i>Add a missing subject</button>
                    @include('livewire.student.partials.grade-gwa-summary')
                </div>

                <div class="flex flex-col gap-3 border-t border-brand-200 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="flex items-center gap-4 text-sm">
                        <button type="button" wire:click="startOver" class="inline-flex items-center gap-1 font-medium text-gray-700 hover:text-brand-900"><i class="ri-arrow-left-line" aria-hidden="true"></i>{{ $mode === 'upload' ? 'Re-upload' : 'Paste again' }}</button>
                        <span class="text-gray-500">{{ count($editedRows) }} {{ \Illuminate\Support\Str::plural('row', count($editedRows)) }} edited</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,confirm" class="text-sm font-medium text-brand-900 hover:underline disabled:opacity-60">Save draft</button>
                        <button type="button" wire:click="confirm" data-confirm="They will count toward your GWA and your next prediction." data-confirm-title="Confirm these grades?" data-confirm-button="Confirm & save" data-confirm-tone="neutral" wire:loading.attr="disabled" wire:target="confirm,saveDraft" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                            <x-spinner wire:loading wire:target="confirm" /><i class="ri-checkbox-circle-line" wire:loading.remove wire:target="confirm" aria-hidden="true"></i>Confirm &amp; Save Grades
                        </button>
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>
