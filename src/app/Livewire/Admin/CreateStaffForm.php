<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateStaffForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $role = 'department_head';

    public ?int $college_id = null;

    public ?int $department_id = null;

    public ?int $program_id = null;

    public ?string $temporaryPassword = null;

    public function mount(): void
    {
        $this->authorize('create', User::class);
    }

    public function save(StaffAccountService $accounts): void
    {
        $this->authorize('create', User::class);

        $this->college_id = $this->college_id ?: null;
        $this->department_id = $this->department_id ?: null;
        $this->program_id = $this->program_id ?: null;
        $isHead = $this->role === UserRole::DepartmentHead->value;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::staffAssignable()))],
            'college_id' => [
                Rule::requiredIf($this->role === UserRole::Dean->value),
                'nullable',
                'integer',
                Rule::exists('colleges', 'id')->where('is_active', true),
            ],
            'department_id' => [
                Rule::requiredIf($isHead),
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'program_id' => [
                'nullable',
                'integer',
                Rule::exists('programs', 'id')
                    ->where('is_active', true)
                    ->where('department_id', $this->department_id ?? 0),
            ],
        ], [
            'program_id.exists' => 'Choose a program inside the selected department.',
        ]);

        $result = $accounts->create($validated, auth()->user(), request()->ip());
        $this->temporaryPassword = $result['temporary_password'];
        $this->reset(['name', 'email', 'college_id', 'department_id', 'program_id']);
        $this->role = UserRole::DepartmentHead->value;
        session()->flash('success', 'Staff account created. Share the temporary password securely.');
    }

    public function render()
    {
        return view('livewire.admin.create-staff-form', [
            'roles' => UserRole::staffAssignable(),
            'colleges' => College::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->with('college')->orderBy('name')->get(),
            'programs' => Program::query()->inPilotScope()->orderBy('name')->get(),
        ]);
    }
}
