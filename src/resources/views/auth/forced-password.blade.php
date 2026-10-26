<x-guest-layout>
    <h1 class="text-2xl font-bold tracking-[-0.02em] text-[#111827]">Set a new password</h1>
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
        @if ($needsConsent)
            <fieldset class="rounded-xl border border-brand-200 bg-brand-50/40 p-4 text-sm text-gray-700">
                <legend class="px-1 font-medium text-brand-900">Informed consent (RA 10173)</legend>
                <div class="max-h-44 space-y-2 overflow-y-auto pr-1 text-xs leading-relaxed" tabindex="0" aria-label="Consent notice">
                    @foreach (config('edupredict.consent.versions.'.$consentVersion.'.paragraphs', []) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                    <p>Consent version: {{ $consentVersion }}. <a href="{{ route('privacy') }}" class="text-brand-900 underline">Read the full notice</a>.</p>
                </div>
                <label class="mt-3 flex items-start gap-2">
                    <input type="checkbox" name="consent" value="1" required class="mt-0.5 rounded border-gray-300 text-brand-900 focus:ring-brand-200">
                    <span>I have read this notice and I consent to the processing of my personal data for EduPredict.</span>
                </label>
                @error('consent') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </fieldset>
        @endif
        <x-primary-button class="w-full py-2.5" icon="ri-lock-password-line">Save password</x-primary-button>
    </form>
</x-guest-layout>
