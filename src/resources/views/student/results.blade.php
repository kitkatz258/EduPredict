<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">University of Caloocan City · EduPredict</p>
            <h2 class="text-lg font-semibold text-brand-900">Your estimates and history</h2>
        </div>
    </x-slot>

    @include('predictions.panel', ['showRequest' => true])
</x-app-layout>
