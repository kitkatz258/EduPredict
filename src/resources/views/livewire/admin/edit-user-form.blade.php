<div>
    @if ($show)
        <x-dialog title="Edit user" close="closeForm" description="Role changes stay inside the active staff roles. Prediction history is kept.">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving user…" />
                @if ($statusMessage !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit-user-name" class="mb-1 block text-sm font-medium">Name</label>
                        <input id="edit-user-name" type="text" wire:model="name" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="edit-user-email" class="mb-1 block text-sm font-medium">Email</label>
                        <input id="edit-user-email" type="email" wire:model="email" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    @if ($roleLocked)
                        <div class="sm:col-span-2">
                            <p class="text-sm text-gray-700">Role: <span class="font-medium text-brand-900">{{ $roleLabel }}</span></p>
                            @if ($role === \App\Enums\UserRole::Faculty->value)
                                <p class="mt-1 text-xs text-gray-500">This legacy faculty role stays on the account and cannot be assigned again.</p>
                            @elseif ($role === \App\Enums\UserRole::Student->value)
                                <p class="mt-1 text-xs text-gray-500">Student accounts come from registration. Email is used for password recovery.</p>
                            @else
                                <p class="mt-1 text-xs text-gray-500">Your own role stays as it is.</p>
                            @endif
                        </div>
                    @else
                        <div>
                            <label for="edit-user-role" class="mb-1 block text-sm font-medium">Role</label>
                            <select id="edit-user-role" wire:model.live="role" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                                @foreach ($roles as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        @if ($role === \App\Enums\UserRole::Dean->value)
                            <div>
                                <label for="edit-user-college" class="mb-1 block text-sm font-medium">College</label>
                                <select id="edit-user-college" wire:model="college_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
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
                                <label for="edit-user-department" class="mb-1 block text-sm font-medium">Department</label>
                                <select id="edit-user-department" wire:model.live="department_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                                    <option value="">Select department</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->college?->code }})</option>
                                    @endforeach
                                </select>
                                @error('department_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="edit-user-program" class="mb-1 block text-sm font-medium">Limit to one program (optional)</label>
                                <select id="edit-user-program" wire:model="program_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                                    <option value="">Whole department</option>
                                    @foreach ($programs->where('department_id', $department_id) as $program)
                                        <option value="{{ $program->id }}">{{ $program->code }} — {{ $program->name }}</option>
                                    @endforeach
                                </select>
                                @error('program_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    @endif
                    @if ($role !== \App\Enums\UserRole::Student->value)
                        <div class="sm:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="resetPassword" class="rounded border-brand-200 text-brand-900">
                                Issue a new temporary password
                            </label>
                        </div>
                    @endif
                </div>
                @if ($temporaryPassword)
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">Temporary password: <code>{{ $temporaryPassword }}</code></p>
                @endif
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" x-on:click="requestClose()" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                        <x-spinner wire:loading wire:target="save" />
                        Save user
                    </button>
                </div>
            </form>
        </x-dialog>
    @endif
</div>
