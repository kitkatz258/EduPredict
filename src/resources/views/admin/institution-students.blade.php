<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Eligible students</h2>
        </div>
    </x-slot>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">A student can register only when their number is on this list and has not been claimed.</p>
            <div class="flex flex-wrap gap-2">
                <livewire:admin.institution-student-form />
                <livewire:admin.institution-student-import />
            </div>
        </div>
        <livewire:tables.institution-students-table />
    </div>
</x-app-layout>
