<x-guest-layout>
    <h1 class="mb-2 text-lg font-semibold text-brand-900">Student registration</h1>
    <p class="mb-4 text-sm text-gray-600">You must appear on the institution student list (student number, name, and program must match).</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="student_number" value="Student number" />
            <x-text-input id="student_number" class="mt-1 block w-full" type="text" name="student_number" :value="old('student_number')" required />
            <x-input-error :messages="$errors->get('student_number')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="last_name" value="Last name" />
            <x-text-input id="last_name" class="mt-1 block w-full" type="text" name="last_name" :value="old('last_name')" required />
            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="first_name" value="First name" />
            <x-text-input id="first_name" class="mt-1 block w-full" type="text" name="first_name" :value="old('first_name')" required />
            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="program_id" value="Program" />
            <select id="program_id" name="program_id" required class="mt-1 w-full rounded-lg border-brand-200 text-sm">
                <option value="">Select program</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>{{ $program->code }} — {{ $program->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('program_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required />
        </div>

        <fieldset class="rounded-lg border border-brand-200 p-3 text-sm text-gray-700">
            <legend class="px-1 font-medium text-brand-900">Informed consent (RA 10173)</legend>
            <div class="max-h-48 overflow-y-auto space-y-2 text-xs leading-relaxed">
                <p>EduPredict collects academic records, socioeconomic information, skills/experience, and questionnaire answers to estimate employability and dropout risk and to suggest career matches and support options.</p>
                <p>Faculty see only their advisees; department heads see their program; deans see their college; administrators see institution-wide data. Predictions are advisory and do not decide admission, academic standing, employment, or discipline.</p>
                <p>When an optional AI service is used, only de-identified features or cleaned grade-row text are sent—never your name, student number, email, or birthdate. Grade-report uploads should not include pages showing more than your grades.</p>
                <p>You may access and correct your data, download a copy, or request account deletion. Consent version: {{ $consentVersion }}.</p>
            </div>
            <label class="mt-3 flex items-start gap-2">
                <input type="checkbox" name="consent" value="1" required class="mt-1">
                <span>I have read this notice and I consent to the processing of my personal data for EduPredict.</span>
            </label>
            <x-input-error :messages="$errors->get('consent')" class="mt-2" />
        </fieldset>

        <div class="flex items-center justify-end gap-3">
            <a class="text-sm text-brand-900 underline" href="{{ route('login') }}">Already registered?</a>
            <x-primary-button>Register</x-primary-button>
        </div>
    </form>
</x-guest-layout>
