<div>
    @if ($show)
        <x-dialog title="Grant extra prediction slots" close="closeForm" description="Adds extra successful attempts for one Philippine calendar date. The usual two-per-day count is not rewritten. Questionnaire answers stay private.">
            <form id="prediction-grant-form" wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving grant…" />
                <div>
                    <label for="grant-student" class="mb-1 block text-sm font-medium">Student number</label>
                    <input id="grant-student" type="text" wire:model.live.debounce.400ms="studentNumber" autocomplete="off" class="ui-field" placeholder="e.g. 2024-00001">
                    @if ($matchedLabel !== '')
                        <p class="mt-1 text-sm text-brand-900">{{ $matchedLabel }}</p>
                    @endif
                    @error('studentNumber') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="grant-date" class="mb-1 block text-sm font-medium">Asia/Manila date</label>
                        <input id="grant-date" type="date" wire:model="manilaDate" class="ui-field">
                        @error('manilaDate') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="grant-extra" class="mb-1 block text-sm font-medium">Extra successful slots</label>
                        <input id="grant-extra" type="number" min="1" max="9" wire:model="extraAttempts" class="ui-field">
                        @error('extraAttempts') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="grant-reason" class="mb-1 block text-sm font-medium">Justification</label>
                    <textarea id="grant-reason" wire:model="reason" rows="3" class="w-full rounded-[11px] border border-[#e8eaee] bg-[#f6f7f9] px-3 py-2 text-sm" placeholder="Why this exception is needed."></textarea>
                    @error('reason') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </form>
            <x-slot:footer>
                <x-dialog-actions form="prediction-grant-form" submit="Grant slots" target="save" />
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
