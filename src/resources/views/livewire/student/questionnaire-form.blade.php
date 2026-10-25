@php
    $partCount = max(1, count($partKeys));
    $primary = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#1f2937] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 disabled:opacity-60';
    $secondary = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60';
    $outline = 'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-brand-900 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60';
    $displayVersionKey = (! $hasDraft && $latestSubmission) ? $latestSubmission->definition_version : $definitionVersion;
    $displayVersion = config('edupredict.questionnaire.versions.'.$displayVersionKey, $version);
    $approved = ($displayVersion['status'] ?? '') === 'panel_approved';
    $partTitle = $navigation === 'construct' ? 'Category' : 'Section';
    $sectionItems = $items;
    $sectionTotal = max(1, $sectionItems->count());
    $sectionSaved = $sectionItems->filter(fn ($item) => isset($saved[$item->id]))->count();
    $sectionAnswered = $sectionItems->filter(function ($item) use ($answers) {
        $value = $answers[$item->id] ?? null;

        return $value !== null && $value !== '';
    })->count();
    $savedPercent = $totalCount > 0 ? (int) round(($savedCount / $totalCount) * 100) : 0;
    $sectionSavedPercent = $sectionItems->count() > 0 ? (int) round(($sectionSaved / $sectionItems->count()) * 100) : 0;
    $sectionItemIds = [];
    $savedPerSection = [];
    $sectionField = $navigation === 'construct' ? 'construct' : 'section';
    foreach ($partKeys as $progressKey) {
        $ids = $allItems->where($sectionField, $progressKey)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $sectionItemIds[$progressKey] = $ids;
        $savedPerSection[$progressKey] = count(array_filter($ids, fn (int $id): bool => isset($saved[$id])));
    }
@endphp

<div
    class="space-y-5"
    data-questionnaire-progress
    data-current="{{ $currentPart }}"
    data-reviewing="{{ $reviewing ? '1' : '0' }}"
    data-sections='@json($sectionItemIds)'
    data-saved='@json($savedPerSection)'
    x-on:confirm-discard-questionnaire.window="window.confirmAction && window.confirmAction({ title: 'Discard unsaved answers?', text: 'Answers already saved in the draft stay on file. Only answers that have not been saved are discarded.', confirmText: 'Discard unsaved answers', danger: true }).then((ok) => { if (ok) $wire.discardUnsavedAndClose() })"
