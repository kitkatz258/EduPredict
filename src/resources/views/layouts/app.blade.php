@php
    $user = auth()->user();
    $shell = match (true) {
        $user === null => 'guest',
        $user->isRole(\App\Enums\UserRole::Student) => 'student',
        default => 'staff',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-brand-50 font-sans text-gray-800 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-brand-900 focus:shadow">Skip to content</a>
    @include('layouts.partials.flash')

    @if ($shell === 'student')
        @include('layouts.partials.student-nav')

        <main id="main-content" class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            @isset($header)
                <div class="mb-6">{{ $header }}</div>
            @endisset
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <footer class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 px-4 pb-8 text-xs text-gray-500 sm:px-6">
            <p>These results are estimates, not guarantees.</p>
            <a href="{{ route('privacy') }}" class="font-medium text-brand-900 hover:underline">Privacy</a>
        </footer>
    @elseif ($shell === 'staff')
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div
                x-show="sidebarOpen"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-30 bg-black/40 lg:hidden"
                @click="sidebarOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                id="staff-sidebar"
                class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-brand-900 text-white transition-transform duration-200 lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                aria-label="Sidebar"
            >
                <div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-white/10 px-5">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 text-xs font-bold">UCC</span>
                        <span class="leading-tight">
                            <span class="block text-base font-semibold tracking-tight">EduPredict</span>
                            <span class="block text-[11px] uppercase tracking-wider text-white/60">{{ $user->role->label() }}</span>
                        </span>
                    </a>
                    <button type="button" class="rounded-lg p-1.5 text-white/80 hover:bg-white/10 lg:hidden" @click="sidebarOpen = false" aria-label="Close navigation">
                        <i class="ri-close-line text-xl" aria-hidden="true"></i>
                    </button>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto p-3 text-sm" aria-label="Main">
                    @include('layouts.partials.sidebar-links')
                </nav>
                <div class="shrink-0 space-y-1 border-t border-white/10 p-3 text-sm">
                    <p class="truncate px-3 pb-1 text-xs text-white/70">{{ $user->name }}</p>
                    <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'bg-white/15 font-medium' : 'hover:bg-white/10' }} flex items-center gap-3 rounded-lg px-3 py-2 text-white/90">
                        <i class="ri-settings-3-line text-lg" aria-hidden="true"></i> Account settings
                    </a>
                    <a href="{{ route('privacy') }}" class="{{ request()->routeIs('privacy') ? 'bg-white/15 font-medium' : 'hover:bg-white/10' }} flex items-center gap-3 rounded-lg px-3 py-2 text-white/90">
                        <i class="ri-shield-check-line text-lg" aria-hidden="true"></i> Privacy
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-white/90 hover:bg-white/10">
                            <i class="ri-logout-box-r-line text-lg" aria-hidden="true"></i> Log out
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-col lg:pl-64">
                <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-brand-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-brand-900 hover:bg-brand-50 lg:hidden"
                        @click="sidebarOpen = true"
                        aria-label="Open navigation"
                        aria-controls="staff-sidebar"
                        :aria-expanded="sidebarOpen.toString()"
                    >
                        <i class="ri-menu-line text-xl" aria-hidden="true"></i>
                    </button>
                    <div class="min-w-0 flex-1">
                        @isset($header)
                            {{ $header }}
                        @else
                            <h1 class="truncate text-base font-semibold text-brand-900">{{ $heading ?? config('app.name') }}</h1>
                        @endisset
                    </div>
                    <livewire:notification-bell />
                </header>

                <main id="main-content" class="flex-1 p-4 sm:p-6">
                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <header class="border-b border-brand-200 bg-white">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-brand-900">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-900 text-xs font-bold text-white">UCC</span>
                    EduPredict
                </a>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-900 hover:underline">
                    <i class="ri-login-box-line" aria-hidden="true"></i> Sign in
                </a>
            </div>
        </header>
        <main id="main-content" class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            @isset($header)
                <div class="mb-6">{{ $header }}</div>
            @endisset
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    @endif

    @livewireScripts
</body>
</html>
