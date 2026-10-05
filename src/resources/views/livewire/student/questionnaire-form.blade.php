<div class="space-y-6">
    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Self-report</p>
        <h2 class="mt-1 text-xl font-semibold text-brand-900">Behavioral questionnaire</h2>
        <p class="mt-2 text-sm text-gray-600">Answer each statement from 1 to 5. There are no right or wrong answers. A higher score means stronger agreement after reverse-worded items are flipped.</p>
        <ol class="mt-3 flex flex-wrap gap-2 text-xs text-gray-600">
            @foreach ($likert as $value => $label)
                <li class="rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-brand-900">{{ $value }} {{ $label }}</li>
            @endforeach
        </ol>
    </section>

    @if ($statusMessage !== '')
        <p class="rounded-xl border border-brand-200 bg-white px-4 py-3 text-sm text-brand-900" role="status">{{ $statusMessage }}</p>
    @endif

    @if ($groupedItems->isEmpty())
        <p class="rounded-2xl border border-brand-200 bg-white px-5 py-8 text-center text-sm text-gray-600">No active questionnaire items are available yet.</p>
    @else
        <form wire:submit="submit" class="space-y-6">
            @foreach ($constructs as $construct => $label)
                @if ($groupedItems->has($construct))
                    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="construct-{{ $construct }}">
                        <h3 id="construct-{{ $construct }}" class="text-lg font-semibold text-brand-900">{{ $label }}</h3>
                        <div class="mt-4 space-y-5">
                            @foreach ($groupedItems[$construct] as $item)
                                <fieldset>
                                    <legend class="text-sm font-medium text-gray-800">{{ $item->text }}</legend>
                                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-5">
                                        @foreach ($likert as $value => $likertLabel)
                                            <label class="flex items-center gap-2 rounded-lg border border-brand-200 px-3 py-2 text-sm text-gray-700 hover:bg-brand-50">
                                                <input
                                                    type="radio"
                                                    name="item-{{ $item->id }}"
                                                    wire:model="answers.{{ $item->id }}"
                                                    value="{{ $value }}"
                                                    class="text-brand-900 focus:ring-brand-200"
                                                >
                                                <span>{{ $likertLabel }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('answers.'.$item->id) <p class="mt-1 text-sm text-red-700">Choose a response for this statement.</p> @enderror
                                </fieldset>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Save draft</button>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Submit questionnaire</button>
                @if (! $hasDraft && $history->isNotEmpty())
                    <button type="button" wire:click="retake" class="rounded-lg border border-brand-900 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Retake</button>
                @endif
                <span class="self-center text-sm text-gray-500" wire:loading>Saving…</span>
            </div>
        </form>
    @endif

    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="questionnaire-history">
        <h3 id="questionnaire-history" class="text-lg font-semibold text-brand-900">Submission history</h3>
        @if ($history->isEmpty())
            <p class="mt-2 text-sm text-gray-600">No submissions yet. A draft is not listed here until you submit.</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($history as $response)
                    <li class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-gray-800">
                        <p class="font-medium text-brand-900">{{ $response->submitted_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($constructs as $construct => $label)
                                <li class="rounded-full bg-white px-3 py-1 text-xs text-brand-900">
                                    {{ $label }}: {{ isset($response->construct_scores[$construct]) ? number_format($response->construct_scores[$construct], 0) : '—' }}
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="text-xs text-gray-500">Structured self-report scale developed from supporting research; not a clinical or diagnostic assessment.</p>
</div>
