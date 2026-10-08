@props(['id', 'name', 'autocomplete' => 'current-password', 'invalid' => false])

<div class="relative" x-data="{ show: false }">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        :type="show ? 'text' : 'password'"
        type="password"
        autocomplete="{{ $autocomplete }}"
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 px-3 py-2.5 pr-11 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200']) }}
    >
    <button
        type="button"
        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-500 hover:text-brand-900"
        @click="show = ! show"
        :aria-label="show ? 'Hide password' : 'Show password'"
        aria-label="Show password"
        :aria-pressed="show.toString()"
    >
        <i class="ri-eye-line text-lg" x-show="! show" aria-hidden="true"></i>
        <i class="ri-eye-off-line text-lg" x-show="show" x-cloak aria-hidden="true"></i>
    </button>
</div>
