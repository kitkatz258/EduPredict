<x-guest-layout>
    <h1 class="mb-4 text-lg font-semibold text-brand-900">Set a new password</h1>
    <p class="mb-4 text-sm text-gray-600">Your administrator created this account with a temporary password. Choose a new one before continuing.</p>
    <form method="POST" action="{{ route('password.forced.update') }}">
        @csrf
        <div>
            <x-input-label for="password" value="New password" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required />
        </div>
        <div class="mt-4 flex justify-end">
            <x-primary-button>Save password</x-primary-button>
        </div>
    </form>
</x-guest-layout>
