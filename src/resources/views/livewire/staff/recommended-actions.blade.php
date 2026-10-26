<section class="rounded-[14px] border border-[#e8eaee] bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]" aria-labelledby="recommended-actions-heading">
    <h2 id="recommended-actions-heading" class="text-sm font-semibold text-brand-900">Recommended institutional actions</h2>
    <p class="mt-1 text-sm leading-6 text-gray-700">These suggestions come from the predefined intervention list. They are advisory. Department judgment is required. They do not decide admission, academic standing, employment, or discipline.</p>

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
        <p class="mt-3 text-sm text-gray-600">
            @if ($canReview)
                Select one or more recommendations, then mark them reviewed. Click a card to read the explanation. Reviewing does not change the stored risk or employability estimate.
            @else
                Stored recommendations for this estimate.
            @endif
        </p>
        <ul class="mt-4 space-y-3" role="list">
            @foreach ($actions as $action)
                @php
                    $selected = in_array($action->id, $selectedIds, true);
                    $expanded = $expandedId === $action->id;
                    $reviewed = $action->reviewed_at !== null;
                    $cardId = 'recommendation-'.$action->id;
                @endphp
                <li>
                    <article
                        class="{{ $selected ? 'border-[#4f46e5] bg-[#eef2ff]' : ($reviewed ? 'border-emerald-200 bg-emerald-50/60' : 'border-[#e8eaee] bg-white') }} rounded-[14px] border p-4 transition-colors"
                        aria-labelledby="{{ $cardId }}-title"
                    >
                        <div class="flex items-start gap-3">
                            @if ($canReview && ! $reviewed)
                                <button
                                    type="button"
                                    id="{{ $cardId }}-select"
                                    class="{{ $selected ? 'border-[#4f46e5] bg-[#4f46e5] text-white' : 'border-gray-300 bg-white text-gray-400' }} mt-1 grid h-5 w-5 shrink-0 place-items-center rounded border focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4f46e5]"
                                    wire:click="toggleSelected({{ $action->id }})"
                                    wire:key="{{ $cardId }}-select-{{ $selected ? 'on' : 'off' }}"
                                    role="checkbox"
                                    aria-checked="{{ $selected ? 'true' : 'false' }}"
                                    aria-label="Select {{ $action->intervention?->title }}"
                                >
                                    <i class="ri-check-line text-sm {{ $selected ? '' : 'opacity-0' }}" aria-hidden="true"></i>
                                </button>
                            @elseif ($reviewed)
                                <span class="mt-0.5 grid h-5 w-5 place-items-center rounded-full bg-emerald-100 text-emerald-800" aria-hidden="true">
                                    <i class="ri-check-line text-sm"></i>
                                </span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <button
                                        type="button"
                                        id="{{ $cardId }}-title"
                                        class="min-w-0 text-left text-sm font-semibold text-brand-900 hover:text-[#4338ca] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4f46e5]"
                                        wire:click="toggleExpanded({{ $action->id }})"
                                        aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                                        aria-controls="{{ $cardId }}-body"
                                    >
                                        {{ $action->intervention?->title }}
                                        <span class="ml-1 text-xs font-normal text-gray-500">{{ $expanded ? 'Hide explanation' : 'Show explanation' }}</span>
                                    </button>
                                    <x-status-pill :tone="$reviewed ? 'success' : 'warning'">
                                        {{ $reviewed ? 'Reviewed' : 'Pending review' }}
                                    </x-status-pill>
                                </div>
                                @unless ($expanded)
                                    <p class="mt-2 line-clamp-2 text-sm leading-6 text-gray-700">{{ $action->phrased_text }}</p>
                                    @if ($reviewed)
                                        <p class="mt-2 text-sm text-gray-700">
                                            Reviewed {{ $action->reviewed_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                            @if ($action->reviewer)
                                                by {{ $action->reviewer->name }}
                                            @endif.
                                        </p>
                                        @if ($action->reviewer_note)
                                            <p class="mt-1 text-sm text-gray-800">Note: {{ $action->reviewer_note }}</p>
                                        @endif
                                    @endif
                                @endunless
                                <div id="{{ $cardId }}-body" @unless ($expanded) hidden @endunless class="mt-3 space-y-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Explanation</p>
                                        <p class="mt-1 text-sm leading-6 text-gray-800">{{ $action->phrased_text }}</p>
                                    </div>
                                    @if ($action->intervention?->description)
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Catalog justification</p>
                                            <p class="mt-1 text-sm leading-6 text-gray-800">{{ $action->intervention->description }}</p>
                                        </div>
                                    @endif
                                    @if ($action->intervention?->code === 'program_fit_conversation')
                                        <p class="text-xs text-gray-600">This suggestion follows the qualitative program-shift indicator. It is not a trained model and it has no percentage.</p>
                                    @endif
                                    @if ($reviewed)
                                        <div class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-gray-700">
                                            <p>
                                                Reviewed {{ $action->reviewed_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                                @if ($action->reviewer)
                                                    by {{ $action->reviewer->name }}
                                                @endif.
                                            </p>
                                            @if ($action->reviewer_note)
                                                <p class="mt-1">Note: {{ $action->reviewer_note }}</p>
                                            @endif
                                        </div>
                                    @elseif ($canReview)
                                        <div>
                                            <label for="review-note-{{ $action->id }}" class="text-sm font-medium text-gray-700">Reviewer note (optional)</label>
                                            <textarea
                                                id="review-note-{{ $action->id }}"
                                                wire:model="notes.{{ $action->id }}"
                                                maxlength="1000"
                                                rows="2"
                                                class="ui-field mt-1 h-auto py-2"
                                            ></textarea>
                                            @error('note')
                                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>

        @if ($canReview && $unreviewedCount > 0)
            <div class="mt-4 border-t border-[#e8eaee] pt-3">
                <p class="mb-2 text-xs text-gray-500">{{ count($selectedIds) }} selected · {{ $unreviewedCount }} still pending. Marking reviewed does not change this student's risk or employability score.</p>
                <button
                    type="button"
                    wire:click="markSelectedReviewed"
                    wire:loading.attr="disabled"
                    wire:target="markSelectedReviewed,markReviewed"
                    data-confirm="This records your review for the selected recommendation(s) on this estimate. The student's stored risk and employability figures stay unchanged."
                    data-confirm-title="Mark selected recommendations as reviewed?"
                    data-confirm-button="Mark reviewed"
                    data-confirm-tone="neutral"
                    class="ui-btn ui-btn-primary"
                    @disabled(count($selectedIds) === 0)
                >
                    <x-spinner wire:loading wire:target="markSelectedReviewed,markReviewed" />
                    Mark reviewed
                </button>
            </div>
        @endif
    @endif
</section>
