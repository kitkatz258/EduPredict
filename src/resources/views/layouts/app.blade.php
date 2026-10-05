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
    <div class="flex min-h-screen" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-30 bg-black/40 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-brand-900 text-white transition-transform lg:static lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            aria-label="Sidebar"
        >
            <div class="flex h-16 items-center gap-2 border-b border-white/10 px-5">
                <a href="{{ url('/') }}" class="text-lg font-semibold tracking-tight text-white">EduPredict</a>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto p-3 text-sm">
                @include('layouts.partials.sidebar-links')
            </nav>
            <p class="border-t border-white/10 p-4 text-xs text-white/70">These results are estimates, not guarantees.</p>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between gap-3 border-b border-brand-200 bg-white px-4">
                <button
                    type="button"
                    class="rounded-lg p-2 text-brand-900 hover:bg-brand-50 lg:hidden"
                    @click="sidebarOpen = true"
                    aria-label="Open navigation"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h1 class="truncate text-base font-semibold text-brand-900">{{ $heading ?? config('app.name') }}</h1>
                    @endisset
                </div>
                <div class="flex items-center gap-3">
                    @auth
                        <livewire:notification-bell />
                    @endauth
                    @auth
                        <span class="hidden text-sm text-gray-600 sm:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-brand-900 hover:underline">Log out</button>
                        </form>
                    @endauth
                </div>
            </header>

            @if (session('status') || session('success') || session('error'))
                <div class="px-4 pt-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)">
                    @if (session('success') || session('status'))
                        <div class="rounded-lg border border-brand-200 bg-white px-4 py-3 text-sm text-brand-900" role="status">
                            {{ session('success') ?? session('status') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="rounded-lg border border-red-200 bg-white px-4 py-3 text-sm text-red-800" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            @endif

            <main class="flex-1 p-4 sm:p-6">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
