<div>
    <button type="button" wire:click="startCreate" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">
        <i class="ri-user-add-line" aria-hidden="true"></i>Add eligible student
    </button>

    @if ($show)
        <x-dialog :title="$recordId ? 'Edit eligible student' : 'Add eligible student'" close="closeForm" description="This adds an eligibility row only. It does not create a login or change prediction history.">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving eligible student…" />
                @if ($registered)
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">This number is already registered. Name, student number, and program stay as they were matched. Year level and optional contact fields can still be corrected.</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="eligible-number" class="mb-1 block text-sm font-medium">Student number</label>
                        <input id="eligible-number" type="text" wire:model="studentNumber" @disabled($registered) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm disabled:bg-gray-50">
                        @error('studentNumber') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="eligible-year" class="mb-1 block text-sm font-medium">Year level</label>
                        <input id="eligible-year" type="number" min="1" max="4" step="1" wire:model="yearLevel" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-gray-500">Enter a whole number from 1 to 4.</p>
                        @error('yearLevel') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="eligible-last" class="mb-1 block text-sm font-medium">Last name</label>
                        <input id="eligible-last" type="text" wire:model="lastName" @disabled($registered) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm disabled:bg-gray-50">
                        @error('lastName') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="eligible-first" class="mb-1 block text-sm font-medium">First name</label>
                        <input id="eligible-first" type="text" wire:model="firstName" @disabled($registered) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm disabled:bg-gray-50">
                        @error('firstName') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="eligible-program" class="mb-1 block text-sm font-medium">CLAS program</label>
                        <select id="eligible-program" wire:model="programId" @disabled($registered) class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm disabled:bg-gray-50">
                            <option value="">Select program</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->code }} — {{ $program->name }}</option>
                            @endforeach
                        </select>
                        @error('programId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="eligible-email" class="mb-1 block text-sm font-medium">Email (optional)</label>
                        <input id="eligible-email" type="email" wire:model="email" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="eligible-birthdate" class="mb-1 block text-sm font-medium">Birthdate (optional)</label>
                        <input id="eligible-birthdate" type="date" wire:model="birthdate" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('birthdate') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" x-on:click="requestClose()" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">
                        <x-spinner wire:loading wire:target="save" />
                        Save eligible student
                    </button>
                </div>
            </form>
        </x-dialog>
    @endif
</div>
