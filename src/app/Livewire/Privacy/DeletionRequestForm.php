<?php

declare(strict_types=1);

namespace App\Livewire\Privacy;

use App\Models\AccountDeletionRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DeletionRequestForm extends Component
{
    public string $reason = '';

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('create', AccountDeletionRequest::class);
    }

    public function submit(AuditLogger $audit): void
    {
        $this->authorize('create', AccountDeletionRequest::class);
        $this->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth()->user();
        $pending = AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            $this->addError('reason', 'A deletion request is already waiting for an administrator.');

            return;
        }

        $request = AccountDeletionRequest::query()->create([
            'user_id' => $user->id,
            'reason' => $this->reason !== '' ? $this->reason : null,
            'status' => 'pending',
        ]);

        $audit->record('account_deletion_requested', $request, []);
        $this->reason = '';
        $this->statusMessage = 'Your request was sent. The account stays active until an administrator processes it.';
    }

    public function render(): View
    {
        $this->authorize('create', AccountDeletionRequest::class);
        $pending = AccountDeletionRequest::query()
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->exists();

        return view('livewire.privacy.deletion-request-form', [
            'pending' => $pending,
        ]);
    }
}
