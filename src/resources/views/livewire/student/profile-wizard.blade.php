<div class="space-y-6">
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="profile-completeness-label">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p id="profile-completeness-label" class="text-sm text-gray-700">Profile completeness — more complete data improves prediction accuracy</p>
            <p class="text-sm font-semibold text-brand-900">{{ $completeness['percent'] }}%</p>
        </div>
        <div
            class="mt-3 h-2 overflow-hidden rounded-full bg-brand-50"
            role="progressbar"
            aria-valuenow="{{ $completeness['percent'] }}"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-labelledby="profile-completeness-label"
        >
            <div class="h-full rounded-full bg-brand-400" style="width: {{ $completeness['percent'] }}%"></div>
        </div>
        <ul class="mt-4 flex flex-wrap gap-2 text-xs">
            @foreach (['academic' => 'Academic', 'socioeconomic' => 'Socioeconomic', 'skills' => 'Skills & experience', 'questionnaire' => 'Questionnaire'] as $key => $label)
                <li class="rounded-full border px-3 py-1 {{ $completeness['sections'][$key] ? 'border-brand-200 bg-brand-50 text-brand-900' : 'border-gray-200 text-gray-600' }}">
                    {{ $label }}{{ $completeness['sections'][$key] ? ' · complete' : ' · still open' }}
                </li>
            @endforeach
        </ul>
    </section>

    <nav class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4" aria-label="Profile steps">
        @foreach (['academic' => '1. Academic', 'socioeconomic' => '2. Socioeconomic', 'skills' => '3. Skills & experience', 'questionnaire' => '4. Questionnaire'] as $key => $label)
            <button
                type="button"
                wire:click="goTo('{{ $key }}')"
                class="rounded-xl border px-4 py-3 text-left text-sm font-medium {{ $step === $key ? 'border-brand-900 bg-white text-brand-900 shadow-sm' : 'border-brand-200 bg-white/70 text-gray-700 hover:bg-white' }}"
                @if ($step === $key) aria-current="step" @endif
            >
                {{ $label }}
            </button>
        @endforeach
    </nav>

    @if ($statusMessage !== '')
        <p class="rounded-xl border border-brand-200 bg-white px-4 py-3 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif

    @if ($step === 'academic')
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="academic-heading">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic record</p>
            <h2 id="academic-heading" class="mt-1 text-xl font-semibold text-brand-900">Grades already on file</h2>
            <p class="mt-2 text-sm text-gray-600">Add or correct terms on My Grades. This step only summarizes confirmed reports.</p>
            <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-brand-50 px-4 py-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">GWA</dt>
                    <dd class="mt-1 text-lg font-semibold text-brand-900">{{ $academic['rounded_gwa'] !== null ? number_format($academic['rounded_gwa'], 2) : '—' }}</dd>
                </div>
                <div class="rounded-xl bg-brand-50 px-4 py-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">Failed subjects</dt>
                    <dd class="mt-1 text-lg font-semibold text-brand-900">{{ $academic['failed_subjects'] }}</dd>
                </div>
                <div class="rounded-xl bg-brand-50 px-4 py-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">Semesters</dt>
                    <dd class="mt-1 text-lg font-semibold text-brand-900">{{ $academic['semesters_completed'] }}</dd>
                </div>
            </dl>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('student.grades') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Update grades</a>
                <button type="button" wire:click="goTo('socioeconomic')" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Continue to socioeconomic</button>
            </div>
        </section>
    @endif

    @if ($step === 'socioeconomic')
        <form class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" wire:submit="saveSocioeconomic">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Private to you</p>
            <h2 class="mt-1 text-xl font-semibold text-brand-900">Socioeconomic profile</h2>
            <p class="mt-2 text-sm text-gray-600">These answers are stored encrypted. Department heads, deans, and administrators cannot open this section.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="household_income_bracket" class="mb-1 block text-sm font-medium text-gray-800">Household income bracket</label>
                    <select id="household_income_bracket" wire:model="household_income_bracket" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($incomeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('household_income_bracket') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="household_size" class="mb-1 block text-sm font-medium text-gray-800">Household size</label>
                    <input id="household_size" type="number" min="1" max="20" wire:model="household_size" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    @error('household_size') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="scholarship_status" class="mb-1 block text-sm font-medium text-gray-800">Scholarship status</label>
                    <select id="scholarship_status" wire:model="scholarship_status" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($scholarshipOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('scholarship_status') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="employment_status" class="mb-1 block text-sm font-medium text-gray-800">Employment status</label>
                    <select id="employment_status" wire:model="employment_status" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($employmentOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('employment_status') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="living_arrangement" class="mb-1 block text-sm font-medium text-gray-800">Living arrangement</label>
                    <select id="living_arrangement" wire:model="living_arrangement" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($livingOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('living_arrangement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="has_internet" class="mb-1 block text-sm font-medium text-gray-800">Internet access</label>
                    <select id="has_internet" wire:model="has_internet" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($internetOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('has_internet') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="has_device" class="mb-1 block text-sm font-medium text-gray-800">Device access</label>
                    <select id="has_device" wire:model="has_device" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($deviceOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('has_device') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="has_study_space" class="mb-1 block text-sm font-medium text-gray-800">Study space</label>
                    <select id="has_study_space" wire:model="has_study_space" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($studySpaceOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('has_study_space') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" wire:click="saveSocioeconomic(true)" wire:loading.attr="disabled" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Save draft</button>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Save and continue</button>
                <span class="self-center text-sm text-gray-500" wire:loading>Saving…</span>
            </div>
        </form>
    @endif

    @if ($step === 'skills')
        <form class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" wire:submit="saveSkills">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Experience</p>
            <h2 class="mt-1 text-xl font-semibold text-brand-900">Skills and experience</h2>
            <p class="mt-2 text-sm text-gray-600">One entry per line. For the paired lists, separate the name and the detail with a vertical bar, for example <span class="font-medium">AWS Cloud Practitioner | 2025</span>. Leaving a list empty is fine.</p>

            <div class="mt-5 grid gap-4">
                <div>
                    <label for="technicalSkills" class="mb-1 block text-sm font-medium text-gray-800">Technical skills</label>
                    <textarea id="technicalSkills" wire:model="technicalSkills" rows="4" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="SQL&#10;PHP"></textarea>
                    @error('technicalSkills') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="certifications" class="mb-1 block text-sm font-medium text-gray-800">Certifications</label>
                    <textarea id="certifications" wire:model="certifications" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="Name | year"></textarea>
                    @error('certifications') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="internships" class="mb-1 block text-sm font-medium text-gray-800">Internships or OJT</label>
                    <textarea id="internships" wire:model="internships" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="Organization | role"></textarea>
                    @error('internships') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="projects" class="mb-1 block text-sm font-medium text-gray-800">Projects</label>
                    <textarea id="projects" wire:model="projects" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="Title | short description"></textarea>
                    @error('projects') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="workExperience" class="mb-1 block text-sm font-medium text-gray-800">Work experience</label>
                    <textarea id="workExperience" wire:model="workExperience" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="Employer | role"></textarea>
                    @error('workExperience') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" wire:click="saveSkills(true)" wire:loading.attr="disabled" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Save draft</button>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Save section</button>
                <span class="self-center text-sm text-gray-500" wire:loading>Saving…</span>
            </div>
        </form>
    @endif

    @if ($step === 'questionnaire')
        <livewire:student.questionnaire-form />
    @endif
</div>
