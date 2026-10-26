<x-app-layout>
    <div class="space-y-5">
        <x-page-header
            title="Department dashboard"
            :description="($department?->name ?? 'Your department').($program ? ' · '.$program->name : '')"
        />
        <livewire:analytics.dashboard-analytics />
    </div>
</x-app-layout>
