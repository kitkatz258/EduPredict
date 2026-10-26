<x-app-layout>
    <div class="space-y-6">
        <x-page-header
            title="Students"
            :description="($department?->name ?? 'Your department').($program ? ' · '.$program->name : '').'. Questionnaire answers and socioeconomic details stay off this page.'"
        />
        <livewire:tables.scoped-students-table :status-modal="true" :initial-student-id="$openStudentId" />
    </div>
</x-app-layout>
