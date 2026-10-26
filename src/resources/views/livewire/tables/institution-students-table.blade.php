<div>
    <x-data-table wire:loading.class="opacity-60">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                    <div class="w-full sm:max-w-sm">
                        <label for="table-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                        <input id="table-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search eligible students…" class="ui-field">
                    </div>
                    <div>
                        <label for="eligible-program-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Program</label>
                        <select id="eligible-program-{{ $this->getId() }}" wire:model.live="programFilter" class="ui-field w-auto">
                            <option value="">All programs</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="eligible-year-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Year</label>
                        <select id="eligible-year-{{ $this->getId() }}" wire:model.live="yearFilter" class="ui-field w-auto">
                            <option value="">All years</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                            <option value="5">5th Year (needs correction)</option>
                            <option value="6">6th Year (needs correction)</option>
                        </select>
                    </div>
                    <div>
                        <label for="eligible-registered-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Claimed</label>
                        <select id="eligible-registered-{{ $this->getId() }}" wire:model.live="registeredFilter" class="ui-field w-auto">
                            <option value="">All</option>
                            <option value="yes">Login issued</option>
                            <option value="no">Unclaimed</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
                        <select id="per-page-{{ $this->getId() }}" wire:model.live="perPage" class="ui-field w-auto">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <x-updating />
        </x-slot:toolbar>
        @if ($outOfRangeYears->isNotEmpty())
            <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
                <p class="font-medium">These eligible-student year levels are outside 1–4. They were left unchanged for correction.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($outOfRangeYears as $record)
                        <li>{{ $record->student_number }} is stored as year {{ $record->year_level }}.</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>
                        <button type="button" wire:click="sortBy('{{ $column['key'] }}')">{{ $column['label'] }}</button>
                    </th>
                @endforeach
                <th>Program</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->student_number }}</td>
                    <td>{{ $row->last_name }}</td>
                    <td>{{ $row->first_name }}</td>
                    <td>{{ $row->year_level }}</td>
                    <td>{{ $row->is_registered ? 'Login issued' : 'Unclaimed' }}</td>
                    <td>{{ $row->program?->code }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-table-action icon="ri-pencil-line" wire:click="editEligible({{ $row->id }})">Edit</x-table-action>
                            @unless ($row->is_registered)
                                <x-table-action icon="ri-key-2-line" wire:click="openProvision({{ $row->id }})">Issue login</x-table-action>
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-data-table>

    @if ($provisioning)
        <x-dialog title="Issue student login" close="closeProvision" description="Creates a hashed temporary password. Surname-only passwords are not used. The student must change the password at first sign-in.">
            <form id="provision-form" wire:submit="issueLogin" class="space-y-4 px-5 py-5 sm:px-6">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Student number</dt>
                        <dd class="font-medium text-brand-900">{{ $provisioning->student_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Name</dt>
                        <dd class="font-medium text-brand-900">{{ $provisioning->last_name }}, {{ $provisioning->first_name }}</dd>
                    </div>
                </dl>
                <div>
                    <label for="provision-email" class="mb-1 block text-sm font-medium">Email for password resets</label>
                    <input id="provision-email" type="email" wire:model="provisionEmail" class="ui-field" @disabled($temporaryPassword !== null)>
                    @error('provisionEmail') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                @if ($temporaryPassword)
                    <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">Temporary password: <code>{{ $temporaryPassword }}</code>. Copy it now. It is not stored in logs.</p>
                @endif
            </form>
            <x-slot:footer>
                @if ($temporaryPassword)
                    <div class="flex justify-end">
                        <button type="button" x-on:click="requestClose()" class="ui-btn ui-btn-primary">Done</button>
                    </div>
                @else
                    <x-dialog-actions form="provision-form" submit="Issue login" target="issueLogin" />
                @endif
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
