<div>
    <button type="button" wire:click="openImport" wire:loading.attr="disabled" wire:target="openImport" class="inline-flex items-center justify-center gap-2 rounded-lg border border-brand-900 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60">
        <i class="ri-upload-2-line" aria-hidden="true"></i>Import CSV
    </button>

    @if ($open)
        <x-dialog title="Import eligible students" close="closeImport" description="Registration still accepts only student numbers on this list. This import does not create accounts. Columns: student_number, last_name, first_name, program_code, year_level. Optional: email, birthdate.">
            <form wire:submit="import" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="import" label="Importing the list…" />
                <div>
                    <label for="eligible-csv" class="mb-1 block text-sm font-medium">CSV file</label>
                    <input id="eligible-csv" type="file" wire:model="csv" accept=".csv,text/csv,.txt,text/plain" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-900" aria-describedby="eligible-csv-help">
                    <p id="eligible-csv-help" class="mt-1 text-sm text-gray-600">CSV or text file up to 2 MB. A header row is required. Uploading the file does not add students until you choose Import.</p>
                    <p class="mt-2 text-sm text-brand-900" wire:loading wire:target="csv" role="status"><x-spinner class="mr-1" />Uploading file…</p>
                    @error('csv') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    @if ($csv && ! $errors->has('csv'))
                        <p class="mt-2 truncate text-sm text-brand-900" role="status">
                            <i class="ri-file-line mr-1" aria-hidden="true"></i>{{ $csv->getClientOriginalName() }}
                        </p>
                    @endif
                </div>
                @if ($summary !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $summary }}</p>
                @endif
                @if ($skippedList !== [])
                    <div>
                        <p class="text-sm font-medium text-brand-900">Skipped</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-gray-700">
                            @foreach ($skippedList as $skip)
                                <li>Row {{ $skip['row'] }}: {{ $skip['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($errorsList !== [])
                    <div>
                        <p class="text-sm font-medium text-red-800">Could not import</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-red-800" role="alert">
                            @foreach ($errorsList as $error)
                                <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" x-on:click="requestClose()" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Close</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="import,csv" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                        <x-spinner wire:loading wire:target="import" />Import
                    </button>
                </div>
            </form>
        </x-dialog>
    @endif
</div>
