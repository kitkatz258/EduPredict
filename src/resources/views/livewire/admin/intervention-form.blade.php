<div>
    @if ($show)
        <x-dialog :title="$readOnly ? 'View intervention' : ($interventionId ? 'Edit intervention' : 'Add intervention')" close="closeForm" description="This list is the only source of recommended actions. Saving here does not rewrite actions already stored on a prediction.">
            <form id="intervention-form" wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save,confirmSave" label="Saving intervention…" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="intervention-code" class="mb-1 block text-sm font-medium">Code</label>
                        <input id="intervention-code" type="text" wire:model="code" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="academic_tutoring">
                        @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="intervention-risk" class="mb-1 block text-sm font-medium">Eligible from risk level</label>
                        <select id="intervention-risk" wire:model="minRiskLevel" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="low">Low</option>
                            <option value="moderate">Moderate</option>
                            <option value="high">High</option>
                        </select>
                        <p id="intervention-risk-help" class="mt-1 text-xs text-gray-500">Moderate = applicable to Moderate and High; High = applicable only to High. The rule engine still selects actions based on relevant factors; it does not mean the action is automatically applied.</p>
                        @error('minRiskLevel') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="intervention-title" class="mb-1 block text-sm font-medium">Title</label>
                        <input id="intervention-title" type="text" wire:model="title" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="intervention-description" class="mb-1 block text-sm font-medium">Description</label>
                        <textarea id="intervention-description" wire:model="description" rows="3" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm"></textarea>
                        <p class="mt-1 text-xs text-gray-500">This text is the rule-based phrasing when AI is unavailable.</p>
                        @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="intervention-targets" class="mb-1 block text-sm font-medium">Target factors</label>
                        <input id="intervention-targets" type="text" wire:model="targets" @disabled($readOnly) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="gwa, failed_subjects">
                        @error('targets') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </form>
            <x-slot:footer>
                <x-dialog-actions form="intervention-form" :submit="$interventionId ? 'Save Changes' : 'Add'" :read-only="$readOnly" />
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
