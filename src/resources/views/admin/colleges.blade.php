<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Academic structure</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">College → Department → Program → Student. The pilot scope is the College of Liberal Arts and Sciences (CLAS); programs outside it are kept as legacy records. Rows stay in place so students and staff assignments keep their links. Delete is not offered.</p>
        <div class="grid gap-6 xl:grid-cols-2">
            <livewire:admin.college-form :college-id="$collegeId" :key="'college-form-'.($collegeId ?: 'new')" />
            <livewire:admin.program-form :program-id="$programId" :key="'program-form-'.($programId ?: 'new')" />
        </div>
        <livewire:tables.colleges-table />
        <livewire:tables.programs-table />
    </div>
</x-app-layout>
