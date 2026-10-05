<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Limits of this system</p>
            <h2 class="text-lg font-semibold text-brand-900">About and limitations</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4 text-sm leading-relaxed text-gray-700">
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Estimates, not decisions</h3>
            <p class="mt-2">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline. The current predictor is the deterministic placeholder <span class="font-medium">placeholder-heuristic-v0</span>. It is not a final trained machine-learning model.</p>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Program-shift</h3>
            <p class="mt-2">Program-shift is a rule-based qualitative indicator. It is not a trained model and it has no percentage. A program-fit label means a conversation with an adviser may be useful. It does not say a student should leave a program.</p>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Career matches</h3>
            <p class="mt-2">Career matches are broad PSOC categories, not job offers. Compatibility scores and ranking are calculated by the system from program relevance, skill overlap, and academic strength. AI may only phrase an explanation of a score that was already computed.</p>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Institutional actions</h3>
            <p class="mt-2">Recommended actions come only from the predefined intervention list. A rule engine selects them. AI may only rephrase an intervention that was already selected. Faculty and department judgment is required before anyone acts on a suggestion.</p>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Questionnaire</h3>
            <p class="mt-2">The questionnaire is a structured self-report scale developed from supporting research. It is not a clinical or diagnostic assessment.</p>
        </section>
        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-brand-900">Limited history</h3>
            <p class="mt-2">Predictions for students with fewer than two completed semesters are marked lower confidence. A shorter record makes the estimate less certain.</p>
        </section>
        <p>Personal data is handled under RA 10173. Read the <a href="{{ route('privacy') }}" class="font-medium text-brand-900 underline">privacy notice</a> for consent, access, and deletion.</p>
    </div>
</x-app-layout>
