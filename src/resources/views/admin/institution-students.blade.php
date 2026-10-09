<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Eligible students</h2>
        </div>
    </x-slot>
    <div class="space-y-6">
        <p class="text-sm text-gray-600">Registration succeeds only when the student number is on this list and has not already been claimed.</p>
        <livewire:admin.institution-student-import />
        <livewire:tables.institution-students-table />
    </div>
</x-app-layout>
