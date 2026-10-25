@php
    $user = auth()->user();
    $shell = match (true) {
        $user === null => 'guest',
        $user->isRole(\App\Enums\UserRole::Student) => 'student',
        default => 'staff',
    };
    $initials = $user
        ? collect(preg_split('/\s+/', trim((string) $user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('')
        : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-[#f6f7f9] font-sans text-[#111827] antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-[11px] focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-[#111827] focus:shadow">Skip to content</a>
    @include('layouts.partials.flash')

    @if ($shell === 'student')
        @include('layouts.partials.student-nav')

        <main id="main-content" class="mx-auto w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            @isset($header)
                <div class="mb-6">{{ $header }}</div>
            @endisset
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <footer class="mx-auto flex w-full flex-wrap items-center justify-between gap-2 px-4 pb-8 text-xs text-gray-500 sm:px-6 lg:px-8">
            <p>These results are estimates, not guarantees.</p>
            <a href="{{ route('privacy') }}" class="font-medium text-[#4f46e5] hover:underline">Privacy</a>
        </footer>
    @elseif ($shell === 'staff')
        <div
            x-data="{
                sidebarOpen: false,
                collapsed: document.documentElement.classList.contains('sidebar-collapsed'),
                toggleCollapsed() {
                    this.collapsed = ! this.collapsed;
                    document.documentElement.classList.toggle('sidebar-collapsed', this.collapsed);
                    try {
                        localStorage.setItem('edupredict.sidebar', this.collapsed ? 'collapsed' : 'expanded');
                    } catch (e) {}
                },
            }"
            @keydown.escape.window="sidebarOpen = false"
        >
            <div
                x-show="sidebarOpen"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-30 bg-black/45 motion-reduce:transition-none lg:hidden"
                @click="sidebarOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                id="staff-sidebar"
                class="fixed inset-y-0 left-0 z-40 flex h-dvh -translate-x-full flex-col overflow-y-auto overscroll-contain bg-[#0f1115] text-[#a3a9b5] motion-reduce:transition-none lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                x-init="
                    const applySidebar = () => {
                        const desktop = window.matchMedia('(min-width: 1024px)').matches;
                        $el.inert = ! desktop && ! sidebarOpen;
                    };
                    applySidebar();
                    window.matchMedia('(min-width: 1024px)').addEventListener('change', applySidebar);
                    $watch('sidebarOpen', applySidebar);
                "
                aria-label="Sidebar"
            >
                <div class="flex min-h-full w-full flex-1 flex-col">
                    <div class="sb-top sticky top-0 z-10 flex items-center gap-2.5 bg-[#0f1115] px-[18px] pb-3 pt-5">
                        <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center gap-2.5 text-white">
                            <span class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-[10px] bg-gradient-to-br from-[#6366f1] to-[#4338ca] text-[11px] font-bold text-white">UCC</span>
                            <span class="sb-brand-text min-w-0">
                                <span class="block truncate text-lg font-bold tracking-[-0.02em]">EduPredict</span>
                            </span>
                        </a>
                        <button
                            type="button"
                            class="hidden h-8 w-8 place-items-center rounded-lg text-[#7d8492] hover:bg-white/10 hover:text-white lg:grid"
                            @click="toggleCollapsed()"
                            :aria-expanded="(! collapsed).toString()"
                            :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        >
                            <i class="ri-sidebar-fold-line text-lg" x-show="! collapsed" aria-hidden="true"></i>
                            <i class="ri-sidebar-unfold-line text-lg" x-show="collapsed" x-cloak aria-hidden="true"></i>
                        </button>
                        <button type="button" class="rounded-lg p-1.5 text-white/80 hover:bg-white/10 lg:hidden" @click="sidebarOpen = false" aria-label="Close navigation">
                            <i class="ri-close-line text-xl" aria-hidden="true"></i>
                        </button>
                    </div>

                    <nav class="px-3 pb-3 text-sm" aria-label="Main">
                        @include('layouts.partials.sidebar-links')
                    </nav>

                    <div class="mt-auto space-y-1 border-t border-white/[0.08] px-3 py-3 text-sm">
                        <div class="sb-account flex items-center gap-2.5 rounded-[10px] bg-white/[0.06] px-2.5 py-2 text-white">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/15 text-[11px] font-bold">{{ $initials !== '' ? $initials : 'U' }}</span>
                            <span class="sb-account-copy min-w-0">
                                <span class="block truncate text-sm font-semibold">{{ $user->name }}</span>
                                <span class="block truncate text-[11px] text-[#7d8492]">{{ $user->role->label() }}</span>
                            </span>
                        </div>
                        <a
                            href="{{ route('privacy') }}"
                            title="Privacy"
                            class="sb-link {{ request()->routeIs('privacy') ? 'bg-white/10 font-medium text-white' : 'text-[#a3a9b5] hover:bg-white/[0.06] hover:text-white' }} relative flex items-center gap-3 rounded-[10px] px-2.5 py-[9px]"
                            @if (request()->routeIs('privacy')) aria-current="page" @endif
                        >
                            <i class="ri-shield-check-line text-lg leading-none" aria-hidden="true"></i>
                            <span class="sb-label truncate">Privacy</span>
                        </a>
                    </div>
                </div>
            </aside>

            <div id="staff-main" class="flex min-h-screen min-w-0 flex-col lg:pl-[264px]">
                <header class="sticky top-0 z-20 flex h-[68px] items-center gap-3 border-b border-[#e8eaee] bg-white px-4 sm:px-7">
                    <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-[11px] border border-[#e8eaee] text-[#111827] hover:bg-[#f3f4f6] lg:hidden"
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
                        @endisset
                    </div>
                    <x-user-menu />
                </header>

                <main id="main-content" class="flex-1 px-4 py-6 sm:px-6 lg:px-7 lg:py-7">
                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <header class="sticky top-0 z-20 border-b border-[#e8eaee] bg-white">
            <div class="mx-auto flex h-[68px] max-w-6xl items-center justify-between px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold text-[#111827]">
                    <span class="grid h-[34px] w-[34px] place-items-center rounded-[10px] bg-gradient-to-br from-[#6366f1] to-[#4338ca] text-[11px] font-bold text-white">UCC</span>
                    EduPredict
                </a>
                <a href="{{ route('login') }}" class="inline-flex h-10 items-center gap-2 rounded-[11px] bg-[#111827] px-3.5 text-sm font-semibold text-white hover:bg-[#1f2937]">
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

    @include('layouts.partials.segmented-track-script')
    @livewireScripts
</body>
</html>
