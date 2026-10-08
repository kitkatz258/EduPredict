<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">RA 10173</p>
            <h2 class="text-lg font-semibold text-brand-900">Privacy notice</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Current consent ({{ $currentVersion }})</h3>
            <div class="mt-3 space-y-3 text-sm leading-relaxed text-gray-700">
                @foreach (($versions[$currentVersion]['paragraphs'] ?? []) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                <p>Consent version: {{ $currentVersion }}.</p>
            </div>
        </section>

        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="estimates-heading">
            <h3 id="estimates-heading" class="text-base font-semibold text-brand-900">How estimates work and their limits</h3>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-gray-700">
                <li>These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline. The current predictor is the deterministic placeholder <span class="font-medium">placeholder-heuristic-v0</span>. It is not a final trained machine-learning model.</li>
                <li>Program-shift is a rule-based qualitative indicator. It is not a trained model and it has no percentage. A program-fit label means a conversation with your department may be useful. It does not say a student should leave a program.</li>
                <li>Career matches are broad PSOC categories, not job offers. Compatibility scores and ranking are calculated by the system from program relevance, skill overlap, and academic strength. AI may only phrase an explanation of a score that was already computed.</li>
                <li>Recommended actions come only from the predefined intervention list. A rule engine selects them. AI may only rephrase an intervention that was already selected.</li>
                <li>The questionnaire is a structured self-report scale developed from supporting research. It is not a clinical or diagnostic assessment.</li>
                <li>Predictions for students with fewer than two completed semesters are marked lower confidence.</li>
            </ul>
        </section>

        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Version history</h3>
            <ul class="mt-3 space-y-3 text-sm text-gray-700">
                @foreach ($versions as $code => $version)
                    <li>
                        <span class="font-medium text-brand-900">{{ $code }}</span>
                        <span class="text-gray-500">effective {{ $version['effective_on'] ?? 'unspecified' }}</span>
                        @if ($code === $currentVersion)
                            <span class="ml-1 rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-xs text-brand-900">Current</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        @auth
            @if (auth()->user()->isRole(\App\Enums\UserRole::Student))
                <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-brand-900">Download my data</h3>
                    <p class="mt-1 text-sm text-gray-600">The file contains your account, grades, profile, questionnaire, and prediction history, including the model version stored on each request.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('privacy.download.json') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Download JSON</a>
                        <a href="{{ route('privacy.download.pdf') }}" class="rounded-lg border border-brand-200 px-4 py-2 text-sm font-medium text-brand-900">Download PDF</a>
                    </div>
                </section>
                <livewire:privacy.deletion-request-form />
            @endif
        @else
            <p class="text-sm text-gray-600">Sign in as a student to download your data or request account deletion. <a href="{{ route('login') }}" class="font-medium text-brand-900 underline">Sign in</a></p>
        @endauth
    </div>
</x-app-layout>
