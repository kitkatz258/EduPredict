<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Career pathways</p>
            <h2 class="text-lg font-semibold text-brand-900">Career matches</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="max-w-3xl text-sm leading-6 text-gray-700">
            These matches use the academic snapshot, program, and skill tags already on file.
            Occupations are referenced from the Philippine Standard Occupational Classification (PSOC).
            They are broad occupational categories, not job offers.
        </p>

        <section class="rounded-2xl border border-brand-200 bg-white p-4 text-sm text-gray-700 shadow-sm" aria-label="Compatibility scale">
            <p class="font-medium text-brand-900">Compatibility scale</p>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                <li>80–100 Strong match</li>
                <li>65–79 Good match</li>
                <li>50–64 Possible with growth</li>
                <li>Below 50 Longer pathway</li>
            </ul>
            <p class="mt-2 text-xs text-gray-500">A higher percentage is a stronger alignment with the current profile. It is not a ranking of how desirable a job is, and it is not a trained model.</p>
        </section>

        @if ($usesTemplate)
            <p class="text-xs text-gray-500" role="status">AI unavailable, using standard text.</p>
        @elseif ($matches->isNotEmpty())
            <p class="text-xs font-medium text-brand-900">AI-assisted explanations</p>
        @endif

        @if ($latest === null)
            <section class="rounded-2xl border border-brand-200 bg-white p-8 text-center shadow-sm">
                <h2 class="text-lg font-semibold text-brand-900">No prediction yet</h2>
                <p class="mx-auto mt-2 max-w-lg text-sm text-gray-600">Request a prediction first. Career matches are saved with that estimate.</p>
                <a href="{{ route('student.results') }}" class="mt-4 inline-flex rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900/90">Go to results</a>
            </section>
        @elseif ($matches->isEmpty())
            <section class="rounded-2xl border border-brand-200 bg-white p-8 text-center shadow-sm">
                <h2 class="text-lg font-semibold text-brand-900">No occupations to compare yet</h2>
                <p class="mt-2 text-sm text-gray-600">The PSOC starter set has not been loaded.</p>
            </section>
        @else
            <ol class="space-y-4">
                @foreach ($matches as $index => $match)
                    @php
                        $score = (int) round((float) $match->compatibility_score);
                        $band = match (true) {
                            $score >= 80 => 'Strong match',
                            $score >= 65 => 'Good match',
                            $score >= 50 => 'Possible with growth',
                            default => 'Longer pathway',
                        };
                    @endphp
                    <li class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div class="sm:w-28">
                                <p class="text-xs font-semibold text-gray-500">#{{ $index + 1 }}</p>
                                <p class="text-3xl font-semibold text-brand-900">{{ $score }}%</p>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-brand-50" role="img" aria-label="Compatibility {{ $score }} out of 100, {{ $band }}">
                                    <div class="h-full rounded-full bg-brand-400" style="width: {{ $score }}%"></div>
                                </div>
                                <p class="mt-1 text-xs text-gray-600">{{ $band }}</p>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <h2 class="text-lg font-semibold text-brand-900">{{ $match->occupation?->title }}</h2>
                                    <span class="text-xs text-gray-500">PSOC {{ $match->occupation?->psoc_code }}</span>
                                </div>
                                <p class="text-sm text-gray-600">{{ $match->occupation?->major_group }}</p>
                                <p class="mt-3 text-sm leading-6 text-gray-700">{{ $match->explanation }}</p>
                                <div class="mt-3 flex flex-wrap gap-2" aria-label="Skill tags">
                                    @foreach ($match->matched_skills ?? [] as $skill)
                                        <span class="rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-900">{{ $skill }}</span>
                                    @endforeach
                                    @foreach ($match->missing_skills ?? [] as $skill)
                                        <span class="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs text-gray-600">Missing: {{ $skill }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        <p class="text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline. Broad occupational categories, not job offers.</p>
    </div>
</x-app-layout>
