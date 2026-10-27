<div>
    @if ($show)
        <x-dialog :title="$departmentId ? 'Edit department' : 'Add department'" close="closeForm" description="A department can hold more than one program. Existing programs keep their records.">
            <form id="department-form" wire:submit="save" class="space-y-4 px-5 py-5 sm:px-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="department-college" class="mb-1 block text-sm font-medium">College</label>
                        <select id="department-college" wire:model="collegeId" class="ui-field">
                            <option value="">Select college</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}{{ $college->is_active ? '' : ' (legacy)' }}</option>
                            @endforeach
                        </select>
                        @error('collegeId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="department-name" class="mb-1 block text-sm font-medium">Name</label>
                        <input id="department-name" type="text" wire:model="name" class="ui-field">
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="department-code" class="mb-1 block text-sm font-medium">Code</label>
                        <input id="department-code" type="text" wire:model="code" class="ui-field">
                        @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="isActive" class="rounded border-brand-200 text-brand-900">
                            Active
                        </label>
                        @error('is_active') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </form>
            <x-slot:footer>
                <x-dialog-actions form="department-form" submit="Save department" />
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
