<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic structure</p>
            <h2 class="text-lg font-semibold text-brand-900">Programs</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">Inactive programs stay outside the CLAS pilot and cannot accept new registrations. Existing students, grades, and predictions stay linked.</p>
        <livewire:admin.program-form :program-id="$programId" :key="'program-form-'.($programId ?: 'new')" />
        <livewire:tables.programs-table />
    </div>
</x-app-layout>
