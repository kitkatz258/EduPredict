<x-app-layout>
    <div class="space-y-5">
        <x-page-header
            title="CLAS reports"
            :description="($college?->name ?? 'Your college').'. College totals only. Individual students are not listed here.'"
        />
        <livewire:staff.dean-report />
    </div>
</x-app-layout>
