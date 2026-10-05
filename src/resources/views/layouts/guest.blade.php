<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \App\Support\PageTitle::forRequest() }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-brand-50 font-sans text-gray-800 antialiased">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-6 text-center text-2xl font-semibold text-brand-900">EduPredict</a>
        <div class="rounded-2xl border border-brand-200 bg-white p-8 shadow-sm">
            {{ $slot }}
        </div>
        @yield('content')
    </div>
    @livewireScripts
</body>
</html>
