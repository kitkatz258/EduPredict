@php
    $sectionFor = ['academic_behavior' => 'questionnaire', 'socioeconomic' => 'socioeconomic', 'skills' => 'skills', 'grades' => 'academic'];
    $savedCount = count(array_filter($progress['sections']));
    $sectionCount = count($progress['sections']);
    $stepKeys = array_keys($steps);
    $statusFor = function (string $key) use ($sectionFor, $progress, $drafts): array {
        if ($key === 'employability') {
            return ['placeholder', 'ri-flask-line', 'Awaiting approved items'];
        }
        if ($key === 'review') {
            return ['open', 'ri-play-circle-line', 'Run when ready'];
        }
        if ($progress['sections'][$sectionFor[$key]] ?? false) {
            return ['saved', 'ri-checkbox-circle-fill', $key === 'grades' ? 'Grades on file' : 'Saved'];
        }
        if ($drafts[$key] ?? false) {
            return ['draft', 'ri-draft-line', 'Draft saved'];
        }

        return ['open', 'ri-time-line', $key === 'grades' ? 'Optional' : 'Not saved yet'];
    };
    $primary = 'inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 disabled:opacity-60';
    $secondary = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60';
    $outline = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand-900 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60';
    $select = 'w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200';
    $tz = config('app.timezone');
@endphp

