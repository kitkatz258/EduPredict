<x-guest-layout>
    <h1 class="text-lg font-semibold text-brand-900">Confirm password</h1>
    <p class="mt-1 text-sm text-gray-600">This is a protected step. Enter your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-4" novalidate>
        @csrf

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-800">Password</label>
            <x-password-input id="password" name="password" autocomplete="current-password" required :invalid="$errors->has('password')" />
            @error('password') <p id="password-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </div>

        <x-primary-button class="w-full py-2.5">Confirm</x-primary-button>
    </form>
</x-guest-layout>
