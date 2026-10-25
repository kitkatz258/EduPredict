<x-app-layout>
    <div class="space-y-6">
        <x-page-header
            title="CLAS dashboard"
            :description="($college?->name ?? 'Your college').'. College totals only. Individual students are not listed here.'"
        />
        <livewire:analytics.dashboard-analytics />
    </div>
</x-app-layout>