<div class="space-y-6">
    <section id="assessment-progress" class="scroll-mt-24" aria-labelledby="assessment-step-label">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <p id="assessment-step-label" class="font-medium text-gray-700">
                Step {{ $stepIndex + 1 }} of {{ count($steps) }}
                <span class="text-gray-400" aria-hidden="true">·</span>
                <span class="text-brand-900">{{ $steps[$step] }}</span>
            </p>
            <p class="font-mono text-xs text-gray-600">{{ $savedCount }}/{{ $sectionCount }} sections saved</p>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-brand-200/40" role="progressbar" aria-label="Saved Assessment sections" aria-valuenow="{{ $savedCount }}" aria-valuemin="0" aria-valuemax="{{ $sectionCount }}" aria-valuetext="{{ $savedCount }} of {{ $sectionCount }} sections saved">
            <div class="h-full rounded-full bg-brand-400 transition-[width] duration-500 ease-out motion-reduce:transition-none" style="width: {{ $sectionCount > 0 ? round(($savedCount / $sectionCount) * 100) : 0 }}%"></div>
        </div>
        <p class="sr-only">This counts saved sections. It is not a prediction-accuracy score.</p>

        <nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Assessment sections">
            @foreach ($steps as $key => $label)
                @php [$state, $icon, $stateLabel] = $statusFor($key); @endphp
                <button
                    type="button"
                    wire:click="goTo('{{ $key }}')"
                    wire:loading.attr="disabled"
                    wire:target="goTo,next,back"
                    @class([
                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-1',
                        'bg-brand-900 text-white shadow-sm' => $step === $key,
                        'border border-brand-200 bg-brand-50 text-brand-900 hover:bg-brand-200/50' => $step !== $key && $state === 'saved',
                        'border border-dashed border-brand-200 bg-white text-gray-600 hover:bg-brand-50' => $step !== $key && $state === 'placeholder',
                        'border border-gray-200 bg-white text-gray-600 hover:border-brand-200 hover:text-brand-900' => $step !== $key && in_array($state, ['open', 'draft'], true),
                    ])
                    @if ($step === $key) aria-current="step" @endif
                    title="{{ $stateLabel }}"
                >
                    <i class="{{ $icon }} {{ $step !== $key && $state === 'saved' ? 'text-brand-900' : '' }}" aria-hidden="true"></i>
                    {{ $label }}
                    <span class="sr-only">({{ $stateLabel }})</span>
                </button>
            @endforeach
        </nav>
    </section>

    <div
        wire:key="assessment-step-{{ $step }}"
        class="assessment-step space-y-6"
        x-data
        x-init="$nextTick(() => { const top = document.getElementById('assessment-progress'); if (top && top.getBoundingClientRect().top < 0) { top.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); } })"
    >
        @if ($step === 'academic_behavior')
            <livewire:student.questionnaire-form />
        @elseif ($step === 'socioeconomic')
            <form class="relative rounded-2xl border border-brand-200 bg-white shadow-sm" wire:submit="saveSocioeconomic">
                <x-loading-overlay target="saveSocioeconomic" label="Saving private context…" />
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-brand-200 bg-brand-50/40 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-900">Socioeconomic factors</h2>
                        <p class="mt-1 text-sm text-gray-600">These values are encrypted. Department Heads, Deans, and Administrators cannot open the raw answers.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full border border-brand-200 bg-white px-3 py-1 text-xs font-medium text-brand-900"><i class="ri-lock-line" aria-hidden="true"></i>Private to you</span>
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 sm:px-6">
                    <div>
                        <label for="household_income_bracket" class="mb-1 block text-sm font-medium">Household income bracket</label>
                        <select id="household_income_bracket" wire:model="household_income_bracket" class="{{ $select }}">
                            <option value="">Select</option>
                            @foreach ($incomeOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('household_income_bracket') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="household_size" class="mb-1 block text-sm font-medium">Household size</label>
                        <input id="household_size" type="number" min="1" max="20" wire:model="household_size" class="{{ $select }}">
                        @error('household_size') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="scholarship_status" class="mb-1 block text-sm font-medium">Scholarship status</label>
                        <select id="scholarship_status" wire:model="scholarship_status" class="{{ $select }}">
                            <option value="">Select</option>
                            @foreach ($scholarshipOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('scholarship_status') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="employment_status" class="mb-1 block text-sm font-medium">Employment status</label>
                        <select id="employment_status" wire:model="employment_status" class="{{ $select }}">
                            <option value="">Select</option>
                            @foreach ($employmentOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('employment_status') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="living_arrangement" class="mb-1 block text-sm font-medium">Living arrangement</label>
                        <select id="living_arrangement" wire:model="living_arrangement" class="{{ $select }}">
                            <option value="">Select</option>
                            @foreach ($livingOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('living_arrangement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="has_internet" class="mb-1 block text-sm font-medium">Internet access</label>
                        <select id="has_internet" wire:model="has_internet" class="{{ $select }}">
                            <option value="">Select</option>
                            @foreach ($internetOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('has_internet') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="has_device" class="mb-1 block text-sm font-medium">Device access</label>
                        <select id="has_device" wire:model="has_device" class="{{ $select }}">
                            <option value="">Select</option>
                            @foreach ($deviceOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('has_device') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="has_study_space" class="mb-1 block text-sm font-medium">Study space</label>
                        <select id="has_study_space" wire:model="has_study_space" class="{{ $select }}">
                            <option value="">Select</option>
                            @foreach ($studySpaceOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('has_study_space') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-200 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="back" wire:loading.attr="disabled" wire:target="back" class="{{ $secondary }}"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
                        <button type="button" wire:click="saveSocioeconomic(true)" wire:loading.attr="disabled" wire:target="saveSocioeconomic" class="{{ $outline }}"><x-spinner wire:loading wire:target="saveSocioeconomic" />Save draft</button>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveSocioeconomic" class="{{ $primary }}"><x-spinner wire:loading wire:target="saveSocioeconomic" />Save &amp; continue<i class="ri-arrow-right-line" wire:loading.remove wire:target="saveSocioeconomic" aria-hidden="true"></i></button>
                </div>
            </form>
        @elseif ($step === 'employability')
            <section class="rounded-2xl border border-brand-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-brand-200 bg-brand-50/40 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-900">Employability assessment</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">No employability questions are collected yet. Final items must come from cited research or an approved instrument. The themes below are planning categories only—not questions, scores, or model weights.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900"><i class="ri-flask-line" aria-hidden="true"></i>Awaiting approved items</span>
                </div>
                <ul class="grid gap-2 px-5 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
                    @foreach (($questionnaireSections['employability']['planning_themes'] ?? []) as $theme)
                        <li class="rounded-xl border border-dashed border-brand-200 bg-brand-50/50 px-4 py-3 text-sm text-gray-700">{{ $theme }}</li>
                    @endforeach
                </ul>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-200 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="back" wire:loading.attr="disabled" wire:target="back" class="{{ $secondary }}"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
                    <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next" class="{{ $primary }}">Continue<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
                </div>
            </section>
        @elseif ($step === 'skills')
            <livewire:student.skills-experience-section />
        @elseif ($step === 'grades')
            @php
                $hasGrades = $currentGradeReports->isNotEmpty();
                $latestReport = $currentGradeReports->first();
                $editorOpen = ! $hasGrades || $gradeChoice === 'update';
            @endphp
            <section class="rounded-2xl border border-brand-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-brand-200 bg-brand-50/40 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-900">Grades</h2>
                        <p class="mt-1 text-sm text-gray-600">Add a term by uploading a document, pasting from the UCC portal, or typing it in. You review every row before it is saved.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full border border-brand-200 bg-white px-3 py-1 text-xs font-medium text-brand-900">Optional update</span>
                </div>

                <div class="px-5 py-5 sm:px-6">
                    @if ($hasGrades)
                        <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Grades for this assessment">
                            <button type="button" role="radio" aria-checked="{{ $gradeChoice === 'latest' ? 'true' : 'false' }}" wire:click="chooseGrades('latest')"
                                class="{{ $gradeChoice === 'latest' ? 'border-brand-900 bg-brand-50 ring-1 ring-brand-900' : 'border-brand-200 hover:bg-brand-50/50' }} rounded-xl border p-4 text-left transition-colors duration-200">
                                <span class="flex items-center gap-2 font-semibold text-brand-900"><i class="{{ $gradeChoice === 'latest' ? 'ri-radio-button-line' : 'ri-checkbox-blank-circle-line' }}" aria-hidden="true"></i>Use latest confirmed grades</span>
                                <span class="mt-1 block text-sm text-gray-600">{{ $currentGradeReports->count() }} {{ \Illuminate\Support\Str::plural('term', $currentGradeReports->count()) }} on file. Most recently saved: AY {{ $latestReport->school_year }} · {{ $latestReport->semester }}.</span>
                            </button>
                            <button type="button" role="radio" aria-checked="{{ $gradeChoice === 'update' ? 'true' : 'false' }}" wire:click="chooseGrades('update')"
                                class="{{ $gradeChoice === 'update' ? 'border-brand-900 bg-brand-50 ring-1 ring-brand-900' : 'border-brand-200 hover:bg-brand-50/50' }} rounded-xl border p-4 text-left transition-colors duration-200">
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
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="font-medium text-amber-900">No confirmed grades on file</p>
                            <p class="mt-1 text-sm text-amber-900/80">EduPredict does not fabricate academic data. You can still continue: a prediction without grades has less academic evidence and is marked lower confidence.</p>
                        </div>
                    @endif

                    @if ($editorOpen && ($hasGrades || $draftGradeReports->isNotEmpty()))
                        <div class="mt-5 border-t border-brand-200 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Terms on file</p>
                            <ul class="mt-2 flex flex-col gap-2">
                                @foreach ($currentGradeReports as $report)
                                    <li wire:key="term-{{ $report->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                                        <span class="text-gray-800">AY {{ $report->school_year }} · {{ $report->semester }} <span class="text-xs text-gray-500">· v{{ $report->version }}@if ($report->confirmed_at) · confirmed {{ $report->confirmed_at->timezone($tz)->format('M j, Y') }}@endif</span></span>
                                        <button type="button" wire:click="replaceGradeReport({{ $report->id }})" wire:loading.attr="disabled" wire:target="replaceGradeReport" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand-900 hover:bg-brand-50" aria-label="Update AY {{ $report->school_year }} {{ $report->semester }}"><i class="ri-refresh-line" aria-hidden="true"></i>Update term</button>
                                    </li>
                                @endforeach
                                @foreach ($draftGradeReports as $report)
                                    <li wire:key="draft-{{ $report->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm">
                                        <span class="text-gray-800"><span class="mr-1 rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-gray-700">Draft</span>AY {{ $report->school_year ?: '—' }} · {{ $report->semester ?: '—' }}</span>
                                        <span class="flex gap-1">
                                            <button type="button" wire:click="continueGradeDraft({{ $report->id }})" wire:loading.attr="disabled" wire:target="continueGradeDraft" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand-900 hover:bg-brand-50"><i class="ri-edit-line" aria-hidden="true"></i>Continue</button>
                                            <button type="button" wire:click="deleteGradeDraft({{ $report->id }})" data-confirm="The draft is removed. Confirmed grades are not affected." data-confirm-title="Delete this draft?" data-confirm-button="Delete" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-red-800 hover:bg-red-50"><i class="ri-delete-bin-line" aria-hidden="true"></i>Delete</button>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-xs text-gray-500">Earlier versions and the grades each prediction used are kept in <a href="{{ route('student.history') }}" class="font-medium text-brand-900 underline">History</a>.</p>
                        </div>
                    @endif
                </div>
            </section>

            @if ($editorOpen)
                <livewire:student.grade-report-form :report-id="$gradeDraftId" :replace-id="$gradeReplaceId" :key="'grade-form-'.$gradeFormKey" />
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" wire:click="back" wire:loading.attr="disabled" wire:target="back" class="{{ $secondary }}"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
                <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next" class="{{ $primary }}">Continue to review<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
            </div>
        @else
            <section class="rounded-2xl border border-brand-200 bg-white shadow-sm">
                <div class="border-b border-brand-200 bg-brand-50/40 px-5 py-4 sm:px-6">
                    <h2 class="text-lg font-semibold text-brand-900">Review and run prediction</h2>
                    <p class="mt-1 text-sm text-gray-600">Check what is saved. Edit any section without restarting the others.</p>
                </div>

                <ul class="divide-y divide-brand-200/70 px-5 sm:px-6">
                    @foreach (array_slice($steps, 0, -1, true) as $key => $label)
                        @php
                            [$state, $icon, $stateLabel] = $statusFor($key);
                            $tone = match ($state) {
                                'saved' => 'text-brand-900',
                                'placeholder' => 'text-gray-600',
                                default => $key === 'grades' ? 'text-gray-600' : 'text-amber-800',
                            };
                            $reviewLabel = match (true) {
                                $state === 'saved' => $stateLabel,
                                $key === 'grades' => 'No grades on file',
                                $state === 'placeholder' => 'Not collected yet',
                                $state === 'draft' => 'Draft — not submitted',
                                default => 'Needs attention',
                            };
                        @endphp
                        <li class="flex items-center justify-between gap-3 py-3">
                            <span class="flex items-center gap-3">
                                <i class="{{ $icon }} text-lg {{ $tone }}" aria-hidden="true"></i>
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ $label }}</span>
                                    <span class="block text-xs {{ $tone }}">{{ $reviewLabel }}</span>
                                </span>
                            </span>
                            <button type="button" wire:click="goTo('{{ $key }}')" wire:loading.attr="disabled" wire:target="goTo" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand-900 hover:bg-brand-50" aria-label="Edit {{ $label }}"><i class="ri-pencil-line" aria-hidden="true"></i>Edit</button>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-brand-200 bg-brand-50 px-5 py-4 sm:px-6">
                    <p class="text-sm text-gray-700">Running a prediction creates a new saved attempt. Existing prediction history is never overwritten. Output comes from <span class="font-medium">placeholder-heuristic-v0</span>, not a final trained model.</p>
                    <div class="mt-4"><livewire:student.request-prediction /></div>
                </div>
            </section>

            <div>
                <button type="button" wire:click="back" wire:loading.attr="disabled" wire:target="back" class="{{ $secondary }}"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
            </div>
        @endif
    </div>
</div>
