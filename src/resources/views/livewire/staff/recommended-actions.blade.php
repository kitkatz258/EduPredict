<section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="recommended-actions-heading">
    <h2 id="recommended-actions-heading" class="text-sm font-semibold text-brand-900">Recommended institutional actions</h2>
    <p class="mt-1 text-sm leading-6 text-gray-700">These suggestions come from the predefined intervention list. They are advisory. Faculty or department judgment is required. They do not decide admission, academic standing, employment, or discipline.</p>

    @if ($usesFallback)
        <p class="mt-3 text-xs text-gray-500">AI unavailable, using standard text.</p>
    @endif

    @if ($actions->isEmpty())
        <p class="mt-4 text-sm text-gray-600">
            @if ($hasPrediction && in_array($risk, ['moderate', 'high'], true))
                No institutional action matched this estimate.
            @else
                No institutional actions were suggested for this estimate.
            @endif
        </p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($actions as $action)
                <li class="rounded-xl border border-brand-200 bg-brand-50 p-4">
                    <h3 class="text-sm font-semibold text-brand-900">{{ $action->intervention?->title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-800">{{ $action->phrased_text }}</p>
                    @if ($action->intervention?->code === 'program_fit_conversation')
                        <p class="mt-2 text-xs text-gray-600">This suggestion follows the qualitative program-shift indicator. It is not a trained model and it has no percentage.</p>
                    @endif
                    @if ($action->reviewed_at)
                        <p class="mt-3 text-sm text-gray-700">
                            Reviewed {{ $action->reviewed_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                            @if ($action->reviewer)
                                by {{ $action->reviewer->name }}
                            @endif.
                        </p>
                    @endif
                    @if ($action->reviewer_note)
                        <p class="mt-2 text-sm text-gray-800">Note: {{ $action->reviewer_note }}</p>
                    @endif
                    @if ($canReview)
                        <div class="mt-3">
                            <label for="review-note-{{ $action->id }}" class="text-sm font-medium text-gray-700">Reviewer note</label>
                            <textarea
                                id="review-note-{{ $action->id }}"
                                wire:model="notes.{{ $action->id }}"
                                maxlength="1000"
                                rows="2"
                                class="mt-1 w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200"
                            ></textarea>
                            @error('note')
                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                            <button
                                type="button"
                                wire:click="markReviewed({{ $action->id }})"
                                class="mt-2 rounded-lg bg-brand-900 px-3 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]"
                            >
                                Mark reviewed
                            </button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
