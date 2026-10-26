<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-10 items-center justify-center gap-2 rounded-[11px] border border-[#e8eaee] bg-white px-3.5 py-2 text-sm font-semibold text-[#111827] transition hover:bg-[#f3f4f6] focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-600/15 disabled:opacity-60']) }}>
    {{ $slot }}
</button>
