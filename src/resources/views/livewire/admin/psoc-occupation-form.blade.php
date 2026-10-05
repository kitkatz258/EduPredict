<form wire:submit="save" class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
    <h3 class="text-base font-semibold text-brand-900">{{ $occupationId ? 'Edit occupation' : 'Add occupation' }}</h3>
    <p class="mt-1 text-sm text-gray-600">Editing a row does not change compatibility scores already stored on a prediction.</p>
    @if ($statusMessage !== '')
        <p class="mt-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="psoc-code" class="mb-1 block text-sm font-medium">PSOC code</label>
            <input id="psoc-code" type="text" wire:model="psocCode" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('psocCode') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="psoc-group" class="mb-1 block text-sm font-medium">Major group</label>
            <input id="psoc-group" type="text" wire:model="majorGroup" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('majorGroup') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="psoc-title" class="mb-1 block text-sm font-medium">Title</label>
            <input id="psoc-title" type="text" wire:model="title" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="psoc-description" class="mb-1 block text-sm font-medium">Description</label>
            <textarea id="psoc-description" wire:model="description" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="psoc-skills" class="mb-1 block text-sm font-medium">Skill tags</label>
            <input id="psoc-skills" type="text" wire:model="skillTags" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="programming, sql">
            @error('skillTags') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="psoc-programs" class="mb-1 block text-sm font-medium">Related program codes</label>
            <input id="psoc-programs" type="text" wire:model="programCodes" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="BSIS, BSIT">
            @error('programCodes') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
    <button type="submit" wire:loading.attr="disabled" class="mt-4 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Save occupation</button>
</form>
