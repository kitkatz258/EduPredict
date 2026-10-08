@php
    $field = 'block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200';
@endphp
<x-guest-layout wide>
    <x-auth-tabs active="register" />

    <h1 class="text-lg font-semibold text-brand-900">Student registration</h1>
    <p class="mt-1 text-sm text-gray-600">Your student number must be on the eligible-student list kept by the administrator, and it must not be registered yet. Your name and program must match that list.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4" novalidate>
        @csrf

        <div>
            <label for="student_number" class="mb-1 block text-sm font-medium text-gray-800">Student number</label>
            <input id="student_number" name="student_number" type="text" value="{{ old('student_number') }}" required autofocus autocomplete="username" placeholder="e.g. 2024-00001" class="{{ $field }}" @error('student_number') aria-invalid="true" aria-describedby="student_number-error" @enderror>
            @error('student_number') <p id="student_number-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="last_name" class="mb-1 block text-sm font-medium text-gray-800">Last name</label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required autocomplete="family-name" class="{{ $field }}" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
                @error('last_name') <p id="last_name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="first_name" class="mb-1 block text-sm font-medium text-gray-800">First name</label>
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required autocomplete="given-name" class="{{ $field }}" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
                @error('first_name') <p id="first_name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="program_id" class="mb-1 block text-sm font-medium text-gray-800">Program</label>
            <select id="program_id" name="program_id" required class="{{ $field }}" @error('program_id') aria-invalid="true" aria-describedby="program_id-error" @enderror>
                <option value="">Select program</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>{{ $program->code }} — {{ $program->name }}</option>
                @endforeach
            </select>
            @error('program_id') <p id="program_id-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-gray-800">Personal email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" aria-describedby="email-help @error('email') email-error @enderror" class="{{ $field }}" @error('email') aria-invalid="true" @enderror>
            <p id="email-help" class="mt-1 text-xs text-gray-500">Used only for password resets. You sign in with your student number.</p>
            @error('email') <p id="email-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-gray-800">Password</label>
                <x-password-input id="password" name="password" autocomplete="new-password" required :invalid="$errors->has('password')" />
                @error('password') <p id="password-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-800">Confirm password</label>
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required />
            </div>
        </div>

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

        <x-primary-button class="w-full py-2.5" icon="ri-user-add-line">Create Account</x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        Already registered? <a class="font-medium text-brand-900 hover:underline" href="{{ route('login') }}">Sign in with your student number</a>
    </p>
</x-guest-layout>
