@php
    $steps = [
        'questionnaire' => ['1', 'Questionnaire', 'Academic, socioeconomic, employability'],
        'skills' => ['2', 'Skills & experience', 'Skills, certificates, work'],
        'grades' => ['3', 'Grades', 'Optional update'],
        'review' => ['4', 'Review', 'Run prediction'],
    ];
    $sectionLabels = [
        'questionnaire' => 'Questionnaire',
        'socioeconomic' => 'Socioeconomic',
        'skills' => 'Skills & experience',
        'academic' => 'Confirmed grades',
    ];
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="assessment-progress-label">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p id="assessment-progress-label" class="text-sm font-semibold text-brand-900">Assessment workflow progress</p>
                <p class="mt-1 text-xs text-gray-500">This tracks saved sections. It is not a prediction-accuracy score.</p>
            </div>
            <p class="text-sm font-semibold text-brand-900">{{ $progress['percent'] }}%</p>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-brand-50" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-labelledby="assessment-progress-label">
            <div class="h-full rounded-full bg-brand-400 transition-all" style="width: {{ $progress['percent'] }}%"></div>
        </div>
        <ul class="mt-4 flex flex-wrap gap-2 text-xs">
            @foreach ($sectionLabels as $key => $label)
                <li class="inline-flex items-center gap-1 rounded-full border px-3 py-1 {{ $progress['sections'][$key] ? 'border-brand-200 bg-brand-50 text-brand-900' : 'border-gray-200 text-gray-600' }}">
                    <i class="{{ $progress['sections'][$key] ? 'ri-checkbox-circle-line' : 'ri-time-line' }}" aria-hidden="true"></i>
                    {{ $label }} · {{ $progress['sections'][$key] ? 'saved' : ($key === 'academic' ? 'optional' : 'open') }}
                </li>
            @endforeach
        </ul>
    </section>

    <nav class="grid gap-2 md:grid-cols-4" aria-label="Assessment steps">
        @foreach ($steps as $key => [$number, $label, $description])
            <button
                type="button"
                wire:click="goTo('{{ $key }}')"
                class="{{ $step === $key ? 'border-brand-900 bg-white text-brand-900 shadow-sm' : 'border-brand-200 bg-white/70 text-gray-700 hover:bg-white' }} flex items-start gap-3 rounded-xl border p-3 text-left"
                @if ($step === $key) aria-current="step" @endif
            >
                <span class="{{ $step === $key ? 'bg-brand-900 text-white' : 'bg-brand-50 text-brand-900' }} flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold">{{ $number }}</span>
                <span>
                    <span class="block text-sm font-semibold">{{ $label }}</span>
                    <span class="mt-0.5 block text-xs font-normal text-gray-500">{{ $description }}</span>
                </span>
            </button>
        @endforeach
    </nav>

    @if ($statusMessage !== '')
        <p class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif

    @if ($step === 'questionnaire')
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Step 1 of 4</p>
            <h2 class="mt-1 text-xl font-semibold text-brand-900">Questionnaire and context</h2>
            <p class="mt-2 text-sm text-gray-600">Work on one section at a time. Saved drafts remain available when you return.</p>

            <nav class="mt-5 grid gap-2 sm:grid-cols-3" aria-label="Questionnaire sections">
                @foreach ($questionnaireSections as $key => $section)
                    <button
                        type="button"
                        wire:click="selectQuestionnaireSection('{{ $key }}')"
                        class="{{ $questionnaireSection === $key ? 'border-brand-900 bg-brand-50 text-brand-900' : 'border-brand-200 text-gray-700 hover:bg-brand-50/50' }} rounded-xl border p-3 text-left"
                        @if ($questionnaireSection === $key) aria-current="page" @endif
                    >
                        <span class="block text-sm font-semibold">{{ $section['label'] }}</span>
                        <span class="mt-1 block text-xs font-normal leading-5 text-gray-500">{{ $section['description'] }}</span>
                    </button>
                @endforeach
            </nav>
        </section>

        @if ($questionnaireSection === 'academic_behavior')
            <livewire:student.questionnaire-form />
        @elseif ($questionnaireSection === 'socioeconomic')
            <form class="relative rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" wire:submit="saveSocioeconomic">
                <x-loading-overlay target="saveSocioeconomic" label="Saving private context…" />
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Private to you</p>
                <h3 class="mt-1 text-xl font-semibold text-brand-900">Socioeconomic factors</h3>
                <p class="mt-2 text-sm text-gray-600">These values are encrypted. Department Heads, Deans, and Administrators cannot open the raw answers.</p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="household_income_bracket" class="mb-1 block text-sm font-medium">Household income bracket</label>
                        <select id="household_income_bracket" wire:model="household_income_bracket" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($incomeOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('household_income_bracket') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="household_size" class="mb-1 block text-sm font-medium">Household size</label>
                        <input id="household_size" type="number" min="1" max="20" wire:model="household_size" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @error('household_size') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="scholarship_status" class="mb-1 block text-sm font-medium">Scholarship status</label>
                        <select id="scholarship_status" wire:model="scholarship_status" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($scholarshipOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('scholarship_status') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="employment_status" class="mb-1 block text-sm font-medium">Employment status</label>
                        <select id="employment_status" wire:model="employment_status" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($employmentOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('employment_status') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="living_arrangement" class="mb-1 block text-sm font-medium">Living arrangement</label>
                        <select id="living_arrangement" wire:model="living_arrangement" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($livingOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('living_arrangement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="has_internet" class="mb-1 block text-sm font-medium">Internet access</label>
                        <select id="has_internet" wire:model="has_internet" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($internetOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('has_internet') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="has_device" class="mb-1 block text-sm font-medium">Device access</label>
                        <select id="has_device" wire:model="has_device" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($deviceOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('has_device') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="has_study_space" class="mb-1 block text-sm font-medium">Study space</label>
                        <select id="has_study_space" wire:model="has_study_space" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach ($studySpaceOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('has_study_space') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="button" wire:click="saveSocioeconomic(true)" wire:loading.attr="disabled" wire:target="saveSocioeconomic" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60">Save draft</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveSocioeconomic" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60">Save &amp; continue</button>
                </div>
            </form>
        @else
            <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3">
                    <i class="ri-flask-line mt-0.5 text-2xl text-brand-900" aria-hidden="true"></i>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Research preparation</p>
                        <h3 class="mt-1 text-xl font-semibold text-brand-900">Employability self-assessment</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">No employability questions are collected yet. Final items must come from cited research or an approved instrument. The themes below are planning categories only—not questions, scores, or model weights.</p>
                    </div>
                </div>
                <ul class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (($questionnaireSections['employability']['planning_themes'] ?? []) as $theme)
                        <li class="rounded-xl border border-dashed border-brand-200 bg-brand-50/50 px-4 py-3 text-sm text-gray-700">{{ $theme }}</li>
                    @endforeach
                </ul>
                <button type="button" wire:click="goTo('skills')" class="mt-6 inline-flex items-center gap-1 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Continue to skills<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
            </section>
        @endif
    @elseif ($step === 'skills')
        <livewire:student.skills-experience-section />
    @elseif ($step === 'grades')
        @php
            $hasGrades = $currentGradeReports->isNotEmpty();
            $latestReport = $currentGradeReports->first();
            $editorOpen = ! $hasGrades || $gradeChoice === 'update';
        @endphp
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Step 3 of 4 · Optional update</p>
            <h2 class="mt-1 text-xl font-semibold text-brand-900">Grades</h2>
            <p class="mt-2 text-sm text-gray-600">Add a term by uploading a document, pasting from the UCC portal, or typing it in. You review every row before it is saved.</p>

            @if ($hasGrades)
                <div class="mt-5 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Grades for this assessment">
                    <button type="button" role="radio" aria-checked="{{ $gradeChoice === 'latest' ? 'true' : 'false' }}" wire:click="chooseGrades('latest')"
                        class="{{ $gradeChoice === 'latest' ? 'border-brand-900 bg-brand-50 ring-1 ring-brand-900' : 'border-brand-200 hover:bg-brand-50/50' }} rounded-xl border p-4 text-left">
                        <span class="flex items-center gap-2 font-semibold text-brand-900"><i class="{{ $gradeChoice === 'latest' ? 'ri-radio-button-line' : 'ri-checkbox-blank-circle-line' }}" aria-hidden="true"></i>Use latest confirmed grades</span>
                        <span class="mt-1 block text-sm text-gray-600">{{ $currentGradeReports->count() }} {{ \Illuminate\Support\Str::plural('term', $currentGradeReports->count()) }} on file. Most recently saved: AY {{ $latestReport->school_year }} · {{ $latestReport->semester }}.</span>
                    </button>
                    <button type="button" role="radio" aria-checked="{{ $gradeChoice === 'update' ? 'true' : 'false' }}" wire:click="chooseGrades('update')"
                        class="{{ $gradeChoice === 'update' ? 'border-brand-900 bg-brand-50 ring-1 ring-brand-900' : 'border-brand-200 hover:bg-brand-50/50' }} rounded-xl border p-4 text-left">
                        <span class="flex items-center gap-2 font-semibold text-brand-900"><i class="{{ $gradeChoice === 'update' ? 'ri-radio-button-line' : 'ri-checkbox-blank-circle-line' }}" aria-hidden="true"></i>Update grades</span>
                        <span class="mt-1 block text-sm text-gray-600">Add a new term or update a saved one, for example when an INC is completed.</span>
                    </button>
                </div>
                <p class="mt-3 text-sm text-gray-700">
                    GWA on file: <span class="font-semibold text-brand-900">{{ $academic['rounded_gwa'] !== null ? number_format($academic['rounded_gwa'], 2) : '—' }}</span>
                    @if ($academic['gwa_provisional'])
                        <span class="ml-1 inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900"><i class="ri-time-line" aria-hidden="true"></i>Provisional · {{ $academic['incomplete_subjects'] }} INC left out</span>
                    @endif
                </p>
            @else
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-medium text-amber-900">No confirmed grades on file</p>
                    <p class="mt-1 text-sm text-amber-900/80">EduPredict does not fabricate academic data. You can still continue: a prediction without grades has less academic evidence and is marked lower confidence.</p>
                </div>
            @endif
        </section>

        @if ($editorOpen)
            <livewire:student.grade-report-form :report-id="$gradeDraftId" :replace-id="$gradeReplaceId" :key="'grade-form-'.$gradeFormKey" />
        @endif

        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="grade-history-heading">
            <h3 id="grade-history-heading" class="text-base font-semibold text-brand-900">Saved grade reports</h3>
            <p class="mb-4 mt-1 text-sm text-gray-600">View any term read-only. Updating a term keeps the earlier version for your prediction history.</p>
            <livewire:tables.grade-reports-table />
        </section>

        <div class="flex justify-end">
            <button type="button" wire:click="goTo('review')" class="inline-flex items-center gap-1 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Continue to review<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
        </div>
    @else
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Step 4 of 4</p>
            <h2 class="mt-1 text-xl font-semibold text-brand-900">Review and run prediction</h2>
            <p class="mt-2 text-sm text-gray-600">Review which sections are saved. You can update one section without restarting the others.</p>

            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($sectionLabels as $key => $label)
                    <div class="rounded-xl border border-brand-200 p-4">
                        <dt class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                        <dd class="mt-1 font-medium {{ $progress['sections'][$key] ? 'text-brand-900' : 'text-amber-800' }}">{{ $progress['sections'][$key] ? 'Saved' : ($key === 'academic' ? 'No grades on file' : 'Needs attention') }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-6 rounded-xl bg-brand-50 p-4">
                <p class="text-sm text-gray-700">Running a prediction creates a new saved attempt. Existing prediction history is never overwritten. Output comes from <span class="font-medium">placeholder-heuristic-v0</span>, not a final trained model.</p>
                <div class="mt-4"><livewire:student.request-prediction /></div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <button type="button" wire:click="goTo('questionnaire')" class="rounded-lg border border-brand-200 px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Update questionnaire</button>
                <button type="button" wire:click="goTo('skills')" class="rounded-lg border border-brand-200 px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Update skills</button>
                <button type="button" wire:click="goTo('grades')" class="rounded-lg border border-brand-200 px-3 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Update grades</button>
            </div>
        </section>
    @endif
</div>
