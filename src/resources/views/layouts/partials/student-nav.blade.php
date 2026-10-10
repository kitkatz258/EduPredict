@php
    $user = auth()->user();
    $student = $user->student?->loadMissing('program');
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $items = [
        ['route' => 'student.dashboard', 'label' => 'Dashboard', 'icon' => 'ri-dashboard-line', 'active' => ['student.dashboard']],
        ['route' => 'student.assessment', 'label' => 'Assessment', 'icon' => 'ri-survey-line', 'active' => ['student.assessment', 'student.profile', 'student.questionnaire', 'student.grades']],
        ['route' => 'student.history', 'label' => 'History', 'icon' => 'ri-history-line', 'active' => ['student.history']],
    ];
@endphp

<header class="sticky top-0 z-30 border-b border-brand-200 bg-white/95 backdrop-blur" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
        <a href="{{ route('student.dashboard') }}" class="flex shrink-0 items-center gap-2 font-semibold text-brand-900">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-900 text-xs font-bold text-white">UCC</span>
            <span class="hidden sm:inline">EduPredict</span>
        </a>

        <nav class="hidden flex-1 items-center gap-1 md:flex" aria-label="Student">
            @foreach ($items as $item)
                @php $isActive = request()->routeIs(...$item['active']); @endphp
                <a
                    href="{{ route($item['route']) }}"
                    class="{{ $isActive ? 'bg-brand-50 text-brand-900' : 'text-gray-600 hover:bg-brand-50/60 hover:text-brand-900' }} inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium"
                    @if ($isActive) aria-current="page" @endif
                >
                    <i class="{{ $item['icon'] }} text-base" aria-hidden="true"></i>{{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            <livewire:notification-bell />
            <div class="hidden items-center gap-2 pl-1 lg:flex">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-900 text-sm font-semibold text-white" aria-hidden="true">{{ $initials }}</span>
                <span class="leading-tight">
                    <span class="block text-sm font-medium text-gray-900">{{ $user->name }}</span>
                    @if ($student)
                        <span class="block text-xs text-gray-500">{{ $student->program?->code }} · Year {{ $student->year_level }}</span>
                    @endif
                </span>
            </div>
            <a
                href="{{ route('profile.edit') }}"
                class="{{ request()->routeIs('profile.edit') ? 'bg-brand-50 text-brand-900' : 'text-gray-600 hover:bg-brand-50 hover:text-brand-900' }} hidden rounded-lg p-2 md:inline-flex"
                aria-label="Profile and settings"
                title="Profile and settings"
            >
                <i class="ri-settings-3-line text-xl" aria-hidden="true"></i>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="hidden md:block">
                @csrf
                <button type="submit" class="rounded-lg p-2 text-gray-600 hover:bg-brand-50 hover:text-brand-900" aria-label="Log out" title="Log out">
                    <i class="ri-logout-box-r-line text-xl" aria-hidden="true"></i>
                </button>
            </form>
            <button
                type="button"
                class="rounded-lg p-2 text-brand-900 hover:bg-brand-50 md:hidden"
                @click="open = ! open"
                :aria-expanded="open.toString()"
                aria-controls="student-mobile-nav"
                aria-label="Toggle navigation"
            >
                <i class="ri-menu-line text-xl" x-show="! open" aria-hidden="true"></i>
                <i class="ri-close-line text-xl" x-show="open" x-cloak aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <nav id="student-mobile-nav" x-show="open" x-cloak x-transition.origin.top class="border-t border-brand-200 bg-white px-4 py-3 motion-reduce:transition-none md:hidden" aria-label="Student mobile">
        <div class="mb-3 flex items-center gap-3 px-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-900 text-sm font-semibold text-white" aria-hidden="true">{{ $initials }}</span>
            <span class="leading-tight">
                <span class="block text-sm font-medium text-gray-900">{{ $user->name }}</span>
                @if ($student)
                    <span class="block text-xs text-gray-500">{{ $student->program?->code }} · Year {{ $student->year_level }}</span>
                @endif
            </span>
        </div>
        @foreach ($items as $item)
            @php $isActive = request()->routeIs(...$item['active']); @endphp
            <a href="{{ route($item['route']) }}" class="{{ $isActive ? 'bg-brand-50 text-brand-900' : 'text-gray-700' }} flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium" @if ($isActive) aria-current="page" @endif>
                <i class="{{ $item['icon'] }} text-lg" aria-hidden="true"></i>{{ $item['label'] }}
            </a>
        @endforeach
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700">
            <i class="ri-settings-3-line text-lg" aria-hidden="true"></i>Profile and settings
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-gray-700">
                <i class="ri-logout-box-r-line text-lg" aria-hidden="true"></i>Log out
            </button>
        </form>
    </nav>
</header>
