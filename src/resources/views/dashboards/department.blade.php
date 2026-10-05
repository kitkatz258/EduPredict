<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">Program dashboard</h2>
    </x-slot>

    <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-gray-600">Aggregated analytics for {{ $program?->name ?? 'your program' }} will appear here.</p>
    </div>
</x-app-layout>
