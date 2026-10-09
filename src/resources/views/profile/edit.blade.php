<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Account</p>
            <h2 class="text-lg font-semibold text-brand-900">Profile and settings</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
            @include('profile.partials.update-password-form')
        </div>

        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>
