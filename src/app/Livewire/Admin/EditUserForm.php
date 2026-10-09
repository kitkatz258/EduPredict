<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class EditUserForm extends Component
{
    public bool $show = false;

    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public ?int $college_id = null;

    public ?int $department_id = null;

    public ?int $program_id = null;

    public bool $roleLocked = true;

    public bool $resetPassword = false;

    public ?string $temporaryPassword = null;

    public string $statusMessage = '';

    #[On('edit-user')]
    public function open(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);
        $this->fillFrom($user);
        $this->resetPassword = false;
        $this->temporaryPassword = null;
        $this->statusMessage = '';
        $this->resetValidation();
        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
    }

    public function save(StaffAccountService $accounts): void
    {
        $user = User::query()->findOrFail($this->userId);
        $this->authorize('update', $user);
        $this->roleLocked = $this->locksRole($user);
        $this->college_id = $this->college_id ?: null;
        $this->department_id = $this->department_id ?: null;
        $this->program_id = $this->program_id ?: null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ];

        if (! $this->roleLocked) {
            $isHead = $this->role === UserRole::DepartmentHead->value;
            $rules['role'] = ['required', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::staffAssignable()))];
            $rules['college_id'] = [
                Rule::requiredIf($this->role === UserRole::Dean->value),
                'nullable',
                'integer',
                Rule::exists('colleges', 'id')->where('is_active', true),
            ];
            $rules['department_id'] = [
                Rule::requiredIf($isHead),
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ];
            $rules['program_id'] = [
                'nullable',
                'integer',
                Rule::exists('programs', 'id')
                    ->where('is_active', true)
                    ->where('department_id', $this->department_id ?? 0),
            ];
        }

        $validated = $this->validate($rules, [
            'program_id.exists' => 'Choose a program inside the selected department.',
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'reset_password' => $this->resetPassword,
        ];

        if (! $this->roleLocked) {
            $payload['role'] = $validated['role'];
            $payload['college_id'] = $this->college_id;
            $payload['department_id'] = $this->department_id;
            $payload['program_id'] = $this->program_id;
        }

        $result = $accounts->update($user, $payload, auth()->user(), request()->ip());
        $this->fillFrom($result['user']);
        $this->temporaryPassword = $result['temporary_password'];
        $this->resetPassword = false;
        $this->statusMessage = $result['temporary_password']
            ? 'User updated. Share the temporary password securely.'
            : 'User updated.';
        $this->show = true;
        $this->dispatch('users-changed');
    }

    public function render(): View
    {
        return view('livewire.admin.edit-user-form', [
            'roles' => UserRole::staffAssignable(),
            'colleges' => College::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->with('college')->orderBy('name')->get(),
            'programs' => Program::query()->inPilotScope()->orderBy('name')->get(),
            'roleLabel' => UserRole::tryFrom($this->role)?->label() ?? $this->role,
        ]);
    }

    private function fillFrom(User $user): void
    {
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->college_id = $user->college_id;
        $this->department_id = $user->department_id;
        $this->program_id = $user->program_id;
        $this->roleLocked = $this->locksRole($user);
    }

    private function locksRole(User $user): bool
    {
        return $user->is(auth()->user())
            || $user->role === UserRole::Student
            || $user->role === UserRole::Faculty;
    }
}
