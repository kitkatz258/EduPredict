<x-guest-layout>
    <h1 class="text-lg font-semibold text-brand-900">Forgot password</h1>
    <p class="mt-1 text-sm text-gray-600">Enter the email on your account. We will send a link to choose a new password. Students still sign in with a student number after the reset.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4" novalidate>
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-gray-800">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </div>

        <x-primary-button class="w-full py-2.5" icon="ri-mail-send-line">Email password reset link</x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        <a href="{{ route('login') }}" class="font-medium text-brand-900 hover:underline">Back to sign in</a>
    </p>
</x-guest-layout>
