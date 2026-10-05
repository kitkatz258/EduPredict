<div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-brand-900">CSV import</h2>
    <p class="mt-1 text-sm text-gray-600">Columns: student_number, last_name, first_name, program_code, year_level. Optional: email, birthdate.</p>
    <form wire:submit="import" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div>
            <label for="csv" class="mb-1 block text-sm font-medium">CSV file</label>
            <input id="csv" type="file" wire:model="csv" accept=".csv,text/csv">
            @error('csv') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Import</button>
    </form>
    @if ($imported > 0)
        <p class="mt-3 text-sm text-brand-900">Imported {{ $imported }} row(s).</p>
    @endif
    @if ($errorsList !== [])
        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-red-800">
            @foreach ($errorsList as $error)
                <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
            @endforeach
        </ul>
    @endif
</div>
