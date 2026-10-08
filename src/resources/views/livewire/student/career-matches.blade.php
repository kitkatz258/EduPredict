<section id="career-matches" class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="career-matches-heading">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Career pathways</p>
            <h2 id="career-matches-heading" class="mt-1 text-lg font-semibold text-brand-900">Career matches</h2>
            <p class="mt-1 text-sm leading-6 text-gray-600">
                Ranked by a fixed rule that compares your program, saved skills, and academic snapshot with occupations from the
                <span class="font-medium text-brand-900">Philippine Standard Occupational Classification (PSOC)</span>.
                These are broad occupational categories, not job offers.
            </p>
        </div>
        <ul class="flex flex-wrap gap-x-4 gap-y-1 rounded-xl border border-brand-200 bg-brand-50 px-3 py-2 text-xs text-gray-700" aria-label="Compatibility scale">
            <li><span class="font-semibold text-brand-900">80–100</span> Strong</li>
            <li><span class="font-semibold text-brand-900">65–79</span> Good</li>
            <li><span class="font-semibold text-brand-900">50–64</span> Possible with growth</li>
            <li><span class="font-semibold text-brand-900">&lt;50</span> Longer pathway</li>
        </ul>
    </div>

    @if ($latest === null)
        <div class="mt-5 rounded-xl border border-dashed border-brand-200 px-4 py-8 text-center">
            <i class="ri-compass-3-line text-2xl text-brand-400" aria-hidden="true"></i>
            <p class="mt-2 text-sm font-medium text-brand-900">No career matches yet</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-600">Career matches are saved with each prediction. Run a prediction to see them here.</p>
        </div>
    @elseif ($matches->isEmpty())
        <div class="mt-5 rounded-xl border border-dashed border-brand-200 px-4 py-8 text-center">
            <p class="text-sm font-medium text-brand-900">No occupations to compare yet</p>
            <p class="mt-1 text-sm text-gray-600">The PSOC starter set has not been loaded.</p>
        </div>
    @else
        <p class="mt-4 text-xs text-gray-500" role="status">
            @if ($usesTemplate)
                AI unavailable, using standard text.
            @else
                AI-assisted explanations. The AI only phrases the explanation; the ranking and scores come from the fixed rule.
            @endif
        </p>

        <ol class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($matches as $index => $match)
                @php
                    $score = (int) round((float) $match->compatibility_score);
                    $band = \App\Livewire\Student\CareerMatches::band((float) $match->compatibility_score);
                @endphp
                <li wire:key="career-{{ $match->id }}" class="flex flex-col rounded-xl border border-brand-200 p-4 {{ $index === 0 ? 'md:col-span-2 xl:col-span-1' : '' }}">
                    <div class="flex items-start gap-3">
                        <div class="w-16 shrink-0">
                            <p class="text-xs font-semibold text-gray-500">#{{ $index + 1 }}</p>
                            <p class="text-2xl font-semibold text-brand-900">{{ $score }}%</p>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-brand-50" role="img" aria-label="Compatibility {{ $score }} out of 100, {{ $band }}">
                                <div class="h-full rounded-full bg-brand-400" style="width: {{ $score }}%"></div>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold leading-snug text-brand-900">{{ $match->occupation?->title }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500"><span class="rounded border border-gray-200 px-1 font-mono">PSOC {{ $match->occupation?->psoc_code }}</span> · {{ $match->occupation?->major_group }}</p>
                            <p class="mt-1 text-xs font-medium text-gray-600">{{ $band }}</p>
                        </div>
                    </div>
                    <p class="mt-3 line-clamp-3 text-sm leading-6 text-gray-700"><span class="font-medium text-brand-900">Why this match:</span> {{ $match->explanation }}</p>
                    @if (! empty($match->matched_skills))
                        <div class="mt-3 flex flex-wrap gap-1.5" aria-label="Matched skills">
                            @foreach (array_slice($match->matched_skills, 0, 3) as $skill)
                                <span class="rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-900">{{ $skill }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-auto pt-3">
                        <button
                            type="button"
                            wire:click="openDetails({{ $match->id }})"
                            wire:loading.attr="disabled"
                            wire:target="openDetails({{ $match->id }})"
                            class="inline-flex items-center gap-1 text-sm font-medium text-brand-900 hover:underline disabled:opacity-60"
                            aria-label="View career details for {{ $match->occupation?->title }}"
                        >
                            View career details
                            <i class="ri-arrow-right-s-line" aria-hidden="true" wire:loading.remove wire:target="openDetails({{ $match->id }})"></i>
                            <x-spinner wire:loading wire:target="openDetails({{ $match->id }})" />
                        </button>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($viewing)
        @php
            $score = (int) round((float) $viewing->compatibility_score);
        @endphp
        <x-dialog :title="(string) $viewing->occupation?->title" close="closeDetails" max-width="2xl" :description="'PSOC '.$viewing->occupation?->psoc_code.' · '.$viewing->occupation?->major_group">
            <div class="space-y-5 px-5 py-5 sm:px-6">
                <div class="flex items-center gap-4">
                    <p class="text-4xl font-semibold text-brand-900">{{ $score }}%</p>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-brand-900">{{ \App\Livewire\Student\CareerMatches::band((float) $viewing->compatibility_score) }}</p>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-brand-50" aria-hidden="true">
                            <div class="h-full rounded-full bg-brand-400" style="width: {{ $score }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Computed by a fixed rule (program relevance, skill-tag overlap, academic snapshot). Not a trained model.</p>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-brand-900">Why this match</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-700">{{ $viewing->explanation }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $viewing->explanation_source === 'ai' ? 'Explanation phrased by AI from de-identified data.' : 'Standard explanation text.' }}</p>
                </div>

                @if ($viewing->occupation?->description)
                    <div>
                        <h3 class="text-sm font-semibold text-brand-900">About this category</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-700">{{ $viewing->occupation->description }}</p>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold text-brand-900">Skills you already list</h3>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($viewing->matched_skills ?? [] as $skill)
                                <span class="rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-900">{{ $skill }}</span>
                            @empty
                                <p class="text-sm text-gray-500">None of this category's skill tags yet.</p>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-brand-900">Skills to build</h3>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($viewing->missing_skills ?? [] as $skill)
                                <span class="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs text-gray-600">Missing: {{ $skill }}</span>
                            @empty
                                <p class="text-sm text-gray-500">No missing tags in this category.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-500">Broad occupational categories, not job offers. Verify details against the official PSA PSOC.</p>
            </div>
            <div class="flex justify-end border-t border-brand-200 px-5 py-3 sm:px-6">
                <button type="button" wire:click="closeDetails" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50">Close</button>
            </div>
        </x-dialog>
    @endif
</section>
