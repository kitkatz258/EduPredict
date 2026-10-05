<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Questionnaire items</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">Seeded items are a draft research scale. Students can answer active items. Deactivate an item to hide it without deleting past answers.</p>
        <livewire:admin.questionnaire-item-form :item-id="$itemId" :key="'item-form-'.($itemId ?: 'new')" />
        <livewire:tables.questionnaire-items-table />
    </div>
</x-app-layout>
