<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-900">Institution students</h2></x-slot>
    <div class="space-y-6">
        <livewire:admin.institution-student-import />
        <livewire:tables.institution-students-table />
    </div>
</x-app-layout>
