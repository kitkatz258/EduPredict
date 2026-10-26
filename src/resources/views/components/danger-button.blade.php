<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-10 items-center justify-center gap-2 rounded-[11px] border border-red-700 bg-red-700 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-red-600/20 disabled:opacity-60']) }}>
    {{ $slot }}
</button>
