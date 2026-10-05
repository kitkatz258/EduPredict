<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Administrator</p>
            <h2 class="text-lg font-semibold text-brand-900">Institution dashboard</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">Institution totals use each student's latest estimate. The chart section is aggregate; the list below names students in your scope.</p>
        <livewire:analytics.dashboard-analytics />
        <section class="space-y-3" aria-labelledby="institution-students-heading">
            <h3 id="institution-students-heading" class="text-sm font-semibold text-brand-900">Students</h3>
            <livewire:tables.scoped-students-table />
        </section>
    </div>
</x-app-layout>
