<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-900">Users</h2></x-slot>
    <div class="space-y-6">
        <livewire:admin.create-staff-form />
        <livewire:tables.users-table />
    </div>
</x-app-layout>
