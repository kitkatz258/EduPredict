<div class="space-y-5">
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="questionnaire-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Behavioral self-report · {{ $version['label'] ?? $definitionVersion }}</p>
                <h3 id="questionnaire-title" class="mt-1 text-xl font-semibold text-brand-900">Academic behavior questionnaire</h3>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">Rate each statement honestly. There are no right or wrong answers. These draft items remain subject to research-source approval and are not a clinical or diagnostic assessment.</p>
            </div>
            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900">Draft instrument</span>
        </div>

        <div class="mt-5">
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="font-medium text-gray-700">Step {{ min($currentIndex + 1, max(1, count($constructKeys))) }} of {{ max(1, count($constructKeys)) }}</span>
                <span class="text-gray-600">{{ $answeredCount }} / {{ $totalCount }} answered</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-brand-50" role="progressbar" aria-label="Questionnaire answers" aria-valuenow="{{ $answeredCount }}" aria-valuemin="0" aria-valuemax="{{ $totalCount }}">
                <div class="h-full rounded-full bg-brand-400 transition-all" style="width: {{ $totalCount > 0 ? round(($answeredCount / $totalCount) * 100) : 0 }}%"></div>
            </div>
        </div>

        <nav class="mt-4 flex gap-2 overflow-x-auto pb-1" aria-label="Questionnaire categories">
            @foreach ($constructKeys as $key)
                @php
                    $count = $allItems->where('construct', $key)->filter(fn ($item) => isset($answers[$item->id]) && $answers[$item->id] !== '')->count();
                    $constructTotal = $allItems->where('construct', $key)->count();
                @endphp
                <button
                    type="button"
                    wire:click="selectConstruct('{{ $key }}')"
                    class="{{ $construct === $key ? 'bg-brand-900 text-white' : 'border border-brand-200 bg-brand-50 text-brand-900 hover:bg-brand-200/50' }} shrink-0 rounded-full px-3 py-1.5 text-xs font-medium"
                    @if ($construct === $key) aria-current="step" @endif
                >
                    {{ $constructs[$key] ?? $key }} <span class="opacity-75">{{ $count }}/{{ $constructTotal }}</span>
                </button>
            @endforeach
        </nav>
    </section>

    @if ($statusMessage !== '')
        <p class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif

    @if ($allItems->isEmpty())
        <p class="rounded-2xl border border-brand-200 bg-white px-5 py-8 text-center text-sm text-gray-600">No active items are available for {{ $definitionVersion }}.</p>
    @elseif (! $hasDraft && $history->isNotEmpty())
        <section class="rounded-2xl border border-brand-200 bg-white p-6 text-center shadow-sm">
            <i class="ri-checkbox-circle-line text-3xl text-brand-900" aria-hidden="true"></i>
            <h3 class="mt-2 text-lg font-semibold text-brand-900">Questionnaire submitted</h3>
            <p class="mt-1 text-sm text-gray-600">Your latest response is saved. Start a new response only when you want to update these answers.</p>
            <button type="button" wire:click="retake" wire:loading.attr="disabled" wire:target="retake" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60">
                <x-spinner wire:loading wire:target="retake" />Start new response
            </button>
        </section>
    @else
        <form wire:submit="submit" class="relative rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
            <x-loading-overlay target="saveDraft,saveAndContinue,submit" label="Saving answers…" />
            <div class="border-b border-brand-200 pb-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic behavior</p>
                <h3 class="mt-1 text-lg font-semibold text-brand-900">{{ $constructs[$construct] ?? $construct }}</h3>
            </div>

            <div class="mt-5 space-y-7">
                @foreach ($items as $item)
                    <fieldset>
                        <legend class="text-sm font-medium leading-6 text-gray-900">{{ $item->text }}</legend>
                        <div class="mt-3 grid gap-2 sm:grid-cols-5">
                            @foreach ($likert as $value => $likertLabel)
                                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-brand-200 px-2 py-3 text-center text-xs text-gray-700 transition hover:bg-brand-50 has-[:checked]:border-brand-900 has-[:checked]:bg-brand-50 has-[:checked]:font-medium has-[:checked]:text-brand-900">
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

            <div class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-brand-200 pt-5">
                <div class="flex flex-wrap gap-2">
                    @if ($currentIndex > 0)
                        <button type="button" wire:click="previousConstruct" class="inline-flex items-center gap-1 rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50"><i class="ri-arrow-left-line" aria-hidden="true"></i>Back</button>
                    @endif
                    <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="inline-flex items-center gap-2 rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 disabled:opacity-60"><x-spinner wire:loading wire:target="saveDraft" />Save draft</button>
                </div>
                @if ($currentIndex < count($constructKeys) - 1)
                    <button type="button" wire:click="saveAndContinue" wire:loading.attr="disabled" wire:target="saveAndContinue" class="inline-flex items-center gap-1 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60"><x-spinner wire:loading wire:target="saveAndContinue" />Save &amp; continue<i class="ri-arrow-right-line" wire:loading.remove wire:target="saveAndContinue" aria-hidden="true"></i></button>
                @else
                    <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-60"><x-spinner wire:loading wire:target="submit" />Submit questionnaire</button>
                @endif
            </div>
        </form>
    @endif

    @if ($history->isNotEmpty())
        <details class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <summary class="cursor-pointer text-sm font-semibold text-brand-900">Previous questionnaire submissions ({{ $history->count() }})</summary>
            <ul class="mt-3 divide-y divide-brand-200 text-sm">
                @foreach ($history as $response)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <span>{{ $response->submitted_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                        <span class="rounded-full bg-brand-50 px-3 py-1 text-xs text-brand-900">{{ $response->definition_version ?? 'Not available for this attempt' }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
