@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : 'U';
    $detail = $user->email ?: $user->student?->student_number ?: $user->role->label();
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button
        type="button"
        class="grid h-10 w-10 place-items-center rounded-full bg-[#e0e7ff] text-sm font-bold text-[#4f46e5]"
        @click="open = ! open"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        aria-label="Account menu for {{ $user->name }}"
    >
        {{ $initials }}
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100 motion-reduce:transition-none"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute end-0 z-50 mt-2 w-[220px] rounded-xl border border-[#e8eaee] bg-white p-1.5 text-sm shadow-[0_12px_32px_rgba(16,24,40,0.12)]"
        role="menu"
    >
        <div class="px-2.5 pb-2 pt-1.5">
            <p class="truncate font-semibold text-gray-900">{{ $user->name }}</p>
            <p class="truncate text-xs text-gray-500">{{ $detail }}</p>
        </div>
        <a role="menuitem" href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-gray-900 hover:bg-[#f3f4f6]">
            <i class="ri-user-line text-base text-gray-500" aria-hidden="true"></i>
            Profile
        </a>
        <a role="menuitem" href="{{ route('profile.edit') }}#account-settings" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-gray-900 hover:bg-[#f3f4f6]">
            <i class="ri-settings-3-line text-base text-gray-500" aria-hidden="true"></i>
            Account settings
        </a>
        <button type="button" role="menuitem" data-theme-toggle class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-gray-900 hover:bg-[#f3f4f6]">
            <i class="ri-contrast-2-line text-base text-gray-500 dark:hidden" aria-hidden="true"></i>
            <i class="ri-sun-line hidden text-base text-gray-500 dark:inline" aria-hidden="true"></i>
            <span class="dark:hidden">Dark mode</span>
            <span class="hidden dark:inline">Light mode</span>
        </button>
        <div class="my-1 border-t border-[#e8eaee]"></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" role="menuitem" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left font-medium text-red-600 hover:bg-red-50">
                <i class="ri-logout-box-r-line text-base" aria-hidden="true"></i>
                Logout
            </button>
        </form>
    </div>
</div>
