<div class="relative" x-data="{ open: false }">
    <button
        type="button"
        class="relative rounded-lg p-2 text-brand-900 hover:bg-brand-50"
        @click="open = !open"
        :aria-expanded="open.toString()"
        aria-haspopup="true"
        aria-label="Notifications"
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute right-1 top-1 inline-flex min-h-4 min-w-4 items-center justify-center rounded-full bg-brand-900 px-1 text-[10px] font-semibold text-white">{{ $unreadCount }}</span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        class="absolute right-0 z-20 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-brand-200 bg-white p-2 shadow-lg"
        role="menu"
    >
        <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Notifications</p>
        @forelse ($notifications as $notification)
            <button
                type="button"
                wire:click="openNotification('{{ $notification->id }}')"
                class="block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-800 hover:bg-brand-50 {{ $notification->read_at ? '' : 'font-medium' }}"
                role="menuitem"
            >
                {{ $notification->data['message'] ?? 'New notification' }}
            </button>
        @empty
            <p class="px-3 py-4 text-sm text-gray-500">No notifications yet.</p>
        @endforelse
    </div>
</div>
