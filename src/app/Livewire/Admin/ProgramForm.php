<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProgramForm extends Component
{
    public ?int $programId = null;

    public ?int $collegeId = null;

    public ?int $departmentId = null;

    public string $name = '';

    public string $code = '';

    public string $statusMessage = '';

    public function mount(?int $programId = null): void
    {
        $this->authorize('create', Program::class);
        if ($programId) {
            $this->loadProgram($programId);
        }
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('create', Program::class);
        $this->departmentId = $this->departmentId ?: null;
        $validated = $this->validate([
            'collegeId' => ['required', 'integer', 'exists:colleges,id'],
            'departmentId' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('college_id', $this->collegeId ?? 0),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('programs', 'code')->ignore($this->programId)],
        ], [
            'departmentId.exists' => 'Choose a department inside the selected college.',
        ]);

        $payload = [
            'college_id' => $validated['collegeId'],
            'department_id' => $validated['departmentId'],
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
        ];

        if ($this->programId) {
            $program = Program::query()->findOrFail($this->programId);
            $this->authorize('update', $program);
            $program->update($payload);
            $this->statusMessage = 'Program updated.';
        } else {
            $program = Program::query()->create($payload);
            $this->programId = $program->id;
            $this->statusMessage = 'Program added.';
        }

        $audit->record('program_saved', $program, ['code' => $program->code]);
    }

    public function render(): View
    {
        $this->authorize('create', Program::class);

        return view('livewire.admin.program-form', [
            'colleges' => College::query()->orderBy('name')->get(),
            'departments' => Department::query()->where('college_id', $this->collegeId ?? 0)->orderBy('name')->get(),
        ]);
    }

    private function loadProgram(int $programId): void
    {
        $program = Program::query()->findOrFail($programId);
        $this->authorize('update', $program);
        $this->programId = $program->id;
        $this->collegeId = $program->college_id;
        $this->departmentId = $program->department_id;
        $this->name = $program->name;
        $this->code = $program->code;
    }
}
