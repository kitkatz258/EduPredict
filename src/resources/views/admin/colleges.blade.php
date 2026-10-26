<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Academic structure</p>
            <h2 class="text-lg font-semibold text-brand-900">Colleges</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">CLAS is the pilot college. Rows stay linked, so students and history are kept. Delete is not offered.</p>
        <livewire:admin.college-form :college-id="$collegeId" :key="'college-form-'.($collegeId ?: 'new')" />
        <livewire:tables.colleges-table />
    </div>
</x-app-layout>
