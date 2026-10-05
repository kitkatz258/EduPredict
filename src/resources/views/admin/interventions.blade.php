<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Interventions</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">Recommended actions can only come from this list. The rule engine selects them. AI may rephrase a chosen item and cannot add a new one.</p>
        <livewire:admin.intervention-form :intervention-id="$interventionId" :key="'intervention-form-'.($interventionId ?: 'new')" />
        <livewire:tables.interventions-table />
    </div>
</x-app-layout>
