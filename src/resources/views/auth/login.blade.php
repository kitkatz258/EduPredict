<x-guest-layout>
    <x-auth-tabs active="login" />

    <h1 class="sr-only">Sign in</h1>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4" novalidate>
        @csrf

        <div>
            <label for="login" class="mb-1 block text-sm font-medium text-gray-800">Student number</label>
            <input
                id="login"
                name="login"
                type="text"
                value="{{ old('login') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="e.g. 2024-00001"
                aria-describedby="login-help @error('login') login-error @enderror"
                @error('login') aria-invalid="true" @enderror
                class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200"
            >
            <p id="login-help" class="mt-1 text-xs text-gray-500">Staff accounts sign in with their work email.</p>
            @error('login') <p id="login-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-800">Password</label>
            <x-password-input id="password" name="password" required :invalid="$errors->has('password')" />
            @error('password') <p id="password-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between gap-3 text-sm">
            <label for="remember_me" class="inline-flex items-center gap-2 text-gray-600">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-gray-300 text-brand-900 focus:ring-brand-200">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a class="font-medium text-brand-900 hover:underline" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <x-primary-button class="w-full py-2.5" icon="ri-login-box-line">Sign In</x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        New student? <a href="{{ route('register') }}" class="font-medium text-brand-900 hover:underline">Create an account</a> with the student number on the eligible list.
    </p>
</x-guest-layout>
