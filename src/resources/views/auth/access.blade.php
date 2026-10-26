@php
    $field = 'ui-field';
@endphp
<x-guest-layout>
    <h1 class="text-2xl font-bold tracking-[-0.02em] text-[#111827]">Welcome back</h1>
    <p class="mt-1 text-sm text-gray-600">Sign in with an institution-provisioned account. Students use a student number. Staff use their work email.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" novalidate>
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
                class="{{ $field }}"
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
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-gray-300 text-[#4f46e5] focus:ring-[#4f46e5]/30">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a class="font-medium text-[#4f46e5] hover:underline" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <x-primary-button class="w-full py-2.5" icon="ri-login-box-line">Sign In</x-primary-button>
    </form>
</x-guest-layout>
