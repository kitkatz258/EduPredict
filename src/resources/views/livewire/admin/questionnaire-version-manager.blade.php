<div class="space-y-4">
    <section class="rounded-2xl border border-brand-200 bg-white px-5 py-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 flex-1">
                <label for="questionnaire-version" class="text-xs font-semibold uppercase tracking-wide text-gray-500">Questionnaire version</label>
                <select id="questionnaire-version" wire:change="selectVersion($event.target.value)" class="ui-field mt-1">
                    @foreach ($versions as $version)
                        <option value="{{ $version->code }}" @selected($selected?->code === $version->code)>{{ $version->selectorLabel($version->items_count) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button" wire:click="openCreate" class="ui-btn ui-btn-primary min-h-11">Create new version</button>
                @if ($selected?->isMutable())
                    <button type="button" wire:click="openReview" class="ui-btn ui-btn-secondary min-h-11">Review & publish</button>
                @else
                    <button type="button" wire:click="openCopy" class="ui-btn ui-btn-secondary min-h-11">Create editable copy</button>
                @endif
                <button type="button" wire:click="openPreview" class="ui-btn ui-btn-secondary min-h-11">Student preview</button>
            </div>
        </div>

        @if ($selected)
            <div class="mt-4">
                <h3 class="text-base font-bold text-brand-900">{{ $selected->name }}</h3>
                <p class="mt-1 font-mono text-xs text-gray-600">{{ $selected->code }} · {{ $selected->stateLabel() }}</p>
                <p class="mt-2 max-w-prose text-sm leading-6 text-gray-600">
                    @if ($selected->isCurrent() && $selected->panel_approved)
                        Panel-approved for this capstone · not psychometrically validated · not a clinical or diagnostic assessment.
                        Students starting a new assessment see this version. Historical questionnaires and open drafts stay on the version they already use.
                    @elseif ($selected->isMutable())
                        Draft only. It is not published and does not inherit panel approval. Students cannot select it.
                    @elseif ($selected->panel_approved)
                        Published historical questionnaire. Its questions, submissions, and section means stay as they were. Panel approval is not psychometric validation.
                    @else
                        Published questionnaire. It is not the version new students receive.
                    @endif
                </p>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($sectionCounts as $section => $count)
                        @php $meta = $sections[$section] ?? []; @endphp
                        <div class="rounded-xl border border-brand-200 bg-brand-50/40 px-3 py-2">
                            <dt class="text-xs font-semibold text-brand-900">{{ $meta['short'] ?? ($meta['label'] ?? $section) }}</dt>
                            <dd class="mt-1 text-gray-700">{{ $count }} active</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </section>

    @if ($showCreate)
        <x-dialog title="Create new questionnaire version" close="closeCreate" description="A new version is an isolated draft. It is not published and does not inherit panel approval.">
            <form id="questionnaire-version-form" wire:submit="createDraft" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="createDraft,confirmCreate" label="Creating draft…" />
                <div>
                    <label for="version-name" class="mb-1 block text-sm font-medium">Version name</label>
                    <input id="version-name" type="text" wire:model="name" maxlength="120" required class="ui-field" autocomplete="off">
                    @error('name') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Starting questions</legend>
                    <div class="space-y-2 text-sm">
                        <label class="flex items-start gap-2">
                            <input type="radio" wire:model="mode" value="copy_active" class="mt-1 border-brand-200 text-brand-900">
                            <span>Copy current active version. Question text is copied. Answers are not.</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input type="radio" wire:model="mode" value="copy_selected" class="mt-1 border-brand-200 text-brand-900">
                            <span>Copy the version selected above{{ $selected ? ' ('.$selected->shortLabel().')' : '' }}.</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input type="radio" wire:model="mode" value="empty" class="mt-1 border-brand-200 text-brand-900">
                            <span>Start empty.</span>
                        </label>
                    </div>
                    @error('mode') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </fieldset>
                <div>
                    <label for="version-summary" class="mb-1 block text-sm font-medium">Change summary <span class="font-normal text-gray-500">(optional)</span></label>
                    <textarea id="version-summary" wire:model="summary" rows="3" maxlength="2000" class="ui-field"></textarea>
                    @error('summary') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
            </form>
            <x-slot:footer>
                <x-dialog-actions form="questionnaire-version-form" submit="Create draft" target="createDraft,confirmCreate" />
            </x-slot:footer>
        </x-dialog>
    @endif

    @if ($showReview && $selected)
        <x-dialog title="Review and publish" close="closeReview" max-width="4xl" description="Publishing activates this draft for new questionnaires only.">
            <form id="questionnaire-publish-form" wire:submit="publish" class="relative space-y-4 px-5 py-5 sm:px-6">
                <x-loading-overlay target="publish,confirmPublish" label="Publishing…" />
                <div>
                    <h3 class="text-base font-bold text-brand-900">{{ $selected->name }}</h3>
                    <p class="mt-1 font-mono text-xs text-gray-600">{{ $selected->code }}</p>
                    <p class="mt-2 text-sm text-gray-700">Approval: not inherited. This draft is not panel-approved until you record new evidence below.</p>
                    @if ($selected->source_code)
                        <p class="mt-1 text-sm text-gray-700">Copied from <span class="font-mono">{{ $selected->source_code }}</span>.</p>
                    @else
                        <p class="mt-1 text-sm text-gray-700">Started empty.</p>
                    @endif
                    @if ($selected->change_summary)
                        <p class="mt-2 text-sm text-gray-700">{{ $selected->change_summary }}</p>
                    @endif
                </div>
                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    @foreach ($sectionCounts as $section => $count)
                        <div class="rounded-lg border border-brand-200 px-3 py-2">
                            <dt class="font-medium text-brand-900">{{ $sections[$section]['heading'] ?? $section }}</dt>
                            <dd>{{ $count }} active</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($changes)
                    <div>
                        <h4 class="text-sm font-bold text-brand-900">Changes from the source</h4>
                        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-gray-700">
                            @foreach ($changes['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($blockingErrors !== [] || $publishErrors !== [])
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2" role="alert">
                        <p class="text-sm font-semibold text-red-800">This draft cannot be published yet.</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-red-800">
                            @foreach (array_unique([...$blockingErrors, ...$publishErrors]) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="rounded-xl border border-brand-200 bg-brand-50/40 px-3 py-3">
                    <h4 class="text-sm font-bold text-brand-900">Student preview</h4>
                    <p class="mt-1 text-xs text-gray-600">Preview only. Answers are not saved.</p>
                    @forelse ($previewItems as $section => $items)
                        <h5 class="mt-3 text-sm font-semibold text-brand-900">{{ $sections[$section]['heading'] ?? $section }}</h5>
                        <ol class="mt-1 space-y-1 text-sm text-gray-800">
                            @foreach ($items as $item)
                                <li><span class="font-mono text-xs">{{ $item->displayCode() }}</span> {{ $item->text }}</li>
                            @endforeach
                        </ol>
                    @empty
                        <p class="mt-2 text-sm text-gray-600">No active questions in this draft.</p>
                    @endforelse
                </div>
                <div>
                    <label for="approval-evidence" class="mb-1 block text-sm font-medium">New approval evidence</label>
                    <textarea id="approval-evidence" wire:model="approvalEvidence" rows="3" maxlength="2000" class="ui-field" required></textarea>
                    @error('approvalEvidence') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" wire:model="acknowledgeActivation" class="mt-1 rounded border-brand-200 text-brand-900" required>
                    <span>Activation affects new questionnaires only. Existing submissions and in-progress drafts stay on their original versions.</span>
                </label>
                @error('acknowledgeActivation') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </form>
            <x-slot:footer>
                <x-dialog-actions form="questionnaire-publish-form" submit="Publish version" target="publish,confirmPublish" />
            </x-slot:footer>
        </x-dialog>
    @endif

    @if ($showPreview && $selected)
        <x-dialog title="Student preview" close="closePreview" max-width="4xl" description="Preview only. Answers are not saved and this does not start a student assessment.">
            <div class="space-y-4 px-5 py-5 sm:px-6">
                <p class="text-sm text-gray-700">{{ $selected->shortLabel() }} · {{ $selected->stateLabel() }}. Scale: 1 Strongly Disagree through 5 Strongly Agree. No answer is stored.</p>
                @forelse ($previewItems as $section => $items)
                    <section>
                        <h3 class="text-sm font-bold text-brand-900">{{ $sections[$section]['heading'] ?? $section }}</h3>
                        <ol class="mt-2 space-y-3">
                            @foreach ($items as $item)
                                <li class="rounded-lg border border-brand-200 px-3 py-2">
                                    <p class="text-sm text-gray-900"><span class="font-mono text-xs font-semibold">{{ $item->displayCode() }}</span> {{ $item->text }}</p>
                                    <p class="mt-1 text-xs text-gray-600">{{ implode(' · ', $likert) }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @empty
                    <p class="text-sm text-gray-600">No active questions to preview.</p>
                @endforelse
            </div>
            <x-slot:footer>
                <button type="button" wire:click="closePreview" class="ui-btn ui-btn-secondary min-h-11">Close</button>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
