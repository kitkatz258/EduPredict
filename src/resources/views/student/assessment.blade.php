<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Student assessment</p>
            <h2 class="text-2xl font-semibold text-brand-900">Assessment</h2>
            <p class="mt-1 text-sm text-gray-600">Work through one section at a time. Saved sections stay saved, so you can return to any of them later.</p>
        </div>
    </x-slot>

    <livewire:student.assessment-wizard />
</x-app-layout>
