@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-brand-200 bg-white px-3 py-2 text-sm text-gray-800 shadow-sm focus:border-brand-900 focus:ring-brand-200']) }}>
