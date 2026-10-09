<div>
    @if ($show)
        <x-dialog :title="$programId ? 'Edit program' : 'Add program'" close="closeForm" description="Programs stay linked to a college and, when set, one department.">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving program…" />
                @if ($statusMessage !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="program-college" class="mb-1 block text-sm font-medium">College</label>
                        <select id="program-college" wire:model.live="collegeId" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select college</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}{{ $college->is_active ? '' : ' (legacy)' }}</option>
                            @endforeach
                        </select>
                        @error('collegeId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="program-department" class="mb-1 block text-sm font-medium">Department</label>
                        <select id="program-department" wire:model="departmentId" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">No department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('departmentId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="program-name" class="mb-1 block text-sm font-medium">Name</label>
                        <input id="program-name" type="text" wire:model="name" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="program-code" class="mb-1 block text-sm font-medium">Code</label>
                        <input id="program-code" type="text" wire:model="code" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                        <x-spinner wire:loading wire:target="save" />
                        Save program
                    </button>
                </div>
            </form>
        </x-dialog>
    @endif
</div>
