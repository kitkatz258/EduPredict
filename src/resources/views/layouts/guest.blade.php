<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-[#f6f7f9] font-sans text-[#111827] antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-[11px] focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-[#111827] focus:shadow">Skip to content</a>
    @include('layouts.partials.flash')
    <div class="mx-auto flex min-h-screen w-full {{ ($wide ?? false) ? 'max-w-xl' : 'max-w-md' }} flex-col justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-6 flex flex-col items-center text-center text-[#111827]">
            <span class="grid h-12 w-12 place-items-center rounded-[14px] bg-gradient-to-br from-[#6366f1] to-[#4338ca] text-sm font-bold text-white shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]">UCC</span>
            <span class="mt-3 text-2xl font-bold tracking-[-0.02em]">EduPredict</span>
            <span class="mt-1 text-sm text-gray-500">University of Caloocan City</span>
            <span class="text-xs text-gray-400">Student Employability &amp; Dropout Prediction System</span>
        </a>
        <main id="main-content" class="rounded-[14px] border border-[#e8eaee] bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)] sm:p-8">
            {{ $slot }}
            @yield('content')
        </main>
        <p class="mt-6 text-center text-xs leading-relaxed text-gray-500">
            Protected under RA 10173, the Data Privacy Act of 2012.
            <a href="{{ route('privacy') }}" class="font-medium text-[#4f46e5] underline underline-offset-2">Privacy notice</a>
        </p>
    </div>
    @include('layouts.partials.segmented-track-script')
    @livewireScripts
</body>
</html>
