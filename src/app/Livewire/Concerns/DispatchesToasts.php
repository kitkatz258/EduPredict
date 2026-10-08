<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Shows a SweetAlert toast through the `toast` browser event (resources/js/feedback.js).
 */
trait DispatchesToasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }
}
