<div>
    @if ($show)
        <x-dialog :title="$itemId ? 'Edit questionnaire item' : 'Add questionnaire item'" close="closeForm" description="Definitions with student answers stay locked. Add a new version instead of rewriting them.">
            <form wire:submit="save" class="space-y-4 px-5 py-5 sm:px-6">
                @if ($statusMessage !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
                @endif
                @if ($locked)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">This definition has student answers and is locked. Create a new version instead.</p>
                @endif
                @error('item') <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="definition_version" class="mb-1 block text-sm font-medium">Definition version</label>
                        <select id="definition_version" wire:model="definition_version" @disabled($locked) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            @foreach ($versions as $value => $definition)
                                <option value="{{ $value }}">{{ $definition['label'] ?? $value }}</option>
                            @endforeach
                        </select>
                        @error('definition_version') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="section" class="mb-1 block text-sm font-medium">Questionnaire section</label>
                        <select id="section" wire:model="section" @disabled($locked) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            @foreach ($sections as $value => $definition)
                                <option value="{{ $value }}">{{ $definition['label'] ?? $value }}</option>
                            @endforeach
                        </select>
                        @error('section') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="construct" class="mb-1 block text-sm font-medium">Construct</label>
                        <select id="construct" wire:model="construct" @disabled($locked) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            @foreach ($constructs as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('construct') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sort_order" class="mb-1 block text-sm font-medium">Sort order</label>
                        <input id="sort_order" type="number" min="0" max="999" wire:model="sort_order" @disabled($locked) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('sort_order') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="item-text" class="mb-1 block text-sm font-medium">Statement</label>
                        <textarea id="item-text" wire:model="text" rows="3" @disabled($locked) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
                        @error('text') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="reverse_scored" @disabled($locked) class="rounded border-brand-200 text-brand-900"> Reverse scored</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_active" @disabled($locked) class="rounded border-brand-200 text-brand-900"> Active</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_draft" @disabled($locked) class="rounded border-brand-200 text-brand-900"> Draft scale item</label>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Cancel</button>
                    @unless ($locked)
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                            <x-spinner wire:loading wire:target="save" />
                            Save item
                        </button>
                    @endunless
                </div>
            </form>
        </x-dialog>
    @endif
</div>
