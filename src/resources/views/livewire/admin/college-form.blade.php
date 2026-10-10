<div>
    @if ($show)
        <x-dialog :title="$collegeId ? 'Edit college' : 'Add college'" close="closeForm" description="Colleges stay in place so staff and programs keep their links.">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving college…" />
                @if ($statusMessage !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="college-name" class="mb-1 block text-sm font-medium">Name</label>
                        <input id="college-name" type="text" wire:model="name" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="college-code" class="mb-1 block text-sm font-medium">Code</label>
                        <input id="college-code" type="text" wire:model="code" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" x-on:click="requestClose()" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                        <x-spinner wire:loading wire:target="save" />
                        Save college
                    </button>
                </div>
            </form>
        </x-dialog>
    @endif
</div>
