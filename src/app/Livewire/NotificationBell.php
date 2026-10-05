<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public function openNotification(string $id): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $target = (string) ($notification->data['url'] ?? '');
        if (! str_starts_with($target, url('/'))) {
            $target = route('dashboard');
        }

        $this->redirect($target);
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user, 403);

        return view('livewire.notification-bell', [
            'notifications' => $user->notifications()->latest()->limit(8)->get(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
