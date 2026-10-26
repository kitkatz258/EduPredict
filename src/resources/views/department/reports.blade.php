<x-app-layout>
    <div class="space-y-6">
        <x-page-header
            title="Department reports"
            :description="($department?->name ?? 'Your department').($program ? ' · '.$program->name : '').'. Authorized student summaries only. Questionnaire answers and household details stay off this page.'"
        />
        <livewire:staff.department-report />
    </div>
</x-app-layout>
