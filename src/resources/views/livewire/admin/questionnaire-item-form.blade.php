<div>
    @if ($show)
        <x-dialog :title="$itemId ? 'Edit questionnaire item' : 'Add questionnaire item'" close="closeForm" description="Published questionnaires stay unchanged. Draft questions can be edited until the version is published.">
            <form id="questionnaire-item-form" wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save,confirmSave" label="Saving question…" />
                <p class="text-sm font-medium text-brand-900">Editing: {{ $editingLabel }}</p>
                <p id="position-help" class="text-sm text-gray-600">{{ $positionLabel }}</p>
                @if ($locked)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">This question is locked. Create an editable copy of the questionnaire instead of changing it.</p>
                @endif
                @error('item') <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="section" class="mb-1 block text-sm font-medium">Section</label>
                        <select id="section" wire:model.live="section" @disabled($locked) class="ui-field" aria-describedby="position-help">
                            @foreach ($sections as $value => $definition)
                                <option value="{{ $value }}">{{ $definition['label'] ?? $value }}</option>
                            @endforeach
                        </select>
                        @error('section') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="category" class="mb-1 block text-sm font-medium">Category</label>
                        <select id="category" wire:model="construct" @disabled($locked || $categories === []) class="ui-field">
                            @forelse ($categories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @empty
                                <option value="">No categories configured</option>
                            @endforelse
                        </select>
                        @if ($categories === [])
                            <p class="mt-1 text-sm text-gray-600">No categories are configured for this section yet.</p>
                        @endif
                        @error('construct') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="item-text" class="mb-1 block text-sm font-medium">Question text</label>
                        <textarea id="item-text" wire:model="text" rows="3" required @disabled($locked) class="ui-field"></textarea>
                        @error('text') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    @if ($allowsReverse)
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="reverse_scored" @disabled($locked) class="rounded border-brand-200 text-brand-900"> Reverse scored</label>
                        @error('reverse_scored') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    @elseif ($locked)
                        <p class="text-sm text-gray-600">Reverse scoring is historical metadata for this version and is not editable. Stored value: {{ $reverse_scored ? 'Yes' : 'No' }}.</p>
                    @endif
                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model="is_active" @disabled($locked) class="rounded border-brand-200 text-brand-900"> Active</label>
                </div>
            </form>
            <x-slot:footer>
                <x-dialog-actions form="questionnaire-item-form" :submit="$itemId ? 'Save Changes' : 'Add'" :read-only="$locked" />
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
