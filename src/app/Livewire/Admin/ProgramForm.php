<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\College;
use App\Models\Program;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProgramForm extends Component
{
    public ?int $programId = null;

    public ?int $collegeId = null;

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
        $validated = $this->validate([
            'collegeId' => ['required', 'integer', 'exists:colleges,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('programs', 'code')->ignore($this->programId)],
        ]);

        $payload = [
            'college_id' => $validated['collegeId'],
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
        ]);
    }

    private function loadProgram(int $programId): void
    {
        $program = Program::query()->findOrFail($programId);
        $this->authorize('update', $program);
        $this->programId = $program->id;
        $this->collegeId = $program->college_id;
        $this->name = $program->name;
        $this->code = $program->code;
    }
}
