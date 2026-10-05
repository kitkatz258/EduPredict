<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(string $action, ?Model $subject = null, array $meta = [], ?User $actor = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor?->id ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'ip' => request()->ip(),
        ]);
    }
}
