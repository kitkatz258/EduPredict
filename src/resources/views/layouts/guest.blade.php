<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="auth-split min-h-dvh font-sans text-[#111827] antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-[11px] focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-[#111827] focus:shadow">Skip to content</a>
    @include('layouts.partials.flash')

    <div class="grid min-h-dvh lg:grid-cols-2">
        <aside class="auth-art relative hidden flex-col justify-between overflow-hidden px-10 py-10 text-white lg:flex">
            <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3 text-white">
                <span class="grid h-11 w-11 place-items-center rounded-[12px] bg-white/15 text-sm font-bold">UCC</span>
                <span>
                    <span class="block text-xl font-bold tracking-[-0.02em]">EduPredict</span>
                    <span class="block text-sm text-white/70">University of Caloocan City</span>
                </span>
            </a>
            <div class="relative z-10 max-w-md">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-200">CLAS pilot</p>
                <h1 class="mt-3 text-4xl font-bold leading-tight tracking-[-0.03em]">Student employability and dropout estimates in one place.</h1>
                <p class="mt-4 text-sm leading-6 text-white/75">Institution-provisioned accounts only. Results are advisory estimates, not guarantees of employment, graduation, or program change.</p>
                <dl class="mt-8 grid grid-cols-2 gap-4 text-sm">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <dt class="text-xs uppercase tracking-wide text-white/55">Access</dt>
                        <dd class="mt-1 font-semibold">Enrolled students and staff</dd>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <dt class="text-xs uppercase tracking-wide text-white/55">Privacy</dt>
                        <dd class="mt-1 font-semibold">Protected under RA 10173</dd>
                    </div>
                </dl>
            </div>
            <p class="relative z-10 text-xs text-white/55">University of Caloocan City · College of Liberal Arts and Sciences</p>
        </aside>

        <div class="flex min-h-dvh flex-col justify-center bg-[#f6f7f9] px-4 py-10 sm:px-8">
            <a href="{{ route('home') }}" class="mb-8 flex items-center gap-3 text-[#111827] lg:hidden">
                <span class="grid h-11 w-11 place-items-center rounded-[12px] bg-gradient-to-br from-[#6366f1] to-[#4338ca] text-sm font-bold text-white">UCC</span>
                <span>
                    <span class="block text-lg font-bold tracking-[-0.02em]">EduPredict</span>
                    <span class="block text-xs text-gray-500">University of Caloocan City</span>
                </span>
            </a>
            <main id="main-content" class="mx-auto w-full max-w-md rounded-[16px] border border-[#e8eaee] bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_12px_32px_rgba(16,24,40,0.06)] sm:p-8">
                {{ $slot }}
                @yield('content')
            </main>
            <p class="mx-auto mt-6 max-w-md text-center text-xs leading-relaxed text-gray-500">
                Protected under RA 10173, the Data Privacy Act of 2012.
                <a href="{{ route('privacy') }}" class="font-medium text-[#4f46e5] underline underline-offset-2">Privacy notice</a>
            </p>
        </div>
    </div>

    @include('layouts.partials.segmented-track-script')
    @livewireScripts
</body>
</html>
