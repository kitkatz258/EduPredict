<div>
    @if ($show)
        <x-dialog :title="$readOnly ? 'View occupation' : ($occupationId ? 'Edit occupation' : 'Add occupation')" close="closeForm" description="Use official PSA PSOC 2012 codes. Editing a row does not change compatibility scores already stored on a prediction.">
            <form id="psoc-form" wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving occupation…" />
                @if ($statusMessage !== '')
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="psoc-code" class="mb-1 block text-sm font-medium">PSOC code</label>
                        <input id="psoc-code" type="text" wire:model.live.debounce.400ms="psocCode" @disabled($readOnly) class="ui-field" placeholder="e.g. 2512" inputmode="numeric">
                        <p class="mt-1 text-xs text-gray-500">Official 1–4 digit unit-group code. Codes are not auto-incremented.</p>
                        @error('psocCode') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="psoc-group" class="mb-1 block text-sm font-medium">Major group</label>
                        <select id="psoc-group" wire:model="majorGroup" @disabled($readOnly) class="ui-field">
                            <option value="">Select major group</option>
                            @foreach ($majorGroups as $group)
                                <option value="{{ $group }}">{{ $group }}</option>
                            @endforeach
                        </select>
                        @error('majorGroup') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="psoc-title" class="mb-1 block text-sm font-medium">Title</label>
                        <input id="psoc-title" type="text" wire:model="title" @disabled($readOnly) class="ui-field">
                        @error('title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="psoc-description" class="mb-1 block text-sm font-medium">Description</label>
                        <textarea id="psoc-description" wire:model="description" rows="3" @disabled($readOnly) class="w-full rounded-[11px] border border-[#e8eaee] bg-[#f6f7f9] px-3 py-2 text-sm"></textarea>
                        @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <p class="mb-1 text-sm font-medium">Skill tags</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($skillOptions as $tag)
                                <label class="inline-flex items-center gap-2 rounded-full border border-[#e8eaee] bg-white px-3 py-1 text-xs">
                                    <input type="checkbox" value="{{ $tag }}" wire:model="selectedSkills" @disabled($readOnly) class="rounded border-brand-200 text-brand-900">
                                    {{ $tag }}
                                </label>
                            @empty
                                <p class="text-sm text-gray-500">No controlled tags yet.</p>
                            @endforelse
                        </div>
                        @unless ($readOnly)
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input id="psoc-new-skill" type="text" wire:model="newSkillTag" class="ui-field" placeholder="Add a skill tag">
                                <button type="button" wire:click="addSkillTag" class="ui-btn ui-btn-secondary">Add tag</button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">New tags are recorded in the Activity Log.</p>
                            @error('newSkillTag') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        @endunless
                    </div>
                    <div class="sm:col-span-2">
                        <p class="mb-1 text-sm font-medium">Related program codes</p>
                        <div class="grid max-h-40 gap-2 overflow-y-auto rounded-xl border border-[#e8eaee] p-3 sm:grid-cols-2">
                            @foreach ($programOptions as $program)
                                <label class="inline-flex items-start gap-2 text-sm">
                                    <input type="checkbox" value="{{ $program->code }}" wire:model="selectedPrograms" @disabled($readOnly) class="mt-0.5 rounded border-brand-200 text-brand-900">
                                    <span>{{ $program->code }} — {{ $program->name }}{{ $program->is_active ? '' : ' (inactive)' }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedPrograms') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </form>
            <x-slot:footer>
                <x-dialog-actions form="psoc-form" submit="Save occupation" :read-only="$readOnly" />
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
