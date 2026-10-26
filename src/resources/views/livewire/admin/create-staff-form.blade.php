<div>
    @if ($show)
        <x-dialog title="Add user" close="closeForm" description="This form adds a staff account. Student logins are issued from Eligible students with a one-time temporary password.">
            <form id="staff-form" wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Creating account…" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="staff-name" class="mb-1 block text-sm font-medium">Name</label>
                        <input id="staff-name" type="text" wire:model="name" class="ui-field">
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="staff-email" class="mb-1 block text-sm font-medium">Email</label>
                        <input id="staff-email" type="email" wire:model="email" class="ui-field">
                        @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="staff-role" class="mb-1 block text-sm font-medium">Role</label>
                        <select id="staff-role" wire:model.live="role" class="ui-field">
                            @foreach ($roles as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    @if ($role === \App\Enums\UserRole::Dean->value)
                        <div>
                            <label for="staff-college" class="mb-1 block text-sm font-medium">College</label>
                            <select id="staff-college" wire:model="college_id" class="ui-field">
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
                            <select id="staff-department" wire:model.live="department_id" class="ui-field">
                                <option value="">Select department</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->college?->code }})</option>
                                @endforeach
                            </select>
                            @error('department_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="staff-program" class="mb-1 block text-sm font-medium">Limit to one program (optional)</label>
                            <select id="staff-program" wire:model="program_id" class="ui-field">
                                <option value="">Whole department</option>
                                @foreach ($programs->where('department_id', $department_id) as $program)
                                    <option value="{{ $program->id }}">{{ $program->code }} — {{ $program->name }}</option>
                                @endforeach
                            </select>
                            @error('program_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
                @if ($temporaryPassword)
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">Temporary password: <code>{{ $temporaryPassword }}</code>. Share it privately. The new user must change it at first sign-in.</p>
                @endif
            </form>
            <x-slot:footer>
                <x-dialog-actions form="staff-form" submit="Create account" />
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
