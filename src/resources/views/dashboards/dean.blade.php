<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">College dashboard</h2>
    </x-slot>

    <div class="space-y-6">
        <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-600">College-wide charts for {{ $college?->name ?? 'your college' }} will appear in a later milestone. The list below is limited to this college.</p>
        </section>
        <livewire:tables.scoped-students-table />
    </div>
</x-app-layout>
