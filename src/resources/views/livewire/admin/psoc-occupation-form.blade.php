<div>
    @if ($show)
        <x-dialog :title="$readOnly ? 'View occupation' : ($occupationId ? 'Edit occupation' : 'Add occupation')" close="closeForm" description="Editing a row does not change compatibility scores already stored on a prediction.">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving occupation…" />
                @if ($statusMessage !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="psoc-code" class="mb-1 block text-sm font-medium">PSOC code</label>
                        <input id="psoc-code" type="text" wire:model="psocCode" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('psocCode') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="psoc-group" class="mb-1 block text-sm font-medium">Major group</label>
                        <input id="psoc-group" type="text" wire:model="majorGroup" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('majorGroup') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="psoc-title" class="mb-1 block text-sm font-medium">Title</label>
                        <input id="psoc-title" type="text" wire:model="title" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="psoc-description" class="mb-1 block text-sm font-medium">Description</label>
                        <textarea id="psoc-description" wire:model="description" rows="3" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
                        @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="psoc-skills" class="mb-1 block text-sm font-medium">Skill tags</label>
                        <input id="psoc-skills" type="text" wire:model="skillTags" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="programming, sql">
                        @error('skillTags') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="psoc-programs" class="mb-1 block text-sm font-medium">Related program codes</label>
                        <input id="psoc-programs" type="text" wire:model="programCodes" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="BSIS, BSIT">
                        @error('programCodes') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" x-on:click="requestClose()" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">{{ $readOnly ? 'Close' : 'Cancel' }}</button>
                    @unless ($readOnly)
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                            <x-spinner wire:loading wire:target="save" />
                            Save occupation
                        </button>
                    @endunless
                </div>
            </form>
        </x-dialog>
    @endif
</div>
