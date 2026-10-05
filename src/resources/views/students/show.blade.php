<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">{{ $student->user->name }}</h2>
    </x-slot>

    <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
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
                <dt class="text-gray-500">Latest risk</dt>
                <dd class="font-medium text-gray-900">{{ $student->latestPrediction?->dropout_risk ?? 'No prediction yet' }}</dd>
            </div>
        </dl>
        <p class="mt-6 text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
    </div>
</x-app-layout>
