<x-app-layout>
    <div class="space-y-6">
        <x-page-header
            title="Department dashboard"
            :description="($department?->name ?? 'Your department').($program ? ' · '.$program->name : '')"
        >
            <x-slot:actions>
                <a href="{{ route('department.students') }}" class="ui-btn ui-btn-secondary print:hidden">
                    <i class="ri-team-line" aria-hidden="true"></i>
                    Students
                </a>
                <a href="{{ route('department.reports') }}" class="ui-btn ui-btn-primary print:hidden">
                    <i class="ri-file-chart-line" aria-hidden="true"></i>
                    Reports
                </a>
            </x-slot:actions>
        </x-page-header>
        <livewire:analytics.dashboard-analytics />
    </div>
</x-app-layout>
