@php
    $user = auth()->user();
    $items = [
        ['route' => 'student.dashboard', 'label' => 'Dashboard', 'icon' => 'ri-dashboard-line', 'active' => ['student.dashboard']],
        ['route' => 'student.assessment', 'label' => 'Assessment', 'icon' => 'ri-survey-line', 'active' => ['student.assessment', 'student.profile', 'student.questionnaire', 'student.grades']],
        ['route' => 'student.history', 'label' => 'History', 'icon' => 'ri-history-line', 'active' => ['student.history']],
    ];
@endphp

<header class="sticky top-0 z-30 border-b border-[#e8eaee] bg-white" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="mx-auto flex h-[68px] w-full items-center gap-3 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('student.dashboard') }}" class="flex shrink-0 items-center gap-2.5 font-semibold text-[#111827]">
            <span class="grid h-[34px] w-[34px] place-items-center rounded-[10px] bg-gradient-to-br from-[#6366f1] to-[#4338ca] text-[11px] font-bold text-white">UCC</span>
            <span class="hidden sm:inline">EduPredict</span>
        </a>

        <nav class="hidden flex-1 items-center gap-1 md:flex" aria-label="Student">
            @foreach ($items as $item)
                @php $isActive = request()->routeIs(...$item['active']); @endphp
                <a
                    href="{{ route($item['route']) }}"
                    class="{{ $isActive ? 'bg-[#eef2ff] text-[#4338ca]' : 'text-gray-600 hover:bg-[#f3f4f6] hover:text-[#111827]' }} inline-flex items-center gap-2 rounded-[10px] px-3 py-2 text-sm font-medium"
                    @if ($isActive) aria-current="page" @endif
                >
                    <i class="{{ $item['icon'] }} text-base" aria-hidden="true"></i>{{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <x-user-menu />
            <button
                type="button"
                class="grid h-10 w-10 place-items-center rounded-[11px] border border-[#e8eaee] text-[#111827] hover:bg-[#f3f4f6] md:hidden"
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

    <nav id="student-mobile-nav" x-show="open" x-cloak x-transition.origin.top class="border-t border-[#e8eaee] bg-white px-4 py-3 motion-reduce:transition-none md:hidden" aria-label="Student mobile">
        <p class="mb-2 truncate px-3 text-sm font-semibold text-gray-900">{{ $user->name }}</p>
        @foreach ($items as $item)
            @php $isActive = request()->routeIs(...$item['active']); @endphp
            <a href="{{ route($item['route']) }}" class="{{ $isActive ? 'bg-[#eef2ff] text-[#4338ca]' : 'text-gray-700' }} flex items-center gap-3 rounded-[10px] px-3 py-2 text-sm font-medium" @if ($isActive) aria-current="page" @endif>
                <i class="{{ $item['icon'] }} text-lg" aria-hidden="true"></i>{{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</header>
