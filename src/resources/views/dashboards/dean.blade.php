<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dean</p>
            <h2 class="text-lg font-semibold text-brand-900">College dashboard</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">{{ $college?->name ?? 'Your college' }}. Comparison cards use each student's latest estimate and stay inside this college.</p>
        <livewire:analytics.dashboard-analytics />
        <section class="space-y-3" aria-labelledby="college-students-heading">
            <h3 id="college-students-heading" class="text-sm font-semibold text-brand-900">Students</h3>
            <livewire:tables.scoped-students-table />
        </section>
    </div>
</x-app-layout>
