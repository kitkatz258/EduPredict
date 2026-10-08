@props(['active' => 'login'])

<nav class="mb-6 grid grid-cols-2 gap-1 rounded-xl bg-brand-50 p-1 text-sm font-medium" aria-label="Account access">
    <a
        href="{{ route('login') }}"
        class="{{ $active === 'login' ? 'bg-white text-brand-900 shadow-sm' : 'text-gray-600 hover:text-brand-900' }} rounded-lg px-3 py-2 text-center"
        @if ($active === 'login') aria-current="page" @endif
    >Sign In</a>
    <a
        href="{{ route('register') }}"
        class="{{ $active === 'register' ? 'bg-white text-brand-900 shadow-sm' : 'text-gray-600 hover:text-brand-900' }} rounded-lg px-3 py-2 text-center"
        @if ($active === 'register') aria-current="page" @endif
    >Create Account</a>
</nav>
