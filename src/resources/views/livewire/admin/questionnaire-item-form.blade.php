<form wire:submit="save" class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
    <h2 class="mt-1 text-lg font-semibold text-brand-900">{{ $itemId ? 'Edit item' : 'Add item' }}</h2>
    @if ($statusMessage !== '')
        <p class="mt-3 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="construct" class="mb-1 block text-sm font-medium">Construct</label>
            <select id="construct" wire:model="construct" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                @foreach ($constructs as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('construct') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="sort_order" class="mb-1 block text-sm font-medium">Sort order</label>
            <input id="sort_order" type="number" min="0" max="999" wire:model="sort_order" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('sort_order') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="item-text" class="mb-1 block text-sm font-medium">Statement</label>
            <textarea id="item-text" wire:model="text" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
            @error('text') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-4 text-sm">
        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="reverse_scored" class="rounded border-brand-200 text-brand-900"> Reverse scored</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_active" class="rounded border-brand-200 text-brand-900"> Active</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_draft" class="rounded border-brand-200 text-brand-900"> Draft scale item</label>
    </div>
    <button type="submit" wire:loading.attr="disabled" class="mt-4 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Save item</button>
</form>
