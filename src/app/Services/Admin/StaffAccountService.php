<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class StaffAccountService
{
    /**
     * @param  array{name: string, email: string, role: string, college_id?: int|null, program_id?: int|null}  $data
     * @return array{user: User, temporary_password: string}
     */
    public function create(array $data, User $actor, ?string $ip = null): array
    {
        $role = UserRole::from($data['role']);

        if ($role === UserRole::Student) {
            abort(422, 'Student accounts are created through self-registration.');
        }

        $temporary = Str::password(12);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $temporary,
            'role' => $role,
            'college_id' => $data['college_id'] ?? null,
            'program_id' => $data['program_id'] ?? null,
            'is_active' => true,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'account_created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'meta' => ['role' => $role->value],
            'ip' => $ip,
        ]);

        return ['user' => $user, 'temporary_password' => $temporary];
    }

    public function setActive(User $user, bool $active, User $actor, ?string $ip = null): void
    {
        if ($user->is($actor) && ! $active) {
            abort(403, 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => $active]);

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $active ? 'account_activated' : 'account_deactivated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'meta' => [],
            'ip' => $ip,
        ]);
    }
}
