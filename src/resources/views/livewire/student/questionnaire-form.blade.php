@php
    $partCount = max(1, count($partKeys));
    $primary = 'inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#1f2937] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 disabled:opacity-60 sm:w-auto';
    $secondary = 'inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60 sm:w-auto';
    $outline = 'inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-brand-900 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60 sm:w-auto';
    $displayVersionKey = (! $hasDraft && $latestSubmission) ? $latestSubmission->definition_version : $definitionVersion;
    $displayVersion = config('edupredict.questionnaire.versions.'.$displayVersionKey, $version);
    $approved = ($displayVersion['status'] ?? '') === 'panel_approved';
    $partTitle = $navigation === 'construct' ? 'Category' : 'Section';
@endphp

<div class="space-y-5">
    @if ($statusMessage !== '')
        <p class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif

    <section class="relative rounded-2xl border border-brand-200 bg-white shadow-sm" aria-labelledby="questionnaire-title">
        <div class="border-b border-brand-200 bg-brand-50/40 px-5 py-4 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $displayVersion['label'] ?? $displayVersionKey }}</p>
                    <h2 id="questionnaire-title" class="mt-1 text-lg font-semibold text-brand-900">{{ (! $hasDraft && $latestSubmission) ? 'Questionnaire' : ($partHeadings[$currentPart] ?? 'Questionnaire') }}</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">
                        @if ($approved)
                            {{ $displayVersion['directions'] ?? '' }}
                        @else
                            Rate each statement honestly. There are no right or wrong answers. These draft items remain subject to research-source approval and are not a clinical or diagnostic assessment.
                        @endif
                    </p>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">{{ $displayVersion['source_note'] ?? 'This questionnaire is not a clinical or diagnostic assessment.' }}</p>
                </div>
                @if ($approved)
                    <span class="rounded-full border border-brand-200 bg-white px-3 py-1 text-xs font-medium text-brand-900">Panel-approved · not psychometrically validated</span>
                @else
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900">Draft instrument</span>
                @endif
            </div>
        </div>

        @if (! $hasDraft && $latestSubmission !== null)
            <div class="px-5 py-8 sm:px-6">
                <div class="text-center">
                    <i class="ri-checkbox-circle-line text-3xl text-brand-900" aria-hidden="true"></i>
                    <h3 class="mt-2 text-lg font-semibold text-brand-900">Questionnaire submitted</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-gray-600">
                        Thank you for answering this questionnaire. This submission was saved {{ $latestSubmission->submitted_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                        under <span class="font-mono">{{ $latestSubmission->definition_version }}</span>.
                        It is separate from prediction History, which keeps the inputs used for each estimate.
                    </p>
                </div>
                <div class="mx-auto mt-5 max-w-3xl text-left">
                    <h4 class="text-sm font-semibold text-brand-900">Section results saved with this submission</h4>
                    <x-construct-summary class="mt-3" :constructs="$sectionScores" unavailable="No section results were stored with this submission." />
                </div>
                <div class="mt-5 flex flex-col items-stretch justify-center gap-2 sm:flex-row sm:flex-wrap">
                    <button type="button" wire:click="retake" wire:loading.attr="disabled" wire:target="retake" class="{{ $outline }}">
                        <x-spinner wire:loading wire:target="retake" />Start new response
                    </button>
                    <button type="button" wire:click="$parent.next" class="{{ $primary }}">Continue<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
                </div>
            </div>
        @elseif ($allItems->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-gray-600 sm:px-6">No active items are available for {{ $definitionVersion }}.</p>
        @else
            <form wire:submit="submit" class="relative">
                <x-loading-overlay target="saveDraft,saveAndContinue,submit,previousPart,selectPart,discardDraft" label="Saving answers…" />

                <div class="px-5 pt-5 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-gray-700">{{ $partTitle }} {{ min($currentIndex + 1, $partCount) }} of {{ $partCount }}</span>
                        <span class="font-mono text-xs text-gray-600" aria-live="polite">{{ $savedCount }} / {{ $totalCount }} saved</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Saved answers are what this count and the section marks use. Choose Save draft or Save &amp; continue to store the statements on this screen.</p>
                    <nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Questionnaire sections">
                        @foreach ($partKeys as $key)
                            @php
                                $partItems = $allItems->where($navigation, $key);
                                $count = $partItems->filter(fn ($item) => isset($saved[$item->id]))->count();
                                $partTotal = $partItems->count();
                            @endphp
                            <button
                                type="button"
                                wire:click="selectPart('{{ $key }}')"
                                wire:loading.attr="disabled"
                                wire:target="selectPart,saveDraft,saveAndContinue,previousPart"
                                @class([
                                    'inline-flex min-h-11 shrink-0 items-center gap-1 rounded-full px-3 py-2 text-xs font-medium transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400',
                                    'bg-brand-900 text-white' => $currentPart === $key,
                                    'border border-brand-200 bg-brand-50 text-brand-900 hover:bg-brand-200/50' => $currentPart !== $key && $count === $partTotal && $partTotal > 0,
                                    'border border-gray-200 bg-white text-gray-600 hover:border-brand-200 hover:text-brand-900' => $currentPart !== $key && $count < $partTotal,
                                ])
                                @if ($currentPart === $key) aria-current="step" @endif
                            >
                                @if ($count === $partTotal && $partTotal > 0)<i class="ri-check-line" aria-hidden="true"></i>@endif
                                {{ $partLabels[$key] ?? $key }} <span class="opacity-75">{{ $count }}/{{ $partTotal }}</span>
                            </button>
                        @endforeach
                    </nav>
                </div>

                <div class="px-5 py-5 sm:px-6">
                    @php $sectionMeta = config('edupredict.questionnaire.sections.'.$currentPart, []); @endphp
                    @if ($navigation === 'section' && ! empty($sectionMeta['description']))
                        <p class="text-sm leading-6 text-gray-600">{{ $sectionMeta['description'] }}</p>
                    @endif
                    @if (! empty($sectionMeta['sensitive']))
                        <p class="mt-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900"><i class="ri-lock-line" aria-hidden="true"></i> These answers are encrypted and stay private to you. They are not copied into prediction History or staff views.</p>
                    @endif
                    <div class="mt-4 space-y-7">
                        @foreach ($items as $item)
                            <fieldset wire:key="item-{{ $item->id }}">
                                <legend class="text-sm font-medium leading-6 text-gray-900">
                                    @if ($item->displayCode())
                                        <span class="mr-2 font-mono text-xs font-semibold text-brand-900">{{ $item->displayCode() }}</span>
                                    @endif
                                    {{ $item->text }}
                                </legend>
                                <div class="mt-3 grid grid-cols-1 gap-2 min-[420px]:grid-cols-2 sm:grid-cols-5">
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

                <div class="flex flex-col gap-3 border-t border-brand-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        @if ($currentIndex > 0)
                            <button type="button" wire:click="previousPart" wire:loading.attr="disabled" wire:target="previousPart" class="{{ $secondary }}"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
                        @endif
                        <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="{{ $outline }}"><x-spinner wire:loading wire:target="saveDraft" />Save draft</button>
                        @if ($legacyDraft)
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
                    @if ($currentIndex < count($partKeys) - 1)
                        <button type="button" wire:click="saveAndContinue" wire:loading.attr="disabled" wire:target="saveAndContinue" class="{{ $primary }}"><x-spinner wire:loading wire:target="saveAndContinue" />Save &amp; continue<i class="ri-arrow-right-line" wire:loading.remove wire:target="saveAndContinue" aria-hidden="true"></i></button>
                    @else
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="{{ $primary }}"><x-spinner wire:loading wire:target="submit" />Submit &amp; continue<i class="ri-arrow-right-line" wire:loading.remove wire:target="submit" aria-hidden="true"></i></button>
                    @endif
                </div>
            </form>
        @endif
    </section>
</div>
