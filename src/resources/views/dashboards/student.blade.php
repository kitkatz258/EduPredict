<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">Student dashboard</h2>
    </x-slot>

    <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-gray-600">Completeness meter, latest scores, and “Request new prediction” will appear here in later milestones.</p>
        @if ($student)
            <p class="mt-4 text-sm text-gray-800">Student number: <span class="font-medium">{{ $student->student_number }}</span></p>
        @endif
        <p class="mt-6 text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
    </div>
</x-app-layout>
