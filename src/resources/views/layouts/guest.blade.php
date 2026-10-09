<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-gradient-to-br from-[#123d17] via-brand-900 to-[#2E7D32] font-sans text-gray-800 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-brand-900 focus:shadow">Skip to content</a>
    @include('layouts.partials.flash')
    <div class="mx-auto flex min-h-screen w-full {{ ($wide ?? false) ? 'max-w-2xl' : 'max-w-md' }} flex-col justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-6 flex flex-col items-center text-center text-white">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-white/25 bg-white/15 text-sm font-bold shadow-sm">UCC</span>
            <span class="mt-3 text-2xl font-semibold tracking-tight">EduPredict</span>
            <span class="mt-1 text-sm text-white/80">University of Caloocan City</span>
            <span class="text-xs text-white/60">Student Employability &amp; Dropout Prediction System</span>
        </a>
        <main id="main-content" class="rounded-2xl bg-white p-6 shadow-xl sm:p-8">
            {{ $slot }}
            @yield('content')
        </main>
        <p class="mt-6 text-center text-xs leading-relaxed text-white/70">
            Protected under RA 10173, the Data Privacy Act of 2012.
            <a href="{{ route('privacy') }}" class="font-medium text-white underline underline-offset-2">Privacy notice</a>
        </p>
    </div>
    @livewireScripts
</body>
</html>
