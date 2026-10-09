<x-guest-layout>
    <h1 class="text-lg font-semibold text-brand-900">Verify your email</h1>
    <p class="mt-1 text-sm text-gray-600">Thanks for registering. Use the link we emailed you before continuing. If it did not arrive, we can send another.</p>

    @if (session('status') == 'verification-link-sent')
        <p class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900" role="status">
            A new verification link has been sent to the email address you provided during registration.
        </p>
    @endif

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button icon="ri-mail-send-line">Resend verification email</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1 text-sm font-medium text-brand-900 hover:underline">
                <i class="ri-logout-box-r-line" aria-hidden="true"></i>Log out
            </button>
        </form>
    </div>
</x-guest-layout>
