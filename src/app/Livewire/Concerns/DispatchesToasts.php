<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Shows a SweetAlert toast through the `toast` browser event (resources/js/feedback.js).
 * Add/Edit success uses a centered dialog with OK instead of a corner toast.
 */
trait DispatchesToasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    protected function successDialog(string $message, string $title = 'Saved'): void
    {
        $this->dispatch('success-dialog', title: $title, message: $message);
    }
}