>
    @if ($statusMessage !== '')
        <p class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif

    <section class="relative rounded-2xl border border-brand-200 bg-white shadow-sm" aria-labelledby="questionnaire-title">
        <div class="border-b border-brand-200 bg-brand-50/40 px-5 py-4 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="max-w-prose">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $displayVersion['label'] ?? $displayVersionKey }}</p>
                    <h2 id="questionnaire-title" class="mt-1 text-lg font-bold text-brand-900">Questionnaire</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600">{{ $approved ? 'Panel-approved · not psychometrically validated.' : 'Draft instrument.' }} This questionnaire is not a clinical or diagnostic assessment.</p>
                </div>
                @if ($approved)
                    <span class="rounded-full border border-brand-200 bg-white px-3 py-1 text-xs font-medium text-brand-900">Panel-approved</span>
                @else
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900">Draft instrument</span>
                @endif
            </div>
        </div>

        @if (! $hasDraft && $latestSubmission !== null)
            <div class="px-5 py-6 sm:px-6">
                <h3 class="text-base font-bold text-brand-900">Questionnaire submitted</h3>
                <p class="mt-1 max-w-prose text-sm text-gray-600">
                    Saved {{ $latestSubmission->submitted_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                    under <span class="font-mono">{{ $latestSubmission->definition_version }}</span>.
                    Prediction History is a separate record.
                </p>
                <div class="mt-4 max-w-3xl">
                    <h4 class="text-sm font-bold text-brand-900">Section results saved with this submission</h4>
                    <x-construct-summary class="mt-3" :constructs="$sectionScores" unavailable="No section results were stored with this submission." />
                </div>
                <div class="mt-5 flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap">
                    <button type="button" wire:click="retake" wire:loading.attr="disabled" wire:target="retake" class="{{ $outline }}">
                        <x-spinner wire:loading wire:target="retake" />Start new response
                    </button>
                    <button type="button" wire:click="$parent.next" class="{{ $primary }}">Continue<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
                </div>
            </div>
        @else
            <div class="flex flex-col gap-3 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="max-w-prose text-sm text-gray-600">
                    @if ($hasDraft)
                        A draft is saved. Open the questionnaire to continue. Unsaved choices on screen are not counted as saved.
                    @else
                        Open the questionnaire to answer one section at a time.
                    @endif
                </p>
                <button type="button" wire:click="openWizard" class="{{ $primary }}">Open questionnaire</button>
            </div>
        @endif
    </section>

    @if ($wizardOpen)
        <x-dialog
            title="Questionnaire"
            close="closeWizard"
            discard="discardUnsavedAndClose"
            :unsaved="$unsaved"
            max-width="5xl"
            :description="($displayVersion['label'] ?? $definitionVersion).' · '.($reviewing ? 'Review' : ($partHeadings[$currentPart] ?? 'Section'))"
        >
            <x-slot:subhead>
                <div
                    x-data="{
                        tick: 0,
                        init() {
                            this._progress = () => this.bump();
                            window.addEventListener('change', this._progress);
                        },
                        destroy() {
                            window.removeEventListener('change', this._progress);
                        },
                        bump() { this.tick++ },
                        host() { return this.$el.closest('[data-questionnaire-progress]') },
                        sections() { return JSON.parse(this.host()?.dataset.sections || '{}') },
                        savedMap() { return JSON.parse(this.host()?.dataset.saved || '{}') },
                        currentKey() { return this.host()?.dataset.current || '' },
                        reviewingNow() { return this.host()?.dataset.reviewing === '1' },
                        answeredIn(key) {
                            this.tick;
                            const ids = this.sections()[key] || [];
                            if (key === this.currentKey() && ! this.reviewingNow()) {
                                const root = this.host();
                                return ids.filter((id) => root?.querySelector('input[name=item-' + id + ']:checked')).length;
                            }
                            return this.savedMap()[key] || 0;
                        },
                        status(key) {
                            const ids = this.sections()[key] || [];
                            const answered = this.answeredIn(key);
                            if (key === this.currentKey() && ! this.reviewingNow()) return 'current';
                            if (ids.length > 0 && answered >= ids.length) return 'complete';
                            if (answered > 0) return 'incomplete';
                            return 'empty';
                        },
                        label() {
                            this.tick;
                            const keys = Object.keys(this.sections());
                            let answered = 0;
                            let total = 0;
                            keys.forEach((key) => {
                                const ids = this.sections()[key] || [];
                                total += ids.length;
                                answered += this.answeredIn(key);
                            });
                            const saved = Object.values(this.savedMap()).reduce((sum, count) => sum + Number(count || 0), 0);
                            return 'Answered ' + answered + ' / ' + total + ' · ' + saved + ' / ' + total + ' saved';
                        }
                    }"
                    x-on:questionnaire-section-changed.window="this.$nextTick(() => this.bump())"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-gray-700">{{ $reviewing ? 'Review' : $partTitle.' '.min($currentIndex + 1, $partCount).' of '.$partCount }}</span>
                        <span class="sr-only">{{ $savedCount }} / {{ $totalCount }} saved</span>
                        <span class="font-mono text-xs text-gray-600" aria-live="polite" x-text="label()">Answered {{ $answeredCount }} / {{ $totalCount }} · {{ $savedCount }} / {{ $totalCount }} saved</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">A filled dot is a completed section. The open ring is the section you are on. An empty ring still needs answers.</p>
                    <div class="mt-3 flex flex-wrap gap-2" role="list" aria-label="Questionnaire sections">
                        @foreach ($partKeys as $key)
                            @php
                                $dotSaved = $savedPerSection[$key] ?? 0;
                                $dotTotal = count($sectionItemIds[$key] ?? []);
                                $dotState = ($key === $currentPart && ! $reviewing) ? 'current' : ($dotTotal > 0 && $dotSaved >= $dotTotal ? 'complete' : ($dotSaved > 0 ? 'incomplete' : 'empty'));
                                $dotCode = (string) config('edupredict.questionnaire.sections.'.$key.'.code', strtoupper(substr((string) $key, 0, 1)));
                            @endphp
                            <button
                                type="button"
                                wire:click="selectPart('{{ $key }}')"
                                role="listitem"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-2 py-1 text-xs font-semibold transition hover:border-brand-900"
                                :class="status('{{ $key }}') === 'complete' ? 'border-brand-900 bg-brand-900 text-white' : (status('{{ $key }}') === 'current' ? 'border-brand-900 bg-white text-brand-900' : (status('{{ $key }}') === 'incomplete' ? 'border-amber-400 bg-amber-50 text-amber-950' : 'border-brand-200 bg-white text-gray-500'))"
                                aria-label="{{ $partLabels[$key] ?? $key }} ({{ $dotState }})"
                                :aria-label="(status('{{ $key }}') === 'complete' ? 'Completed ' : (status('{{ $key }}') === 'current' ? 'Current ' : (status('{{ $key }}') === 'incomplete' ? 'Incomplete ' : 'Not started '))) + '{{ $partLabels[$key] ?? $key }}'"
                            >
                                <span class="flex h-6 w-6 items-center justify-center rounded-full border border-current text-[11px]" aria-hidden="true">
                                    <i class="ri-check-line" x-show="status('{{ $key }}') === 'complete'"></i>
                                    <span x-show="status('{{ $key }}') !== 'complete'">{{ $dotCode }}</span>
                                </span>
                                <span class="pr-1">{{ $partLabels[$key] ?? $key }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </x-slot:subhead>

            <form id="questionnaire-wizard" wire:submit="submit" class="relative">
                <x-loading-overlay target="saveDraft,saveAndContinue,submit,previousPart,selectPart,openReview,discardDraft" label="Saving answers…" />

                @if ($allItems->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-gray-600 sm:px-6">No active items are available for {{ $definitionVersion }}.</p>
                @elseif ($reviewing)
                    <div class="space-y-4 px-5 py-5 sm:px-6">
                        <p class="max-w-prose text-sm text-gray-600">Check each section. Submit stores this response as its own version. Earlier submissions stay as they were.</p>
                        <ul class="divide-y divide-brand-200 rounded-xl border border-brand-200">
                            @foreach ($partKeys as $key)
                                @php
                                    $partItems = $allItems->where($navigation, $key);
                                    $count = $partItems->filter(fn ($item) => isset($saved[$item->id]))->count();
                                    $partTotal = $partItems->count();
                                @endphp
                                <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                    <span class="font-medium text-gray-900">{{ $partLabels[$key] ?? $key }}</span>
                                    <span class="{{ $count === $partTotal && $partTotal > 0 ? 'text-brand-900' : 'text-amber-800' }}">{{ $count }}/{{ $partTotal }} saved</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($approved)
                            <p class="text-xs text-gray-500">{{ $displayVersion['source_note'] ?? 'This questionnaire is not a clinical or diagnostic assessment.' }}</p>
                        @endif
                    </div>
                @else
                    <div class="px-5 py-5 sm:px-6">
                        @php $sectionMeta = config('edupredict.questionnaire.sections.'.$currentPart, []); @endphp
                        @if ($navigation === 'section' && ! empty($sectionMeta['description']))
                            <p class="max-w-prose text-sm leading-6 text-gray-600">{{ $sectionMeta['description'] }}</p>
                        @endif
                        @if (! empty($sectionMeta['sensitive']))
                            <p class="mt-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900"><i class="ri-lock-line" aria-hidden="true"></i> These answers are encrypted and stay private to you. They are not copied into prediction History or staff views.</p>
                        @endif
                        <div class="mt-4 space-y-7">
                            @foreach ($items as $item)
                                <fieldset wire:key="item-{{ $item->id }}">
                                    <legend class="max-w-prose text-sm font-medium leading-6 text-gray-900">
                                        @if ($item->displayCode())
                                            <span class="mr-2 font-mono text-xs font-semibold text-brand-900">{{ $item->displayCode() }}</span>
                                        @endif
                                        {{ $item->text }}
                                    </legend>
                                    <div class="mt-3 grid grid-cols-1 gap-2 min-[420px]:grid-cols-2 lg:grid-cols-5" role="radiogroup" aria-label="{{ $item->displayCode() ?: 'Response' }}">
                                        @foreach ($likert as $value => $likertLabel)
                                            <label class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border border-brand-200 px-2 py-3 text-center text-xs text-gray-700 transition-colors duration-150 hover:bg-brand-50 has-[:checked]:border-brand-900 has-[:checked]:bg-brand-50 has-[:checked]:font-medium has-[:checked]:text-brand-900 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-400 sm:flex-col">
                                                <input
                                                    type="radio"
                                                    name="item-{{ $item->id }}"
                                                    wire:model.live="answers.{{ $item->id }}"
                                                    value="{{ $value }}"
                                                    class="h-4 w-4 text-brand-900 focus:ring-brand-200"
                                                >
                                                <span>{{ $likertLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('answers.'.$item->id) <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                @endif
            </form>

            <x-slot:footer>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <button type="button" x-on:click="requestClose()" class="{{ $secondary }}">Cancel</button>
                        @if ($reviewing || $currentIndex > 0)
                            <button type="button" wire:click="previousPart" wire:loading.attr="disabled" wire:target="previousPart" class="{{ $secondary }}"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
                        @endif
                        @unless ($reviewing)
                            <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="{{ $outline }}"><x-spinner wire:loading wire:target="saveDraft" />Save draft</button>
                        @endunless
                        @if ($hasDraft && ! $reviewing)
                            <button
                                type="button"
                                wire:click="discardDraft"
                                wire:loading.attr="disabled"
                                wire:target="discardDraft"
                                data-confirm="This removes the unfinished draft only. Submitted questionnaire responses and prediction history stay as they are."
                                data-confirm-title="Discard this draft?"
                                data-confirm-button="Discard draft"
                                class="{{ $secondary }}"
                            >Discard draft</button>
                        @endif
                    </div>
                    @if ($reviewing)
                        <button type="submit" form="questionnaire-wizard" wire:loading.attr="disabled" wire:target="submit" class="{{ $primary }}"><x-spinner wire:loading wire:target="submit" />Submit</button>
                    @elseif ($currentIndex < count($partKeys) - 1)
                        <button type="button" wire:click="saveAndContinue" wire:loading.attr="disabled" wire:target="saveAndContinue" class="{{ $primary }}"><x-spinner wire:loading wire:target="saveAndContinue" />Save &amp; continue<i class="ri-arrow-right-line" wire:loading.remove wire:target="saveAndContinue" aria-hidden="true"></i></button>
                    @else
                        <button type="button" wire:click="openReview" wire:loading.attr="disabled" wire:target="openReview" class="{{ $primary }}"><x-spinner wire:loading wire:target="openReview" />Review</button>
                    @endif
                </div>
            </x-slot:footer>
        </x-dialog>
    @endif
</div>
<script>
    if (!window.__edupredictQuestionnaireScroll) {
        window.__edupredictQuestionnaireScroll = true;
        window.addEventListener('questionnaire-section-changed', () => {
            const started = performance.now();
            const hold = () => {
                document.querySelectorAll('.dialog-scroll').forEach((el) => {
                    el.style.overflowAnchor = 'none';
                    el.scrollTop = 0;
                });
                if (performance.now() - started < 1200) {
                    requestAnimationFrame(hold);
                }
            };
            hold();
        });
    }
</script>
