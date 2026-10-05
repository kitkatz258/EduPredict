<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">Advisees</h2>
    </x-slot>

    <section class="space-y-4">
        <p class="text-sm text-gray-600">Latest estimates for students assigned to you. Open a row to see factors and history.</p>
        <livewire:tables.scoped-students-table />
    </section>
</x-app-layout>
