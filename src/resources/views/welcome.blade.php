<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-50 font-sans text-gray-800 antialiased">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center gap-10 px-4 py-12 lg:flex-row lg:items-center">
        <div class="flex-1">
            <p class="text-sm font-medium text-brand-900">University of Caloocan City</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight text-brand-900">EduPredict</h1>
            <p class="mt-4 max-w-xl text-gray-600">
                Advisory estimates of employability and dropout risk for faculty and department review.
                Results never decide admission, academic standing, employment, or discipline.
            </p>
        </div>
        <div class="w-full max-w-md rounded-2xl border border-brand-200 bg-white p-8 shadow-sm">
            <h2 class="text-lg font-semibold text-brand-900">Sign in</h2>
            <p class="mt-1 text-sm text-gray-500">Use your EduPredict account. Password reset mail is written to the log in development.</p>
            @auth
                <a href="{{ route('dashboard') }}" class="mt-6 inline-flex w-full justify-center rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-[#2E7D32]">Go to dashboard</a>
            @else
                <a href="{{ route('login') }}" class="mt-6 inline-flex w-full justify-center rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-[#2E7D32]">Sign in</a>
                <p class="mt-4 text-center text-sm text-gray-500">
                    <a class="font-medium text-brand-900 underline" href="{{ route('register') }}">Student registration</a>
                    ·
                    <a class="font-medium text-brand-900 underline" href="{{ route('password.request') }}">Forgot password?</a>
                </p>
            @endauth
        </div>
    </div>
</body>
</html>
