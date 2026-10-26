<x-app-layout>
    <div class="space-y-5">
        <x-page-header
            title="Institution dashboard"
            description="Cohort estimates for the college. Totals use each student's latest result."
        />
        <livewire:analytics.dashboard-analytics />
        {{-- Student rows stay off this dashboard. Department heads open tables.scoped-students-table from Students. Eligible students have their own admin page. --}}
    </div>
</x-app-layout>
