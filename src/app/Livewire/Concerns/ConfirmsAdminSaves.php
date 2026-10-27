<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Add/Edit administrator forms validate first, then ask SweetAlert2 to confirm,
 * and only persist after the administrator clicks "Yes, proceed".
 */
trait ConfirmsAdminSaves
{
    public bool $adminSaveConfirmed = false;

    public bool $adminSaveInFlight = false;

    public function confirmSave(): mixed
    {
        if ($this->adminSaveInFlight) {
            return null;
        }

        $this->adminSaveInFlight = true;
        $this->adminSaveConfirmed = true;

        try {
            return app()->call([$this, 'save']);
        } finally {
            $this->adminSaveInFlight = false;
            $this->adminSaveConfirmed = false;
        }
    }

    protected function consumeAdminSaveConfirmation(bool $isEdit): bool
    {
        if ($this->adminSaveConfirmed) {
            $this->adminSaveConfirmed = false;

            return true;
        }

        $isEditJs = $isEdit ? 'true' : 'false';
        $this->js(<<<JS
            Promise.resolve(window.confirmAdminSave({ isEdit: {$isEditJs} })).then((ok) => {
                if (ok) {
                    \$wire.confirmSave();
                }
            });
        JS);

        return false;
    }

    protected function notifyAdminSaveSuccess(bool $isEdit, string $detail = ''): void
    {
        $this->successDialog(
            $detail,
            $isEdit ? 'Changes saved successfully.' : 'Successfully added.',
        );
    }
}
