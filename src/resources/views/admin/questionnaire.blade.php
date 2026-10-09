<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Questionnaire</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">Questionnaire items here are a draft research scale. Students can answer active items. A definition that already has answers stays locked; add a new version instead of rewriting it.</p>
        <livewire:admin.questionnaire-item-form :item-id="$itemId" :key="'item-form-'.($itemId ?: 'new')" />
        <livewire:tables.questionnaire-items-table />
    </div>
</x-app-layout>
