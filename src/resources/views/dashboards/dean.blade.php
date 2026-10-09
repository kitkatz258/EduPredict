<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dean</p>
            <h2 class="text-lg font-semibold text-brand-900">CLAS dashboard</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">{{ $college?->name ?? 'Your college' }}. These are college totals by department, program, year, and period. The page stays aggregate.</p>
        <livewire:analytics.dashboard-analytics />
    </div>
</x-app-layout>
