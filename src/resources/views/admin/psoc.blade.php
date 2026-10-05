<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">PSOC occupations</h2>
    </x-slot>

    <div class="space-y-4">
        <p class="rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="note">
            Starter set, verify against the official PSA PSOC 2012 before final submission.
        </p>
        <livewire:tables.psoc-occupations-table />
    </div>
</x-app-layout>
