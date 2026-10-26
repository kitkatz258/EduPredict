<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Oversight</p>
            <h2 class="text-lg font-semibold text-brand-900">Prediction grants</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">Grant extra successful prediction slots for one Asia/Manila calendar date. This does not create a prediction or change stored history. The usual two-per-day limit stays in place; extras are added on top.</p>
        <livewire:admin.prediction-grant-form />
        <livewire:tables.prediction-grants-table />
    </div>
</x-app-layout>
