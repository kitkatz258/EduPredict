<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::Administrator);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->isRole(UserRole::Administrator);
    }
}
