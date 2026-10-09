<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;

class StaffAccountService
{
    /**
     * @param  array{name: string, email: string, role: string, college_id?: int|null, department_id?: int|null, program_id?: int|null}  $data
     * @return array{user: User, temporary_password: string}
     */
    public function create(array $data, User $actor, ?string $ip = null): array
    {
        $role = UserRole::from($data['role']);

        if (! in_array($role, UserRole::staffAssignable(), true)) {
            abort(422, $role === UserRole::Student
                ? 'Student accounts are created through self-registration.'
                : 'This role is no longer assignable.');
        }

        $scope = $this->scopeFor($role, $data);
        $temporary = Str::password(12);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $temporary,
            'role' => $role,
            ...$scope,
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

    /**
     * Updates an account without removing its history.
     * Students, legacy faculty, and the signed-in administrator keep their current role.
     *
     * @param  array{name: string, email: string, role?: string, college_id?: int|null, department_id?: int|null, program_id?: int|null, reset_password?: bool}  $data
     * @return array{user: User, temporary_password: ?string}
     */
    public function update(User $user, array $data, User $actor, ?string $ip = null): array
    {
        $locked = $user->is($actor) || $user->role === UserRole::Student || $user->role === UserRole::Faculty;
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! $locked) {
            $role = UserRole::from((string) ($data['role'] ?? ''));

            if (! in_array($role, UserRole::staffAssignable(), true)) {
                abort(422, 'This role is no longer assignable.');
            }

            $payload['role'] = $role;
            $payload = [...$payload, ...$this->scopeFor($role, $data)];
        }

        $temporary = null;
        if (! empty($data['reset_password']) && $user->role !== UserRole::Student) {
            $temporary = Str::password(12);
            $payload['password'] = $temporary;
            $payload['must_change_password'] = true;
        }

        $user->update($payload);

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'account_updated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'meta' => [
                'role' => $user->role->value,
                'password_reset' => $temporary !== null,
            ],
            'ip' => $ip,
        ]);

        return ['user' => $user->fresh(), 'temporary_password' => $temporary];
    }

    /**
     * Only the fields that define each role's scope are stored.
     *
     * @param  array<string, mixed>  $data
     * @return array{college_id: int|null, department_id: int|null, program_id: int|null}
     */
    private function scopeFor(UserRole $role, array $data): array
    {
        $none = ['college_id' => null, 'department_id' => null, 'program_id' => null];

        if ($role === UserRole::Dean) {
            abort_if(empty($data['college_id']), 422, 'A dean needs a college.');

            return [...$none, 'college_id' => (int) $data['college_id']];
        }

        if ($role === UserRole::DepartmentHead) {
            $department = Department::query()->findOrFail((int) ($data['department_id'] ?? 0));
            $programId = empty($data['program_id']) ? null : (int) $data['program_id'];

            abort_if(
                $programId !== null && ! $department->programs()->whereKey($programId)->exists(),
                422,
                'The program must belong to the selected department.',
            );

            return [
                'college_id' => $department->college_id,
                'department_id' => $department->id,
                'program_id' => $programId,
            ];
        }

        return $none;
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
