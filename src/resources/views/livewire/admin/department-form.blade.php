<div>
    @if ($show)
        <x-dialog :title="$departmentId ? 'Edit department' : 'Add department'" close="closeForm" description="A department can hold more than one program. Existing programs keep their records.">
            <form wire:submit="save" class="space-y-4 px-5 py-5 sm:px-6">
                @if ($statusMessage !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="department-college" class="mb-1 block text-sm font-medium">College</label>
                        <select id="department-college" wire:model="collegeId" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select college</option>
                            @foreach ($colleges as $college)
                                <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}{{ $college->is_active ? '' : ' (legacy)' }}</option>
                            @endforeach
                        </select>
                        @error('collegeId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="department-name" class="mb-1 block text-sm font-medium">Name</label>
                        <input id="department-name" type="text" wire:model="name" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="department-code" class="mb-1 block text-sm font-medium">Code</label>
                        <input id="department-code" type="text" wire:model="code" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="isActive" class="rounded border-brand-200 text-brand-900">
                            Active
                        </label>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                        <x-spinner wire:loading wire:target="save" />
                        Save department
                    </button>
                </div>
            </form>
        </x-dialog>
    @endif
</div>
