<x-guest-layout>
    <h1 class="text-lg font-semibold text-brand-900">Set a new password</h1>
    <p class="mt-1 text-sm text-gray-600">Your administrator created this account with a temporary password. Choose a new one before continuing.</p>

    <form method="POST" action="{{ route('password.forced.update') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-800">New password</label>
            <x-password-input id="password" name="password" autocomplete="new-password" required :invalid="$errors->has('password')" />
            @error('password') <p id="password-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-800">Confirm password</label>
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required />
        </div>
        <x-primary-button class="w-full py-2.5" icon="ri-lock-password-line">Save password</x-primary-button>
    </form>
</x-guest-layout>
