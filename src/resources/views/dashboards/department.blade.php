<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department head</p>
            <h2 class="text-lg font-semibold text-brand-900">Department dashboard</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                {{ $department?->name ?? 'Your department' }}{{ $program ? ' · '.$program->name : '' }}.
                These figures stay inside your assigned scope. Individual students are reviewed on the Students page.
            </p>
            <a href="{{ route('department.students') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">
                <i class="ri-team-line" aria-hidden="true"></i>
                Students
            </a>
        </div>
        <livewire:analytics.dashboard-analytics />
    </div>
</x-app-layout>
