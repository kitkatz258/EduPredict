<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin console</p>
            <h2 class="text-lg font-semibold text-brand-900">Academic structure</h2>
        </div>
    </x-slot>

    <div class="space-y-8">
        <p class="text-sm text-gray-600">College, department, and program stay linked. The pilot scope is the College of Liberal Arts and Sciences (CLAS). Legacy rows stay in place so students and staff keep their links. Delete is not offered.</p>
        <livewire:admin.college-form :college-id="$collegeId" :key="'college-form-'.($collegeId ?: 'new')" />
        <livewire:admin.department-form :department-id="$departmentId" :key="'department-form-'.($departmentId ?: 'new')" />
        <livewire:admin.program-form :program-id="$programId" :key="'program-form-'.($programId ?: 'new')" />
        <section class="space-y-3" aria-labelledby="colleges-heading">
            <h3 id="colleges-heading" class="text-sm font-semibold text-brand-900">Colleges</h3>
            <livewire:tables.colleges-table />
        </section>
        <section class="space-y-3" aria-labelledby="departments-heading">
            <h3 id="departments-heading" class="text-sm font-semibold text-brand-900">Departments</h3>
            <livewire:tables.departments-table />
        </section>
        <section class="space-y-3" aria-labelledby="programs-heading">
            <h3 id="programs-heading" class="text-sm font-semibold text-brand-900">Programs</h3>
            <livewire:tables.programs-table />
        </section>
    </div>
</x-app-layout>
