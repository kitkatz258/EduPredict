@php
    $categoryCount = max(1, count($constructKeys));
    $primary = 'inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 disabled:opacity-60';
    $secondary = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60';
    $outline = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand-900 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 disabled:opacity-60';
@endphp

<div class="space-y-5">
    @if ($statusMessage !== '')
        <p class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif

    <section class="relative rounded-2xl border border-brand-200 bg-white shadow-sm" aria-labelledby="questionnaire-title">
        <div class="border-b border-brand-200 bg-brand-50/40 px-5 py-4 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Behavioral self-report · {{ $version['label'] ?? $definitionVersion }}</p>
                    <h2 id="questionnaire-title" class="mt-1 text-lg font-semibold text-brand-900">Academic behavior</h2>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600">Rate each statement honestly. There are no right or wrong answers. These draft items remain subject to research-source approval and are not a clinical or diagnostic assessment.</p>
                </div>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900">Draft instrument</span>
            </div>
        </div>

        @if ($allItems->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-gray-600 sm:px-6">No active items are available for {{ $definitionVersion }}.</p>
        @elseif (! $hasDraft && $latestSubmission !== null)
            <div class="px-5 py-8 text-center sm:px-6">
                <i class="ri-checkbox-circle-line text-3xl text-brand-900" aria-hidden="true"></i>
                <h3 class="mt-2 text-lg font-semibold text-brand-900">Questionnaire submitted</h3>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-600">
                    Your latest response was saved {{ $latestSubmission->submitted_at?->timezone(config('app.timezone'))->format('M j, Y') }}. Start a new response only when you want to update these answers. Earlier attempts stay in History.
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <button type="button" wire:click="retake" wire:loading.attr="disabled" wire:target="retake" class="{{ $outline }}">
                        <x-spinner wire:loading wire:target="retake" />Start new response
                    </button>
                    <button type="button" wire:click="$parent.next" class="{{ $primary }}">Continue<i class="ri-arrow-right-line" aria-hidden="true"></i></button>
                </div>
            </div>
        @else
            <form wire:submit="submit" class="relative">
                <x-loading-overlay target="saveDraft,saveAndContinue,submit" label="Saving answers…" />

                <div class="px-5 pt-5 sm:px-6">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium text-gray-700">Category {{ min($currentIndex + 1, $categoryCount) }} of {{ $categoryCount }}</span>
                        <span class="font-mono text-xs text-gray-600">{{ $answeredCount }} / {{ $totalCount }} answered</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-200/40" role="progressbar" aria-label="Questionnaire answers" aria-valuenow="{{ $answeredCount }}" aria-valuemin="0" aria-valuemax="{{ $totalCount }}">
                        <div class="h-full rounded-full bg-brand-400 transition-[width] duration-300 ease-out motion-reduce:transition-none" style="width: {{ $totalCount > 0 ? round(($answeredCount / $totalCount) * 100) : 0 }}%"></div>
                    </div>
                    <nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Questionnaire categories">
                        @foreach ($constructKeys as $key)
                            @php
                                $count = $allItems->where('construct', $key)->filter(fn ($item) => isset($answers[$item->id]) && $answers[$item->id] !== '')->count();
                                $constructTotal = $allItems->where('construct', $key)->count();
                            @endphp
                            <button
                                type="button"
                                wire:click="selectConstruct('{{ $key }}')"
                                @class([
                                    'inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition-colors duration-200',
                                    'bg-brand-900 text-white' => $construct === $key,
                                    'border border-brand-200 bg-brand-50 text-brand-900 hover:bg-brand-200/50' => $construct !== $key && $count === $constructTotal,
                                    'border border-gray-200 bg-white text-gray-600 hover:border-brand-200 hover:text-brand-900' => $construct !== $key && $count < $constructTotal,
                                ])
                                @if ($construct === $key) aria-current="step" @endif
                            >
                                @if ($count === $constructTotal && $constructTotal > 0)<i class="ri-check-line" aria-hidden="true"></i>@endif
                                {{ $constructs[$key] ?? $key }} <span class="opacity-75">{{ $count }}/{{ $constructTotal }}</span>
                            </button>
                        @endforeach
                    </nav>
                </div>

                <div class="px-5 py-5 sm:px-6">
                    <h3 class="text-base font-semibold text-brand-900">{{ $constructs[$construct] ?? $construct }}</h3>
                    <div class="mt-4 space-y-7">
                        @foreach ($items as $item)
                            <fieldset wire:key="item-{{ $item->id }}">
                                <legend class="text-sm font-medium leading-6 text-gray-900">{{ $item->text }}</legend>
                                <div class="mt-3 grid gap-2 sm:grid-cols-5">
                                    @foreach ($likert as $value => $likertLabel)
                                        <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-brand-200 px-2 py-3 text-center text-xs text-gray-700 transition-colors duration-150 hover:bg-brand-50 has-[:checked]:border-brand-900 has-[:checked]:bg-brand-50 has-[:checked]:font-medium has-[:checked]:text-brand-900 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-400">
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

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-200 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap gap-2">
                        @if ($currentIndex > 0)
                            <button type="button" wire:click="previousConstruct" class="{{ $secondary }}"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
                        @endif
                        <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="{{ $outline }}"><x-spinner wire:loading wire:target="saveDraft" />Save draft</button>
                    </div>
                    @if ($currentIndex < count($constructKeys) - 1)
                        <button type="button" wire:click="saveAndContinue" wire:loading.attr="disabled" wire:target="saveAndContinue" class="{{ $primary }}"><x-spinner wire:loading wire:target="saveAndContinue" />Save &amp; continue<i class="ri-arrow-right-line" wire:loading.remove wire:target="saveAndContinue" aria-hidden="true"></i></button>
                    @else
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="{{ $primary }}"><x-spinner wire:loading wire:target="submit" />Submit &amp; continue<i class="ri-arrow-right-line" wire:loading.remove wire:target="submit" aria-hidden="true"></i></button>
                    @endif
                </div>
            </form>
        @endif
    </section>
</div>
