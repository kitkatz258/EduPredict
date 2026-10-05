<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">Grades</h2>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600">Paste from the portal first (no AI). You can also type rows or upload a PDF/image. Uploaded files are deleted after you confirm. Do not upload pages that show more than your grades. If an optional AI fallback runs, only cleaned subject-row text is sent—never the image or your name.</p>
        <livewire:student.grade-report-form :report-id="request()->integer('report') ?: null" :key="'grade-form-'.(request()->integer('report') ?: 'new')" />
        <livewire:tables.grade-reports-table />
    </div>
</x-app-layout>
