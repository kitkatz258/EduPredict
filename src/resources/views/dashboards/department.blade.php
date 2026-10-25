<x-app-layout>
    <div class="space-y-6">
        <x-page-header
            title="Department dashboard"
            :description="($department?->name ?? 'Your department').($program ? ' · '.$program->name : '')"
        >
            <x-slot:actions>
                <a href="{{ route('department.students') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-[11px] bg-[#111827] px-3.5 text-sm font-semibold text-white hover:bg-[#1f2937]">
                    <i class="ri-team-line" aria-hidden="true"></i>
                    Students
                </a>
            </x-slot:actions>
        </x-page-header>
        <livewire:analytics.dashboard-analytics />
    </div>
</x-app-layout>
