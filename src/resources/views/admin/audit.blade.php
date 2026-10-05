<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Audit log</h2>
        </div>
    </x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-600">Logins, account changes, adviser assignment, prediction requests, exports, and staff views of a student record are recorded here.</p>
        <livewire:tables.audit-logs-table />
    </div>
</x-app-layout>
