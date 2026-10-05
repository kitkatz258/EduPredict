<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $student->program?->name }}</p>
            <h2 class="text-lg font-semibold text-brand-900">{{ $student->user->name }}</h2>
        </div>
    </x-slot>

    <div class="mb-6 rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-gray-500">Student number</dt>
                <dd class="font-medium text-gray-900">{{ $student->student_number }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Program</dt>
                <dd class="font-medium text-gray-900">{{ $student->program->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Year level</dt>
                <dd class="font-medium text-gray-900">{{ $student->year_level }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Adviser</dt>
                <dd class="font-medium text-gray-900">{{ $student->adviser?->name ?? 'Unassigned' }}</dd>
            </div>
        </dl>
    </div>

    @include('predictions.panel', ['showRequest' => false])

    @unless ($forStudent)
        <div class="mt-6">
            <livewire:staff.recommended-actions :student-id="$student->id" />
        </div>
    @endunless
</x-app-layout>
