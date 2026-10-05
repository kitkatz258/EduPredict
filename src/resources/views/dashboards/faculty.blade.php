<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Faculty portal</p>
            <h2 class="text-lg font-semibold text-brand-900">Advisees</h2>
        </div>
    </x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-600">Your assigned advisees only. Scores reflect each student's latest assessment.</p>

        @if ($stats['unreviewed_high'] > 0)
            <div class="flex flex-col gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between" role="status">
                <p>
                    {{ $stats['unreviewed_high'] === 1 ? '1 advisee flagged as high risk without a completed review.' : $stats['unreviewed_high'].' advisees flagged as high risk without a completed review.' }}
                </p>
                <p class="text-red-800">Use the High filter in the list below.</p>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-brand-900">{{ $stats['students'] }}</p>
                <p class="text-sm text-gray-600">Total advisees</p>
            </article>
            <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-amber-800">{{ $stats['unreviewed_high'] }}</p>
                <p class="text-sm text-gray-600">Unreviewed high risk</p>
            </article>
            <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-red-800">{{ $stats['high_risk'] }}</p>
                <p class="text-sm text-gray-600">High risk</p>
            </article>
            <article class="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-semibold text-brand-900">{{ $stats['program_concern'] }}</p>
                <p class="text-sm text-gray-600">Program-fit concern</p>
            </article>
        </div>

        <livewire:tables.scoped-students-table />
    </div>
</x-app-layout>
