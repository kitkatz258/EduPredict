<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-brand-900">Advisees</h2>
    </x-slot>

    <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-sm">
        @forelse ($advisees as $advisee)
            <a href="{{ route('students.show', $advisee) }}" class="block border-b border-brand-200 py-3 last:border-b-0 hover:bg-brand-50">
                <span class="font-medium text-brand-900">{{ $advisee->user->name }}</span>
                <span class="text-sm text-gray-500"> · {{ $advisee->student_number }}</span>
            </a>
        @empty
            <p class="text-sm text-gray-600">No advisees assigned yet.</p>
        @endforelse
    </div>
</x-app-layout>
