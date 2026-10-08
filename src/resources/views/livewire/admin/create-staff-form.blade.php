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
            <select id="staff-role" wire:model.live="role" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                @foreach ($roles as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('role') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        @if ($role === \App\Enums\UserRole::Dean->value)
            <div>
                <label for="staff-college" class="mb-1 block text-sm font-medium">College</label>
                <select id="staff-college" wire:model="college_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="">Select college</option>
                    @foreach ($colleges as $college)
                        <option value="{{ $college->id }}">{{ $college->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Deans see aggregated college figures only.</p>
                @error('college_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        @elseif ($role === \App\Enums\UserRole::DepartmentHead->value)
            <div>
                <label for="staff-department" class="mb-1 block text-sm font-medium">Department</label>
                <select id="staff-department" wire:model.live="department_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="">Select department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->college?->code }})</option>
                    @endforeach
                </select>
                @error('department_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="staff-program" class="mb-1 block text-sm font-medium">Limit to one program (optional)</label>
                <select id="staff-program" wire:model="program_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="">Whole department</option>
                    @foreach ($programs->where('department_id', $department_id) as $program)
                        <option value="{{ $program->id }}">{{ $program->code }} — {{ $program->name }}</option>
                    @endforeach
                </select>
                @error('program_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        @endif
        <div class="sm:col-span-2">
            <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Create account</button>
        </div>
    </form>
    @if ($temporaryPassword)
        <p class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900">Temporary password: <code>{{ $temporaryPassword }}</code></p>
    @endif
</div>
