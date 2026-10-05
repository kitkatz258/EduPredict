<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department head</p>
            <h2 class="text-lg font-semibold text-brand-900">Program dashboard</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">{{ $program?->name ?? 'Your program' }}. Risk, employability, and program-shift counts stay inside this program.</p>
        <livewire:analytics.dashboard-analytics />
        <section class="space-y-3" aria-labelledby="program-students-heading">
            <h3 id="program-students-heading" class="text-sm font-semibold text-brand-900">Students</h3>
            <livewire:tables.scoped-students-table />
        </section>
    </div>
</x-app-layout>
