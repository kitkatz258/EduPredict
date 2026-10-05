<div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-brand-900">Create staff account</h2>
    <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="staff-name" class="mb-1 block text-sm font-medium">Name</label>
            <input id="staff-name" type="text" wire:model="name" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="staff-email" class="mb-1 block text-sm font-medium">Email</label>
            <input id="staff-email" type="email" wire:model="email" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="staff-role" class="mb-1 block text-sm font-medium">Role</label>
            <select id="staff-role" wire:model="role" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="faculty">Faculty</option>
                <option value="department_head">Department Head</option>
                <option value="dean">Dean</option>
                <option value="administrator">Administrator</option>
            </select>
        </div>
        <div>
            <label for="staff-college" class="mb-1 block text-sm font-medium">College</label>
            <select id="staff-college" wire:model="college_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="">None</option>
                @foreach ($colleges as $college)
                    <option value="{{ $college->id }}">{{ $college->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="staff-program" class="mb-1 block text-sm font-medium">Program</label>
            <select id="staff-program" wire:model="program_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="">None</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}">{{ $program->code }} — {{ $program->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Create account</button>
        </div>
    </form>
    @if ($temporaryPassword)
        <p class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900">Temporary password: <code>{{ $temporaryPassword }}</code></p>
    @endif
</div>
