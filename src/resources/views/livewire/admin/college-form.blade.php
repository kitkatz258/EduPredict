<div>
    @if ($show)
        <x-dialog :title="$collegeId ? 'Edit college' : 'Add college'" close="closeForm" description="Colleges stay in place so staff and programs keep their links.">
            <form id="college-form" wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving college…" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="college-name" class="mb-1 block text-sm font-medium">Name</label>
                        <input id="college-name" type="text" wire:model="name" class="ui-field">
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="college-code" class="mb-1 block text-sm font-medium">Code</label>
                        <input id="college-code" type="text" wire:model="code" class="ui-field">
                        @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </form>
            <x-slot:footer>
                <x-dialog-actions form="college-form" submit="Save college" />
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
