<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Department head</p>
            <h2 class="text-lg font-semibold text-brand-900">Students</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">
            {{ $department?->name ?? 'Your department' }}{{ $program ? ' · '.$program->name : '' }}.
            Status opens here. This page does not include raw questionnaire answers or socioeconomic details.
        </p>
        <livewire:tables.scoped-students-table :status-modal="true" :initial-student-id="$openStudentId" />
    </div>
</x-app-layout>
