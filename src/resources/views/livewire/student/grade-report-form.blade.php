<div class="space-y-6">
    <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-brand-900">Add or review a term</h2>
        <p class="mt-1 text-sm text-gray-600">Source: <span class="font-medium text-brand-900">{{ str_replace('_', ' ', $source) }}</span>@if ($usedAiFallback) · AI fallback used @endif</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label for="school_year" class="mb-1 block text-sm font-medium">School year</label>
                <input id="school_year" type="text" wire:model="school_year" placeholder="2024-2025" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                @error('school_year') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="semester" class="mb-1 block text-sm font-medium">Semester</label>
                <select id="semester" wire:model="semester" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="">Select</option>
                    <option value="First">First</option>
                    <option value="Second">Second</option>
                    <option value="Midyear">Midyear</option>
                </select>
                @error('semester') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="detected_gpa" class="mb-1 block text-sm font-medium">GPA on source (optional)</label>
                <input id="detected_gpa" type="text" wire:model="detected_gpa" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div>
                <label for="pastedText" class="mb-1 block text-sm font-medium">Paste from portal</label>
                <textarea id="pastedText" wire:model="pastedText" rows="6" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="Copy the grades table from the portal and paste it here."></textarea>
                @error('pastedText') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <button type="button" wire:click="parsePaste" wire:loading.attr="disabled" class="mt-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Parse pasted table</button>
            </div>
            <div>
                <label for="upload" class="mb-1 block text-sm font-medium">Upload PDF or image</label>
                <input id="upload" type="file" wire:model="upload" accept=".pdf,.png,.jpg,.jpeg,.webp">
                @error('upload') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <button type="button" wire:click="parseUpload" wire:loading.attr="disabled" class="mt-2 rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Parse upload</button>
                <p class="mt-2 text-xs text-gray-500">Paste works without an AI key. Uploads use PDF text or OCR, then manual review.</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-brand-900">Subject rows</h3>
                <p class="text-sm text-gray-600">
                    Computed GPA:
                    <span class="font-medium text-brand-900">{{ $gwa['rounded'] !== null ? number_format($gwa['rounded'], 2) : '—' }}</span>
                    @if ($detected_gpa)
                        · Source GPA: {{ $detected_gpa }}
                    @endif
                    · Failed subjects: {{ $gwa['failed'] }}
                </p>
                @if ($gwa['mismatch'])
                    <p class="mt-1 text-sm text-amber-800" role="status">Computed GPA differs from the source GPA by more than 0.01. Check OCR errors or a rule difference (NSTP excluded; INC counts as 4.00).</p>
                @endif
            </div>
            <button type="button" wire:click="addRow" class="rounded-lg border border-brand-200 px-3 py-2 text-sm text-brand-900">Add row</button>
        </div>

        @error('rows') <p class="mb-3 text-sm text-red-700">{{ $message }}</p> @enderror

        @if ($warnings !== [])
            <ul class="mb-4 list-disc space-y-1 pl-5 text-sm text-amber-800">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-2 py-2 text-left font-semibold text-brand-900">Code</th>
                        <th class="px-2 py-2 text-left font-semibold text-brand-900">Description</th>
                        <th class="px-2 py-2 text-left font-semibold text-brand-900">Units</th>
                        <th class="px-2 py-2 text-left font-semibold text-brand-900">Midterm</th>
                        <th class="px-2 py-2 text-left font-semibold text-brand-900">Final exam</th>
                        <th class="px-2 py-2 text-left font-semibold text-brand-900">Final grade</th>
                        <th class="px-2 py-2 text-left font-semibold text-brand-900">Remarks</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr class="{{ !empty($row['needs_review']) ? 'bg-amber-50' : ($index % 2 === 0 ? 'bg-white' : 'bg-brand-50') }}">
                            <td class="px-2 py-2"><input aria-label="Subject code {{ $index + 1 }}" type="text" wire:model.live.debounce.400ms="rows.{{ $index }}.subject_code" class="w-28 rounded border border-brand-200 px-2 py-1"></td>
                            <td class="px-2 py-2"><input aria-label="Description {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.subject_name" class="w-56 rounded border border-brand-200 px-2 py-1"></td>
                            <td class="px-2 py-2"><input aria-label="Units {{ $index + 1 }}" type="text" wire:model.live.debounce.400ms="rows.{{ $index }}.units" class="w-16 rounded border border-brand-200 px-2 py-1"></td>
                            <td class="px-2 py-2"><input aria-label="Midterm {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.midterm_grade" class="w-16 rounded border border-brand-200 px-2 py-1"></td>
                            <td class="px-2 py-2"><input aria-label="Final exam {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.final_exam_grade" class="w-16 rounded border border-brand-200 px-2 py-1"></td>
                            <td class="px-2 py-2"><input aria-label="Final grade {{ $index + 1 }}" type="text" wire:model.live.debounce.400ms="rows.{{ $index }}.final_grade" class="w-16 rounded border border-brand-200 px-2 py-1"></td>
                            <td class="px-2 py-2"><input aria-label="Remarks {{ $index + 1 }}" type="text" wire:model.blur="rows.{{ $index }}.remarks" class="w-28 rounded border border-brand-200 px-2 py-1"></td>
                            <td class="px-2 py-2">
                                <button type="button" wire:click="removeRow({{ $index }})" class="text-sm text-red-800 underline">Remove</button>
                                @if (!empty($row['warnings']))
                                    <p class="mt-1 text-xs text-amber-800">{{ implode(' ', $row['warnings']) }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" wire:click="saveDraft" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Save draft</button>
            <button type="button" wire:click="confirm" wire:confirm="Confirm this term? It will count toward your GWA." class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Confirm</button>
            <button type="button" wire:click="startManual" class="text-sm text-brand-900 underline">Reset to empty manual entry</button>
        </div>
        <p class="mt-4 text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
    </div>
</div>
