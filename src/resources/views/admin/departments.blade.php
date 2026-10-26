<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic structure</p>
            <h2 class="text-lg font-semibold text-brand-900">Departments</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">CLAS keeps at least one active department. Deactivating a department does not delete programs, students, or predictions.</p>
        <livewire:admin.department-form :department-id="$departmentId" :key="'department-form-'.($departmentId ?: 'new')" />
        <livewire:tables.departments-table />
    </div>
</x-app-layout>
