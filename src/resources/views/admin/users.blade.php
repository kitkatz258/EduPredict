<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Users</h2>
        </div>
    </x-slot>
    <div class="space-y-6">
        <livewire:admin.create-staff-form />
        <livewire:admin.edit-user-form />
        <livewire:tables.users-table />
    </div>
</x-app-layout>
