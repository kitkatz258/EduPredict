<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Your past estimates</p>
            <h2 class="text-2xl font-semibold text-brand-900">History</h2>
            <p class="mt-1 text-sm text-gray-600">Each row is a saved prediction attempt. Saved attempts are kept as they were and are not recalculated.</p>
        </div>
    </x-slot>

    <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm">
        <livewire:tables.prediction-history-table :student-id="$student->id" />
    </section>
</x-app-layout>
