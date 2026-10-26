<x-app-layout>
    <div class="space-y-6">
        <x-page-header
            title="CLAS dashboard"
            :description="($college?->name ?? 'Your college').'. College totals only. Individual students are not listed here.'"
        >
            <x-slot:actions>
                <a href="{{ route('dean.reports') }}" class="ui-btn ui-btn-primary print:hidden">
                    <i class="ri-file-chart-line" aria-hidden="true"></i>
                    Reports
                </a>
            </x-slot:actions>
        </x-page-header>
        <livewire:analytics.dashboard-analytics />
    </div>
</x-app-layout>
