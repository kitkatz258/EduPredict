<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Deletion requests</h2>
        </div>
    </x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-600">These are account deletion requests. Approval deactivates sign-in. Grades, predictions, and history stay in the database.</p>
        <livewire:tables.deletion-requests-table />
    </div>
</x-app-layout>
