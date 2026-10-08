<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department head</p>
            <h2 class="text-lg font-semibold text-brand-900">Department dashboard</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">
            {{ $department?->name ?? 'Your department' }}{{ $program ? ' · '.$program->name : '' }}.
            Risk, employability, and program-shift figures stay inside your assigned scope.
        </p>
        <livewire:analytics.dashboard-analytics />
        <section class="space-y-3" aria-labelledby="department-students-heading">
            <h3 id="department-students-heading" class="text-sm font-semibold text-brand-900">Students</h3>
            <livewire:tables.scoped-students-table />
        </section>
    </div>
</x-app-layout>
