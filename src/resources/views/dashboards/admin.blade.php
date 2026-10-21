<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Administrator</p>
            <h2 class="text-lg font-semibold text-brand-900">Institution dashboard</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <livewire:analytics.dashboard-analytics />
        {{-- Student rows stay off this dashboard. Department heads open tables.scoped-students-table from Students. Eligible students have their own admin page. --}}
    </div>
</x-app-layout>
