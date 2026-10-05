<div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-brand-900">Adviser assignment</h2>
    <form wire:submit="assign" class="mt-4 space-y-4">
        <div>
            <label for="faculty" class="mb-1 block text-sm font-medium">Faculty adviser</label>
            <select id="faculty" wire:model="facultyId" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="">Select faculty</option>
                @foreach ($faculty as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
            @error('facultyId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <fieldset>
            <legend class="mb-2 text-sm font-medium">Students (bulk)</legend>
            <div class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-brand-200 p-3">
                @foreach ($students as $student)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="studentIds" value="{{ $student->id }}">
                        {{ $student->student_number }} — {{ $student->user->name }}
                    </label>
                @endforeach
            </div>
            @error('studentIds') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </fieldset>
        <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Assign adviser</button>
    </form>
</div>
