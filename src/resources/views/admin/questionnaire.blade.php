<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Questionnaire</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <livewire:admin.questionnaire-version-manager />
        <livewire:admin.questionnaire-item-form :item-id="$itemId" :key="'item-form-'.($itemId ?: 'new')" />
        <h3 class="text-base font-bold text-brand-900">Questionnaire items</h3>
        <livewire:tables.questionnaire-items-table />
    </div>
</x-app-layout>
