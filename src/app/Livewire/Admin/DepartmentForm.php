<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\College;
use App\Models\Department;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class DepartmentForm extends Component
{
    public bool $show = false;

    public ?int $departmentId = null;

    public ?int $collegeId = null;

    public string $name = '';

    public string $code = '';

    public bool $isActive = true;

    public string $statusMessage = '';

    public function mount(?int $departmentId = null): void
    {
        $this->authorize('create', Department::class);
        if ($departmentId) {
            $this->loadDepartment($departmentId);
            $this->show = true;
        }
    }

    #[On('add-department')]
    public function startCreate(): void
    {
        $this->authorize('create', Department::class);
        $this->reset(['departmentId', 'collegeId', 'name', 'code', 'statusMessage']);
        $this->isActive = true;
        $this->resetValidation();
        $this->show = true;
    }

    #[On('edit-department')]
    public function startEdit(int $departmentId): void
    {
        $this->resetValidation();
        $this->statusMessage = '';
        $this->loadDepartment($departmentId);
        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('create', Department::class);
        $validated = $this->validate([
            'collegeId' => ['required', 'integer', 'exists:colleges,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('departments', 'code')->ignore($this->departmentId)],
            'isActive' => ['boolean'],
        ]);

        $payload = [
            'college_id' => $validated['collegeId'],
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => (bool) $this->isActive,
        ];

        if ($this->departmentId) {
            $department = Department::query()->findOrFail($this->departmentId);
            $this->authorize('update', $department);
            $collegeChanged = (int) $department->college_id !== (int) $payload['college_id'];
            $department->update($payload);
            if ($collegeChanged) {
                $department->programs()->update(['college_id' => $department->college_id]);
            }
            $this->statusMessage = 'Department updated.';
        } else {
            $department = Department::query()->create($payload);
            $this->departmentId = $department->id;
            $this->statusMessage = 'Department added.';
        }

        $this->show = true;
        $audit->record('department_saved', $department, ['code' => $department->code]);
        $this->dispatch('catalog-changed');
    }

    public function render(): View
    {
        $this->authorize('create', Department::class);

        return view('livewire.admin.department-form', [
            'colleges' => College::query()->orderBy('name')->get(),
        ]);
    }

    private function loadDepartment(int $departmentId): void
    {
        $department = Department::query()->findOrFail($departmentId);
        $this->authorize('update', $department);
        $this->departmentId = $department->id;
        $this->collegeId = $department->college_id;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->isActive = $department->is_active;
    }
}
