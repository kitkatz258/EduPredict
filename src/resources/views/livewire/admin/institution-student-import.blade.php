<div class="flex justify-end">
    <button type="button" wire:click="openImport" wire:loading.attr="disabled" wire:target="openImport" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
        <i class="ri-upload-2-line" aria-hidden="true"></i>Import CSV
    </button>
</div>

@if ($open)
    <x-dialog title="Import eligible students" close="closeImport" description="Registration still accepts only student numbers on this list. Columns: student_number, last_name, first_name, program_code, year_level. Optional: email, birthdate.">
        <form wire:submit="import" class="relative space-y-4 px-5 py-5 sm:px-6">
            <x-loading-overlay target="import" label="Importing the list…" />
            <section class="rounded-2xl border-2 border-dashed border-brand-200 bg-white p-6 text-center sm:p-8" x-data="{ dragging: false }" x-bind:class="dragging && 'border-brand-400 bg-brand-50'">
                <label for="eligible-csv" class="relative block cursor-pointer rounded-xl focus-within:ring-2 focus-within:ring-brand-200"
                    x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop="dragging = false">
                    <input id="eligible-csv" type="file" wire:model="csv" accept=".csv,text/csv" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" aria-describedby="eligible-csv-help">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-2xl text-brand-900"><i class="ri-file-upload-line" aria-hidden="true"></i></span>
                    <span class="mt-4 block text-base font-semibold text-gray-900">Drop a CSV file here</span>
                    <span id="eligible-csv-help" class="mt-1 block text-sm text-gray-600">or click to browse. CSV up to 2 MB.</span>
                </label>
                <p class="mt-3 text-sm text-brand-900" wire:loading wire:target="csv" role="status"><x-spinner class="mr-1" />Uploading file…</p>
                @error('csv') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                @if ($csv && ! $errors->has('csv'))
                    <p class="mx-auto mt-4 max-w-md truncate rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="status">
                        <i class="ri-file-line mr-1" aria-hidden="true"></i>{{ $csv->getClientOriginalName() }}
                    </p>
                @endif
            </section>
            @if ($imported > 0)
                <p class="text-sm text-brand-900" role="status">Imported {{ $imported }} row(s).</p>
            @endif
            @if ($errorsList !== [])
                <ul class="list-disc space-y-1 pl-5 text-sm text-red-800" role="alert">
                    @foreach ($errorsList as $error)
                        <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" x-on:click="requestClose()" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="import,csv" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                    <x-spinner wire:loading wire:target="import" />Import
                </button>
            </div>
        </form>
    </x-dialog>
@endif
