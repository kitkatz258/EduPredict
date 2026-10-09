<x-guest-layout>
    <h1 class="text-lg font-semibold text-brand-900">Reset password</h1>
    <p class="mt-1 text-sm text-gray-600">Choose a new password for this account.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-gray-800">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-800">Password</label>
            <x-password-input id="password" name="password" autocomplete="new-password" required :invalid="$errors->has('password')" />
            @error('password') <p id="password-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-800">Confirm password</label>
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required />
        </div>

        <x-primary-button class="w-full py-2.5" icon="ri-lock-password-line">Reset password</x-primary-button>
    </form>
</x-guest-layout>
