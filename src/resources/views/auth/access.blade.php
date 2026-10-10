@php
    $field = 'block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200';
    // Validation redirects return to the previously rendered URL, so the posted form decides which panel reopens.
    $mode = in_array(old('_auth_form'), ['login', 'register'], true) ? old('_auth_form') : $mode;
    $loginErrors = $mode === 'login' ? $errors : new \Illuminate\Support\ViewErrorBag;
    $registerErrors = $mode === 'register' ? $errors : new \Illuminate\Support\ViewErrorBag;
    $titles = ['login' => 'Sign in — '.config('app.name'), 'register' => 'Student registration — '.config('app.name')];
@endphp
<x-guest-layout wide>
    <div
        x-data="{
            mode: @js($mode),
            urls: @js(['login' => route('login'), 'register' => route('register')]),
            titles: @js($titles),
            height: null,
            init() {
                this.syncUrl('replace');
                this.$nextTick(() => this.measure());
                const observer = new ResizeObserver(() => this.measure());
                observer.observe(this.$refs.login);
                observer.observe(this.$refs.register);
                window.addEventListener('popstate', () => {
                    const next = window.location.pathname === new URL(this.urls.register).pathname ? 'register' : 'login';
                    this.show(next, false);
                });
            },
            show(next, push = true) {
                if (next === this.mode) {
                    return;
                }
                this.mode = next;
                if (push) {
                    this.syncUrl('push');
                }
                document.title = this.titles[next];
                this.$nextTick(() => this.measure());
            },
            syncUrl(how) {
                const target = this.urls[this.mode];
                if (window.location.href === target) {
                    return;
                }
                how === 'push' ? history.pushState({}, '', target) : history.replaceState({}, '', target);
            },
            measure() {
                const panel = this.$refs[this.mode];
                if (panel && panel.offsetHeight > 0) {
                    this.height = panel.offsetHeight;
                }
            },
            focusTab(next) {
                this.show(next);
                this.$nextTick(() => document.getElementById('auth-tab-' + next)?.focus());
            },
        }"
    >
        <div
            class="relative mb-6 grid grid-cols-2 rounded-xl bg-brand-50 p-1 text-sm font-medium ring-1 ring-brand-200"
            role="tablist"
            aria-label="Account access"
            x-on:keydown.right.prevent="focusTab('register')"
            x-on:keydown.left.prevent="focusTab('login')"
        >
            <span
                aria-hidden="true"
                class="{{ $mode === 'register' ? 'translate-x-full' : '' }} absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-lg bg-white shadow-sm ring-1 ring-brand-200 transition-transform duration-300 ease-out motion-reduce:transition-none"
                x-bind:class="{ 'translate-x-full': mode === 'register' }"
            ></span>
            @foreach (['login' => 'Sign In', 'register' => 'Create Account'] as $key => $label)
                <a
                    href="{{ route($key) }}"
                    id="auth-tab-{{ $key }}"
                    role="tab"
                    aria-controls="auth-panel-{{ $key }}"
                    aria-selected="{{ $mode === $key ? 'true' : 'false' }}"
                    tabindex="{{ $mode === $key ? '0' : '-1' }}"
                    x-bind:aria-selected="(mode === '{{ $key }}').toString()"
                    x-bind:tabindex="mode === '{{ $key }}' ? 0 : -1"
                    x-on:click.prevent="show('{{ $key }}')"
                    class="{{ $mode === $key ? 'text-brand-900' : 'text-gray-600' }} relative z-10 rounded-lg px-3 py-2 text-center transition-colors duration-200 hover:text-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400"
                    x-bind:class="{ 'text-brand-900': mode === '{{ $key }}', 'text-gray-600': mode !== '{{ $key }}' }"
                >{{ $label }}</a>
            @endforeach
        </div>

        <div
            class="relative overflow-hidden transition-[height] duration-300 ease-out motion-reduce:transition-none"
            x-bind:style="height !== null ? { height: height + 'px' } : {}"
        >
            <section
                id="auth-panel-login"
                x-ref="login"
                role="tabpanel"
                aria-labelledby="auth-tab-login"
                x-show="mode === 'login'"
                x-transition:enter="transition duration-300 ease-out motion-reduce:transition-none"
                x-transition:enter-start="opacity-0 -translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="absolute inset-x-0 top-0 transition duration-200 ease-in motion-reduce:transition-none"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-6"
                @if ($mode !== 'login') style="display: none" @endif
            >
                <h1 class="sr-only">Sign in</h1>
                @if ($mode === 'login')
                    <x-auth-session-status class="mb-4" :status="session('status')" />
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-4" novalidate>
                    @csrf
                    <input type="hidden" name="_auth_form" value="login">

                    <div>
                        <label for="login" class="mb-1 block text-sm font-medium text-gray-800">Student number</label>
                        <input
                            id="login"
                            name="login"
                            type="text"
                            value="{{ $mode === 'login' ? old('login') : '' }}"
                            required
                            @if ($mode === 'login') autofocus @endif
                            autocomplete="username"
                            placeholder="e.g. 2024-00001"
                            aria-describedby="login-help @if ($loginErrors->has('login')) login-error @endif"
                            @if ($loginErrors->has('login')) aria-invalid="true" @endif
                            class="{{ $field }}"
                        >
                        <p id="login-help" class="mt-1 text-xs text-gray-500">Staff accounts sign in with their work email.</p>
                        @if ($loginErrors->has('login')) <p id="login-error" class="mt-1 text-sm text-red-700">{{ $loginErrors->first('login') }}</p> @endif
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-gray-800">Password</label>
                        <x-password-input id="password" name="password" required :invalid="$loginErrors->has('password')" />
                        @if ($loginErrors->has('password')) <p id="password-error" class="mt-1 text-sm text-red-700">{{ $loginErrors->first('password') }}</p> @endif
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
            </section>

            <section
                id="auth-panel-register"
                x-ref="register"
                role="tabpanel"
                aria-labelledby="auth-tab-register"
                x-show="mode === 'register'"
                x-transition:enter="transition duration-300 ease-out motion-reduce:transition-none"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="absolute inset-x-0 top-0 transition duration-200 ease-in motion-reduce:transition-none"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-6"
                @if ($mode !== 'register') style="display: none" @endif
            >
                <h1 class="text-lg font-semibold text-brand-900">Student registration</h1>
                <p class="mt-1 text-sm text-gray-600">Your student number must be on the eligible-student list kept by the administrator, and it must not be registered yet. Your name and program must match that list.</p>

                <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4" novalidate>
                    @csrf
                    <input type="hidden" name="_auth_form" value="register">

                    <div>
                        <label for="student_number" class="mb-1 block text-sm font-medium text-gray-800">Student number</label>
                        <input id="student_number" name="student_number" type="text" value="{{ $mode === 'register' ? old('student_number') : '' }}" required @if ($mode === 'register') autofocus @endif autocomplete="username" placeholder="e.g. 2024-00001" class="{{ $field }}" @if ($registerErrors->has('student_number')) aria-invalid="true" aria-describedby="student_number-error" @endif>
                        @if ($registerErrors->has('student_number')) <p id="student_number-error" class="mt-1 text-sm text-red-700">{{ $registerErrors->first('student_number') }}</p> @endif
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="last_name" class="mb-1 block text-sm font-medium text-gray-800">Last name</label>
                            <input id="last_name" name="last_name" type="text" value="{{ $mode === 'register' ? old('last_name') : '' }}" required autocomplete="family-name" class="{{ $field }}" @if ($registerErrors->has('last_name')) aria-invalid="true" aria-describedby="last_name-error" @endif>
                            @if ($registerErrors->has('last_name')) <p id="last_name-error" class="mt-1 text-sm text-red-700">{{ $registerErrors->first('last_name') }}</p> @endif
                        </div>
                        <div>
                            <label for="first_name" class="mb-1 block text-sm font-medium text-gray-800">First name</label>
                            <input id="first_name" name="first_name" type="text" value="{{ $mode === 'register' ? old('first_name') : '' }}" required autocomplete="given-name" class="{{ $field }}" @if ($registerErrors->has('first_name')) aria-invalid="true" aria-describedby="first_name-error" @endif>
                            @if ($registerErrors->has('first_name')) <p id="first_name-error" class="mt-1 text-sm text-red-700">{{ $registerErrors->first('first_name') }}</p> @endif
                        </div>
                    </div>

                    <div>
                        <label for="program_id" class="mb-1 block text-sm font-medium text-gray-800">Program</label>
                        <select id="program_id" name="program_id" required class="{{ $field }}" @if ($registerErrors->has('program_id')) aria-invalid="true" aria-describedby="program_id-error" @endif>
                            <option value="">Select program</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected($mode === 'register' && old('program_id') == $program->id)>{{ $program->code }} — {{ $program->name }}</option>
                            @endforeach
                        </select>
                        @if ($registerErrors->has('program_id')) <p id="program_id-error" class="mt-1 text-sm text-red-700">{{ $registerErrors->first('program_id') }}</p> @endif
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-800">Personal email</label>
                        <input id="email" name="email" type="email" value="{{ $mode === 'register' ? old('email') : '' }}" required autocomplete="email" aria-describedby="email-help @if ($registerErrors->has('email')) email-error @endif" class="{{ $field }}" @if ($registerErrors->has('email')) aria-invalid="true" @endif>
                        <p id="email-help" class="mt-1 text-xs text-gray-500">Used only for password resets. You sign in with your student number.</p>
                        @if ($registerErrors->has('email')) <p id="email-error" class="mt-1 text-sm text-red-700">{{ $registerErrors->first('email') }}</p> @endif
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="register_password" class="mb-1 block text-sm font-medium text-gray-800">Password</label>
                            <x-password-input id="register_password" name="password" autocomplete="new-password" required :invalid="$registerErrors->has('password')" />
                            @if ($registerErrors->has('password')) <p id="register_password-error" class="mt-1 text-sm text-red-700">{{ $registerErrors->first('password') }}</p> @endif
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
                        @if ($registerErrors->has('consent')) <p class="mt-2 text-sm text-red-700">{{ $registerErrors->first('consent') }}</p> @endif
                    </fieldset>

                    <x-primary-button class="w-full py-2.5" icon="ri-user-add-line">Create Account</x-primary-button>
                </form>
            </section>
        </div>
    </div>
</x-guest-layout>
