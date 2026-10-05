<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">University of Caloocan City · EduPredict</p>
            <h2 class="text-lg font-semibold text-brand-900">Hi, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}. Here's your latest snapshot.</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($completeness)
            <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="dashboard-completeness">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p id="dashboard-completeness" class="text-sm text-gray-700">Profile completeness — more complete data improves prediction accuracy</p>
                    <p class="text-sm font-semibold text-brand-900">{{ $completeness['percent'] }}%</p>
                </div>
                <div
                    class="mt-3 h-2 overflow-hidden rounded-full bg-brand-50"
                    role="progressbar"
                    aria-valuenow="{{ $completeness['percent'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-labelledby="dashboard-completeness"
                >
                    <div class="h-full rounded-full bg-brand-400" style="width: {{ $completeness['percent'] }}%"></div>
                </div>
                <a href="{{ route('student.profile') }}" class="mt-3 inline-flex text-sm font-medium text-brand-900 hover:underline">Complete profile</a>
            </section>
        @endif

        <section class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-600">Employability, dropout risk, and “Request new prediction” will appear here in later milestones.</p>
            @if ($student)
                <p class="mt-4 text-sm text-gray-800">Student number: <span class="font-medium">{{ $student->student_number }}</span></p>
                @if ($academic)
                    <p class="mt-2 text-sm text-gray-800">GWA: <span class="font-medium">{{ $academic['rounded_gwa'] !== null ? number_format($academic['rounded_gwa'], 2) : '—' }}</span>
                        · Failed subjects: {{ $academic['failed_subjects'] }}
                        · Semesters completed: {{ $academic['semesters_completed'] }}
                        @if ($academic['limited_history'])
                            · <span class="text-amber-800">Lower confidence (limited academic history)</span>
                        @endif
                    </p>
                @endif
            @endif
            <p class="mt-6 text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
        </section>
    </div>
</x-app-layout>
