<form wire:submit="save" class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
    <h3 class="text-base font-semibold text-brand-900">{{ $interventionId ? 'Edit intervention' : 'Add intervention' }}</h3>
    <p class="mt-1 text-sm text-gray-600">This list is the only source of recommended actions. Saving here does not rewrite actions already stored on a prediction.</p>
    @if ($statusMessage !== '')
        <p class="mt-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="intervention-code" class="mb-1 block text-sm font-medium">Code</label>
            <input id="intervention-code" type="text" wire:model="code" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="academic_tutoring">
            @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="intervention-risk" class="mb-1 block text-sm font-medium">Minimum risk</label>
            <select id="intervention-risk" wire:model="minRiskLevel" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="low">Low</option>
                <option value="moderate">Moderate</option>
                <option value="high">High</option>
            </select>
            @error('minRiskLevel') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="intervention-title" class="mb-1 block text-sm font-medium">Title</label>
            <input id="intervention-title" type="text" wire:model="title" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="intervention-description" class="mb-1 block text-sm font-medium">Description</label>
            <textarea id="intervention-description" wire:model="description" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
            <p class="mt-1 text-xs text-gray-500">This text is the rule-based phrasing when AI is unavailable.</p>
            @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="intervention-targets" class="mb-1 block text-sm font-medium">Target factors</label>
            <input id="intervention-targets" type="text" wire:model="targets" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="gwa, failed_subjects">
            @error('targets') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
    <button type="submit" wire:loading.attr="disabled" class="mt-4 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Save intervention</button>
</form>
