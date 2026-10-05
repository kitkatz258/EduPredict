<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Program;
use App\Models\User;
use App\Services\Admin\StaffAccountService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateStaffForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $role = 'faculty';

    public ?int $college_id = null;

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
        $this->program_id = $this->program_id ?: null;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in([
                UserRole::Faculty->value,
                UserRole::DepartmentHead->value,
                UserRole::Dean->value,
                UserRole::Administrator->value,
            ])],
            'college_id' => [
                Rule::requiredIf($this->role === UserRole::Dean->value),
                'nullable',
                'integer',
                'exists:colleges,id',
            ],
            'program_id' => [
                Rule::requiredIf($this->role === UserRole::DepartmentHead->value),
                'nullable',
                'integer',
                'exists:programs,id',
            ],
        ]);

        $result = $accounts->create($validated, auth()->user(), request()->ip());
        $this->temporaryPassword = $result['temporary_password'];
        $this->reset(['name', 'email', 'college_id', 'program_id']);
        $this->role = 'faculty';
        session()->flash('success', 'Staff account created. Share the temporary password securely.');
    }

    public function render()
    {
        return view('livewire.admin.create-staff-form', [
            'colleges' => College::query()->orderBy('name')->get(),
            'programs' => Program::query()->orderBy('name')->get(),
        ]);
    }
}
