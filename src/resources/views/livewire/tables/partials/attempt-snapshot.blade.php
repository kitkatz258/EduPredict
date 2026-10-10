@php
    $prediction = $snapshot['prediction'];
    $questionnaire = $snapshot['questionnaire'];
    $skills = $snapshot['skills'];
    $grades = $snapshot['grades'];
    $shift = $snapshot['shift'];
    $requestedAt = $prediction->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
    $experienceTypes = config('edupredict.profile.experience_types', []);
    $unavailable = 'Not available for this attempt.';
@endphp

<x-dialog
    title="Saved attempt"
    close="closeView"
    max-width="4xl"
    :description="$requestedAt.' · '.($isLatest ? 'Latest' : 'Archived').' · read-only'"
>
    <div class="space-y-6 px-5 py-5 sm:px-6">
        <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
            <i class="ri-lock-line mr-1" aria-hidden="true"></i>This is what was saved when the prediction ran. Later changes to your Assessment or grades do not change it.
            @unless ($snapshot['complete'])
                Some inputs were not saved with this older attempt and show as not available.
            @endunless
        </p>

        <section aria-labelledby="attempt-results-heading">
            <h3 id="attempt-results-heading" class="text-sm font-semibold text-brand-900">Results</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-brand-200 p-4">
                    <p class="text-xs text-gray-500">Employability</p>
                    <p class="mt-1 text-2xl font-semibold text-brand-900">{{ number_format((float) $prediction->employability_score, 0) }}<span class="text-sm font-normal text-gray-500"> / 100</span></p>
                </div>
                <div class="rounded-xl border border-brand-200 p-4">
                    <p class="text-xs text-gray-500">Dropout risk</p>
                    <div class="mt-2"><x-risk-badge :level="$prediction->dropout_risk" /></div>
                </div>
                <div class="rounded-xl border border-brand-200 p-4">
                    <p class="text-xs text-gray-500">Confidence</p>
                    <div class="mt-2">
                        @if ($prediction->confidence === 'low')
                            <x-confidence-tag />
                        @else
                            <span class="text-sm font-medium text-brand-900">Normal</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="mt-3 rounded-xl border border-brand-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Program-shift indicator</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-900">{{ $shift['label'] }}</span>
                    @if ($shift['engagement'])
                        <span class="inline-flex items-center rounded-full border border-brand-200 bg-white px-2.5 py-1 text-xs font-medium text-brand-900">Engagement: {{ $shift['engagement'] }}</span>
                    @endif
                </div>
                <p class="mt-2 text-sm text-gray-700">{{ $shift['message'] }}</p>
            </div>
        </section>

        <section aria-labelledby="attempt-factors-heading">
            <h3 id="attempt-factors-heading" class="text-sm font-semibold text-brand-900">Contributing factors</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                @foreach (['employability' => 'Employability', 'dropout' => 'Dropout risk'] as $group => $label)
                    <div class="rounded-xl border border-brand-200 p-4">
                        <p class="text-xs font-semibold text-gray-600">{{ $label }}</p>
                        @if ($snapshot['factors'][$group] === [])
                            <p class="mt-2 text-sm text-gray-500">{{ $unavailable }}</p>
                        @else
                            <ul class="mt-2 space-y-1.5 text-sm">
                                @foreach (array_slice($snapshot['factors'][$group], 0, 6) as $factor)
                                    <li class="flex items-start gap-2">
                                        @if (($factor['direction'] ?? '') === '+')
                                            <i class="ri-arrow-up-line mt-0.5 text-brand-900" aria-hidden="true"></i>
                                            <span class="text-gray-800">{{ $factor['label'] }} <span class="sr-only">(supports this estimate)</span></span>
                                        @else
                                            <i class="ri-arrow-down-line mt-0.5 text-amber-700" aria-hidden="true"></i>
                                            <span class="text-gray-800">{{ $factor['label'] }} <span class="sr-only">(an area where support could help)</span></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="attempt-constructs-heading">
            <h3 id="attempt-constructs-heading" class="text-sm font-semibold text-brand-900">Category summary</h3>
            <x-construct-summary class="mt-3 rounded-xl border border-brand-200 p-4" :constructs="$snapshot['constructs']" :unavailable="$unavailable" />
        </section>

        <section aria-labelledby="attempt-questionnaire-heading">
            <h3 id="attempt-questionnaire-heading" class="text-sm font-semibold text-brand-900">Questionnaire used</h3>
            @if ($questionnaire === null)
                <p class="mt-2 text-sm text-gray-500">{{ $unavailable }}</p>
            @elseif (! ($questionnaire['submitted'] ?? false))
                <p class="mt-2 text-sm text-gray-600">No submitted questionnaire was on file for this attempt.</p>
            @else
                <p class="mt-2 text-sm text-gray-700">
                    Definition version <span class="font-mono">{{ $questionnaire['definition_version'] ?? '—' }}</span>
                    @if (! empty($questionnaire['submitted_at']))
                        · submitted {{ \Illuminate\Support\Carbon::parse($questionnaire['submitted_at'])->timezone(config('app.timezone'))->format('M j, Y') }}
                    @endif
                </p>
                @if (empty($questionnaire['answers']))
                    <p class="mt-2 text-sm text-gray-500">Individual answers were not recorded with this attempt.</p>
                @else
                    <details class="mt-3 rounded-xl border border-brand-200">
                        <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-brand-900">Show your {{ count($questionnaire['answers']) }} answers</summary>
                        <ol class="divide-y divide-brand-200/60 border-t border-brand-200 text-sm">
                            @foreach ($questionnaire['answers'] as $answer)
                                <li class="flex items-start justify-between gap-4 px-4 py-2">
                                    <span class="text-gray-800">{{ $answer['text'] ?? 'Item '.$answer['questionnaire_item_id'] }}</span>
                                    <span class="shrink-0 font-mono text-brand-900" aria-label="Answer {{ $answer['value'] }} of 5">{{ $answer['value'] }}/5</span>
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif
            @endif
        </section>

        <section aria-labelledby="attempt-skills-heading">
            <h3 id="attempt-skills-heading" class="text-sm font-semibold text-brand-900">Skills and experience used</h3>
            @if ($skills === null)
                <p class="mt-2 text-sm text-gray-500">{{ $unavailable }}</p>
            @elseif (! ($skills['section_saved'] ?? false))
                <p class="mt-2 text-sm text-gray-600">The Skills & Experience section was not saved at the time, so no entries were used.</p>
            @else
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-brand-200 p-4">
                        <p class="text-xs font-semibold text-gray-600">Technical skills</p>
                        @forelse ($skills['technical_skills'] ?? [] as $skill)
                            <span class="mr-1 mt-2 inline-flex rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs text-brand-900">{{ $skill['name'] ?? '' }}</span>
                        @empty
                            <p class="mt-2 text-sm text-gray-500">None listed.</p>
                        @endforelse
                    </div>
                    <div class="rounded-xl border border-brand-200 p-4">
                        <p class="text-xs font-semibold text-gray-600">Certifications</p>
                        <ul class="mt-2 space-y-1 text-sm text-gray-800">
                            @forelse ($skills['certifications'] ?? [] as $cert)
                                <li>{{ $cert['title'] ?? '' }}@if (! empty($cert['issuer'])) <span class="text-gray-500">· {{ $cert['issuer'] }}</span>@endif</li>
                            @empty
                                <li class="text-gray-500">None listed.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="rounded-xl border border-brand-200 p-4">
                        <p class="text-xs font-semibold text-gray-600">Work experience</p>
                        <ul class="mt-2 space-y-1 text-sm text-gray-800">
                            @forelse ($skills['work_experience'] ?? [] as $job)
                                <li>{{ $job['role_title'] ?? '' }}@if (! empty($job['organization'])) <span class="text-gray-500">· {{ $job['organization'] }}</span>@endif <span class="text-xs text-gray-500">({{ $experienceTypes[$job['experience_type'] ?? ''] ?? \Illuminate\Support\Str::headline((string) ($job['experience_type'] ?? '')) }})</span></li>
                            @empty
                                <li class="text-gray-500">None listed.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif
        </section>

        <section aria-labelledby="attempt-grades-heading">
            <h3 id="attempt-grades-heading" class="text-sm font-semibold text-brand-900">Grades used</h3>
            @if ($grades === null)
                <p class="mt-2 text-sm text-gray-500">{{ $unavailable }}</p>
            @elseif (! ($grades['has_grades'] ?? false))
                <p class="mt-2 text-sm text-gray-600">No confirmed grades were on file. The estimate ran without academic data and is marked lower confidence.</p>
            @else
                <p class="mt-2 text-sm text-gray-700">
                    GWA <span class="font-semibold text-brand-900">{{ isset($grades['rounded_gwa']) ? number_format((float) $grades['rounded_gwa'], 2) : '—' }}</span>
                    · Failed subjects {{ $grades['failed_subjects'] ?? 0 }}
                    @if ($grades['gwa_provisional'] ?? false)
                        <span class="ml-1 text-xs text-amber-900">Provisional: {{ $grades['incomplete_subjects'] ?? 0 }} incomplete left out.</span>
                    @endif
                </p>
                <div class="mt-3 space-y-3">
                    @foreach ($grades['reports'] ?? [] as $report)
                        <details class="rounded-xl border border-brand-200">
                            <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-brand-900">AY {{ $report['school_year'] }} · {{ $report['semester'] }} · version {{ $report['report_version'] ?? 1 }}</summary>
                            <div class="overflow-x-auto border-t border-brand-200">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-brand-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        <tr>
                                            <th scope="col" class="px-3 py-2">Code</th>
                                            <th scope="col" class="px-3 py-2">Subject</th>
                                            <th scope="col" class="px-3 py-2">Units</th>
                                            <th scope="col" class="px-3 py-2">Final grade</th>
                                            <th scope="col" class="px-3 py-2">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-brand-200/60">
                                        @foreach ($report['subjects'] ?? [] as $subject)
                                            <tr>
                                                <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700">{{ $subject['subject_code'] }}</td>
                                                <td class="px-3 py-2 text-gray-900">{{ $subject['subject_name'] }}</td>
                                                <td class="px-3 py-2">{{ rtrim(rtrim(number_format((float) $subject['units'], 2), '0'), '.') }}</td>
                                                <td class="px-3 py-2 font-mono">{{ ($subject['final_grade'] ?? '') !== '' ? $subject['final_grade'] : '—' }}</td>
                                                <td class="px-3 py-2 text-gray-700">{{ ($subject['remarks'] ?? '') !== '' ? ucfirst(strtolower($subject['remarks'])) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-labelledby="attempt-careers-heading">
            <h3 id="attempt-careers-heading" class="text-sm font-semibold text-brand-900">Career matches saved with this attempt</h3>
            @if ($snapshot['careers']->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No career matches were saved with this attempt.</p>
            @else
                <ol class="mt-2 space-y-1 text-sm text-gray-800">
                    @foreach ($snapshot['careers'] as $match)
                        <li class="flex items-center justify-between gap-3">
                            <span>{{ $match->occupation?->title }} <span class="text-xs text-gray-500">PSOC {{ $match->occupation?->psoc_code }}</span></span>
                            <span class="font-semibold text-brand-900">{{ (int) round((float) $match->compatibility_score) }}%</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section aria-labelledby="attempt-references-heading" class="rounded-xl border border-gray-200 bg-gray-50 p-4">
            <h3 id="attempt-references-heading" class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stored references</h3>
            <dl class="mt-2 grid gap-x-6 gap-y-1 text-xs text-gray-700 sm:grid-cols-2">
                <div><dt class="inline text-gray-500">Attempt:</dt> <dd class="inline font-mono">#{{ $prediction->id }}</dd></div>
                <div><dt class="inline text-gray-500">Model:</dt> <dd class="inline font-mono">{{ $prediction->model_version }}</dd></div>
                <div>
                    <dt class="inline text-gray-500">Questionnaire response:</dt>
                    <dd class="inline font-mono">{{ isset($questionnaire['questionnaire_response_id']) ? '#'.$questionnaire['questionnaire_response_id'].' ('.($questionnaire['definition_version'] ?? '—').')' : $unavailable }}</dd>
                </div>
                <div>
                    <dt class="inline text-gray-500">Grade versions:</dt>
                    <dd class="inline font-mono">
                        @if ($grades === null)
                            {{ $unavailable }}
                        @elseif (empty($grades['reports']))
                            None on file
                        @else
                            {{ collect($grades['reports'])->map(fn ($r) => '#'.$r['grade_report_id'].' v'.($r['report_version'] ?? 1))->implode(', ') }}
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <x-model-disclosure :version="$prediction->model_version" />
    </div>
    <div class="flex justify-end border-t border-brand-200 px-5 py-3 sm:px-6">
        <button type="button" wire:click="closeView" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Close</button>
    </div>
</x-dialog>
