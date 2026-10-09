<div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-brand-900">CSV import</h2>
    <p class="mt-1 text-sm text-gray-600">Columns: student_number, last_name, first_name, program_code, year_level. Optional: email, birthdate.</p>
    <form wire:submit="import" class="relative mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <x-loading-overlay target="import" label="Importing the list…" />
        <div>
            <label for="csv" class="mb-1 block text-sm font-medium">CSV file</label>
            <input id="csv" type="file" wire:model="csv" accept=".csv,text/csv">
            <p class="mt-2 text-sm text-brand-900" wire:loading wire:target="csv" role="status"><x-spinner class="mr-1" />Uploading file…</p>
            @error('csv') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="import,csv" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60"><x-spinner wire:loading wire:target="import" />Import</button>
    </form>
    @if ($imported > 0)
        <p class="mt-3 text-sm text-brand-900" role="status">Imported {{ $imported }} row(s).</p>
    @endif
    @if ($errorsList !== [])
        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-red-800" role="alert">
            @foreach ($errorsList as $error)
                <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
            @endforeach
        </ul>
    @endif
</div>
