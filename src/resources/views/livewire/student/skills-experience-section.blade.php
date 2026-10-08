@php
    $inputClass = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:bg-gray-50 disabled:text-gray-700';
    $dateRange = function ($start, $end, bool $ongoing): string {
        $from = $start?->format('M Y') ?? 'Start not recorded';
        $to = $ongoing ? 'Present' : ($end?->format('M Y') ?? 'End not recorded');

        return $from.' – '.$to;
    };
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Step 2 of 4</p>
                <h2 class="mt-1 text-xl font-semibold text-brand-900">Skills and experience</h2>
                <p class="mt-2 max-w-2xl text-sm text-gray-600">Add, edit, or archive entries one at a time. Each entry saves on its own, and archived entries stay in your history. OJT and internships are recorded as a work-experience type.</p>
            </div>
            <span class="{{ $sectionSaved ? 'border-brand-200 bg-brand-50 text-brand-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium">
                <i class="{{ $sectionSaved ? 'ri-checkbox-circle-line' : 'ri-time-line' }}" aria-hidden="true"></i>
                {{ $sectionSaved ? 'Section saved' : 'Not yet marked saved' }}
            </span>
        </div>
        @if ($legacyProjectCount > 0)
            <p class="mt-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600"><i class="ri-archive-line mr-1" aria-hidden="true"></i>{{ $legacyProjectCount }} earlier project {{ \Illuminate\Support\Str::plural('entry', $legacyProjectCount) }} remain stored in your records and data export. Projects are no longer part of the Assessment.</p>
        @endif
    </section>

    {{-- Technical skills --}}
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="skills-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="skills-heading" class="text-base font-semibold text-brand-900"><i class="ri-tools-line mr-1" aria-hidden="true"></i>Technical skills</h3>
                <p class="text-sm text-gray-600">{{ $skills->count() }} {{ \Illuminate\Support\Str::plural('skill', $skills->count()) }} listed</p>
            </div>
            <button type="button" wire:click="openCreate('skill')" wire:loading.attr="disabled" wire:target="openCreate" class="inline-flex items-center gap-1 rounded-lg border border-brand-900 px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60"><i class="ri-add-line" aria-hidden="true"></i>Add skill</button>
        </div>
        @if ($skills->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-brand-200 px-4 py-6 text-center text-sm text-gray-500">No skills yet. Add tools, languages, or methods you can use.</p>
        @else
            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach ($skills as $item)
                    <li wire:key="skill-{{ $item->id }}" class="inline-flex items-center gap-1 rounded-full border border-brand-200 bg-brand-50 py-1 pl-3 pr-1 text-sm text-brand-900">
                        <span>{{ $item->name }}</span>
                        <button type="button" wire:click="openEdit('skill', {{ $item->id }})" class="rounded-full p-1 hover:bg-white" aria-label="Edit skill {{ $item->name }}"><i class="ri-pencil-line" aria-hidden="true"></i></button>
                        <button type="button" wire:click="archive('skill', {{ $item->id }})" data-confirm="It will no longer count in new predictions. You can restore it later." data-confirm-title="Archive {{ $item->name }}?" data-confirm-button="Archive" class="rounded-full p-1 text-gray-600 hover:bg-white hover:text-red-800" aria-label="Archive skill {{ $item->name }}"><i class="ri-archive-line" aria-hidden="true"></i></button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Certifications --}}
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="certifications-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="certifications-heading" class="text-base font-semibold text-brand-900"><i class="ri-award-line mr-1" aria-hidden="true"></i>Certifications</h3>
                <p class="text-sm text-gray-600">{{ $certifications->count() }} {{ \Illuminate\Support\Str::plural('certification', $certifications->count()) }} listed</p>
            </div>
            <button type="button" wire:click="openCreate('certification')" wire:loading.attr="disabled" wire:target="openCreate" class="inline-flex items-center gap-1 rounded-lg border border-brand-900 px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60"><i class="ri-add-line" aria-hidden="true"></i>Add certification</button>
        </div>
        @if ($certifications->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-brand-200 px-4 py-6 text-center text-sm text-gray-500">No certifications yet. Many students have none; that is fine.</p>
        @else
            <ul class="mt-4 divide-y divide-brand-200 rounded-xl border border-brand-200">
                @foreach ($certifications as $item)
                    <li wire:key="certification-{{ $item->id }}" class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-gray-900">{{ $item->title }}</p>
                            <p class="text-sm text-gray-600">{{ $item->issuer ?? 'Issuer not recorded' }} · {{ $item->issued_year ?? 'Year not recorded' }}@if ($item->expires_on) · Expires {{ $item->expires_on->format('M j, Y') }}@endif</p>
                        </div>
                        <div class="flex shrink-0 gap-2 text-sm">
                            <button type="button" wire:click="openView('certification', {{ $item->id }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-brand-900 hover:bg-brand-50" aria-label="View {{ $item->title }}"><i class="ri-eye-line" aria-hidden="true"></i>View</button>
                            <button type="button" wire:click="openEdit('certification', {{ $item->id }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-brand-900 hover:bg-brand-50" aria-label="Edit {{ $item->title }}"><i class="ri-pencil-line" aria-hidden="true"></i>Edit</button>
                            <button type="button" wire:click="archive('certification', {{ $item->id }})" data-confirm="It will no longer count in new predictions. You can restore it later." data-confirm-title="Archive this certification?" data-confirm-button="Archive" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-red-800 hover:bg-red-50" aria-label="Archive {{ $item->title }}"><i class="ri-archive-line" aria-hidden="true"></i>Archive</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Work experience --}}
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="experience-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="experience-heading" class="text-base font-semibold text-brand-900"><i class="ri-briefcase-4-line mr-1" aria-hidden="true"></i>Work experience</h3>
                <p class="text-sm text-gray-600">Jobs, OJT/internships, assistantships, and volunteer work.</p>
            </div>
            <button type="button" wire:click="openCreate('experience')" wire:loading.attr="disabled" wire:target="openCreate" class="inline-flex items-center gap-1 rounded-lg border border-brand-900 px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60"><i class="ri-add-line" aria-hidden="true"></i>Add experience</button>
        </div>
        @if ($experiences->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-brand-200 px-4 py-6 text-center text-sm text-gray-500">No work experience yet. Add an OJT or internship here when you have one.</p>
        @else
            <ul class="mt-4 divide-y divide-brand-200 rounded-xl border border-brand-200">
                @foreach ($experiences as $item)
                    <li wire:key="experience-{{ $item->id }}" class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-gray-900">{{ $item->role_title ?? 'Role not recorded' }} <span class="font-normal text-gray-600">· {{ $item->organization ?? 'Organization not recorded' }}</span></p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                                <span class="rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-900">{{ $item->typeLabel() }}</span>
                                <span>{{ $dateRange($item->start_date, $item->end_date, $item->is_ongoing) }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2 text-sm">
                            <button type="button" wire:click="openView('experience', {{ $item->id }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-brand-900 hover:bg-brand-50" aria-label="View {{ $item->role_title }} at {{ $item->organization }}"><i class="ri-eye-line" aria-hidden="true"></i>View</button>
                            <button type="button" wire:click="openEdit('experience', {{ $item->id }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-brand-900 hover:bg-brand-50" aria-label="Edit {{ $item->role_title }} at {{ $item->organization }}"><i class="ri-pencil-line" aria-hidden="true"></i>Edit</button>
                            <button type="button" wire:click="archive('experience', {{ $item->id }})" data-confirm="It will no longer count in new predictions. You can restore it later." data-confirm-title="Archive this experience?" data-confirm-button="Archive" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-red-800 hover:bg-red-50" aria-label="Archive {{ $item->role_title }} at {{ $item->organization }}"><i class="ri-archive-line" aria-hidden="true"></i>Archive</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($archivedCount > 0)
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <button type="button" wire:click="toggleArchived" class="inline-flex items-center gap-1 text-sm font-medium text-brand-900" aria-expanded="{{ $showArchived ? 'true' : 'false' }}">
                <i class="{{ $showArchived ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line' }}" aria-hidden="true"></i>
                Archived entries ({{ $archivedCount }})
            </button>
            @if ($showArchived)
                <ul class="mt-4 divide-y divide-gray-200 text-sm">
                    @foreach ($archivedSkills as $item)
                        <li wire:key="archived-skill-{{ $item->id }}" class="flex items-center justify-between gap-3 py-2"><span><span class="text-gray-500">Skill ·</span> {{ $item->name }}</span><button type="button" wire:click="restore('skill', {{ $item->id }})" class="text-brand-900 underline">Restore</button></li>
                    @endforeach
                    @foreach ($archivedCertifications as $item)
                        <li wire:key="archived-certification-{{ $item->id }}" class="flex items-center justify-between gap-3 py-2"><span><span class="text-gray-500">Certification ·</span> {{ $item->title }}</span><button type="button" wire:click="restore('certification', {{ $item->id }})" class="text-brand-900 underline">Restore</button></li>
                    @endforeach
                    @foreach ($archivedExperiences as $item)
                        <li wire:key="archived-experience-{{ $item->id }}" class="flex items-center justify-between gap-3 py-2"><span><span class="text-gray-500">{{ $item->typeLabel() }} ·</span> {{ $item->role_title ?? 'Role not recorded' }}, {{ $item->organization ?? 'organization not recorded' }}</span><button type="button" wire:click="restore('experience', {{ $item->id }})" class="text-brand-900 underline">Restore</button></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <section class="relative flex flex-col gap-3 rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <x-loading-overlay target="completeSection" label="Saving section…" />
        <p class="text-sm text-gray-600">When the list looks right, mark the section saved. An empty list is allowed.</p>
        <button type="button" wire:click="completeSection" wire:loading.attr="disabled" wire:target="completeSection" class="inline-flex items-center justify-center gap-1 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">Save section &amp; continue<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
    </section>

    @if ($modal === 'skill')
        <x-dialog :title="$editingId ? 'Edit skill' : 'Add skill'" close="closeModal" max-width="md">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving skill…" />
                <div>
                    <label for="skill-name" class="mb-1 block text-sm font-medium">Skill name <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="skill-name" type="text" wire:model="skill.name" maxlength="120" class="{{ $inputClass }}" placeholder="e.g. SQL, Adobe Premiere, Data analysis" autofocus>
                    @error('skill.name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 border-t border-brand-200 pt-4">
                    <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60"><x-spinner wire:loading wire:target="save" />Save skill</button>
                </div>
            </form>
        </x-dialog>
    @elseif ($modal === 'certification')
        <x-dialog :title="$viewOnly ? 'Certification details' : ($editingId ? 'Edit certification' : 'Add certification')" close="closeModal">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving certification…" />
                <fieldset @disabled($viewOnly) class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="cert-title" class="mb-1 block text-sm font-medium">Certificate / title @unless ($viewOnly)<span class="text-red-700" aria-hidden="true">*</span>@endunless</label>
                        <input id="cert-title" type="text" wire:model="certification.title" maxlength="160" class="{{ $inputClass }}" autofocus>
                        @error('certification.title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cert-issuer" class="mb-1 block text-sm font-medium">Issuer / provider @unless ($viewOnly)<span class="text-red-700" aria-hidden="true">*</span>@endunless</label>
                        <input id="cert-issuer" type="text" wire:model="certification.issuer" maxlength="160" class="{{ $inputClass }}">
                        @error('certification.issuer') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cert-year" class="mb-1 block text-sm font-medium">Issue year</label>
                        <input id="cert-year" type="number" inputmode="numeric" min="1980" max="{{ now()->format('Y') }}" wire:model="certification.issued_year" class="{{ $inputClass }}" aria-describedby="cert-year-help">
                        <p id="cert-year-help" class="mt-1 text-xs text-gray-500">Required unless you enter the exact issue date.</p>
                        @error('certification.issued_year') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cert-issued-on" class="mb-1 block text-sm font-medium">Issue date (optional)</label>
                        <input id="cert-issued-on" type="date" wire:model="certification.issued_on" class="{{ $inputClass }}">
                        @error('certification.issued_on') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cert-expires" class="mb-1 block text-sm font-medium">Expiry date (optional)</label>
                        <input id="cert-expires" type="date" wire:model="certification.expires_on" class="{{ $inputClass }}">
                        @error('certification.expires_on') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cert-reference" class="mb-1 block text-sm font-medium">Credential ID / reference (optional)</label>
                        <input id="cert-reference" type="text" wire:model="certification.credential_reference" maxlength="120" class="{{ $inputClass }}">
                        @error('certification.credential_reference') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cert-description" class="mb-1 block text-sm font-medium">Description (optional)</label>
                        <textarea id="cert-description" rows="3" wire:model="certification.description" maxlength="1000" class="{{ $inputClass }}"></textarea>
                        @error('certification.description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </fieldset>
                <div class="flex justify-end gap-2 border-t border-brand-200 pt-4">
                    @if ($viewOnly)
                        <button type="button" wire:click="openEdit('certification', {{ $editingId }})" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Edit</button>
                        <button type="button" wire:click="closeModal" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Close</button>
                    @else
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60"><x-spinner wire:loading wire:target="save" />Save certification</button>
                    @endif
                </div>
            </form>
        </x-dialog>
    @elseif ($modal === 'experience')
        <x-dialog :title="$viewOnly ? 'Work experience details' : ($editingId ? 'Edit work experience' : 'Add work experience')" close="closeModal">
            <form wire:submit="save" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="save" label="Saving experience…" />
                <fieldset @disabled($viewOnly) class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="exp-type" class="mb-1 block text-sm font-medium">Experience type @unless ($viewOnly)<span class="text-red-700" aria-hidden="true">*</span>@endunless</label>
                        <select id="exp-type" wire:model="experience.experience_type" class="{{ $inputClass }}" autofocus>
                            @foreach ($experienceTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('experience.experience_type') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exp-org" class="mb-1 block text-sm font-medium">Organization / employer @unless ($viewOnly)<span class="text-red-700" aria-hidden="true">*</span>@endunless</label>
                        <input id="exp-org" type="text" wire:model="experience.organization" maxlength="160" class="{{ $inputClass }}">
                        @error('experience.organization') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exp-role" class="mb-1 block text-sm font-medium">Role / title @unless ($viewOnly)<span class="text-red-700" aria-hidden="true">*</span>@endunless</label>
                        <input id="exp-role" type="text" wire:model="experience.role_title" maxlength="160" class="{{ $inputClass }}">
                        @error('experience.role_title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exp-start" class="mb-1 block text-sm font-medium">Start date @unless ($viewOnly)<span class="text-red-700" aria-hidden="true">*</span>@endunless</label>
                        <input id="exp-start" type="date" wire:model="experience.start_date" class="{{ $inputClass }}">
                        @error('experience.start_date') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exp-end" class="mb-1 block text-sm font-medium">End date</label>
                        <input id="exp-end" type="date" wire:model="experience.end_date" class="{{ $inputClass }}" @disabled($experience['is_ongoing'] ?? false)>
                        @error('experience.end_date') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model.live="experience.is_ongoing" class="rounded border-brand-200 text-brand-900 focus:ring-brand-200">
                            I still do this (ongoing)
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="exp-description" class="mb-1 block text-sm font-medium">Responsibilities / description (optional)</label>
                        <textarea id="exp-description" rows="4" wire:model="experience.description" maxlength="2000" class="{{ $inputClass }}"></textarea>
                        @error('experience.description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </fieldset>
                <div class="flex justify-end gap-2 border-t border-brand-200 pt-4">
                    @if ($viewOnly)
                        <button type="button" wire:click="openEdit('experience', {{ $editingId }})" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Edit</button>
                        <button type="button" wire:click="closeModal" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Close</button>
                    @else
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60"><x-spinner wire:loading wire:target="save" />Save experience</button>
                    @endif
                </div>
            </form>
        </x-dialog>
    @endif
</div>
