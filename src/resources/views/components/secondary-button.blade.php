<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center rounded-lg border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-900 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-200 focus:ring-offset-2 disabled:opacity-60']) }}>
    {{ $slot }}
</button>
