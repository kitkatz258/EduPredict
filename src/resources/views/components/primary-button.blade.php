<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-lg bg-brand-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-[#2E7D32] focus:outline-none focus:ring-2 focus:ring-brand-200 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
