<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Questionnaire</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="rounded-2xl border border-brand-200 bg-white px-5 py-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current student instrument</p>
            <h3 class="mt-1 text-base font-bold text-brand-900">{{ $currentMeta['label'] ?? $currentVersion }}</h3>
            <p class="mt-1 font-mono text-xs text-gray-600">{{ $currentVersion }}</p>
            <p class="mt-2 max-w-prose text-sm leading-6 text-gray-600">
                Panel-approved for this capstone · not psychometrically validated · not a clinical or diagnostic assessment.
                Students starting a new assessment see this version. Historical {{ \App\Services\Questionnaire\ApprovedInstrumentCatalog::VERSION }} and draft-v1 rows stay stored and are not rewritten.
            </p>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                @foreach ($versionCounts as $version => $count)
                    @php
                        $meta = config('edupredict.questionnaire.versions.'.$version, []);
                        $status = (string) ($meta['status'] ?? 'draft');
                        $current = $version === $currentVersion;
                    @endphp
                    <div class="rounded-xl border border-brand-200 bg-brand-50/40 px-3 py-2">
                        <dt class="font-mono text-xs {{ $current ? 'font-semibold text-brand-900' : 'text-gray-600' }}">{{ $version }}{{ $current ? ' · current' : '' }}</dt>
                        <dd class="mt-1 text-gray-700">{{ $count['total'] }} items · {{ $count['active'] }} active · {{ $count['published'] }} published{{ $status === 'draft' ? ' · draft' : '' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <livewire:admin.questionnaire-item-form :item-id="$itemId" :key="'item-form-'.($itemId ?: 'new')" />
        <h3 class="text-base font-bold text-brand-900">Questionnaire items</h3>
        <livewire:tables.questionnaire-items-table />
    </div>
</x-app-layout>
