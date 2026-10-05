<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\College;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CollegeForm extends Component
{
    public ?int $collegeId = null;

    public string $name = '';

    public string $code = '';

    public string $statusMessage = '';

    public function mount(?int $collegeId = null): void
    {
        $this->authorize('create', College::class);
        if ($collegeId) {
            $this->loadCollege($collegeId);
        }
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('create', College::class);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('colleges', 'code')->ignore($this->collegeId)],
        ]);

        $payload = [
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
        ];

        if ($this->collegeId) {
            $college = College::query()->findOrFail($this->collegeId);
            $this->authorize('update', $college);
            $college->update($payload);
            $this->statusMessage = 'College updated.';
        } else {
            $college = College::query()->create($payload);
            $this->collegeId = $college->id;
            $this->statusMessage = 'College added.';
        }

        $audit->record('college_saved', $college, ['code' => $college->code]);
    }

    public function render(): View
    {
        $this->authorize('create', College::class);

        return view('livewire.admin.college-form');
    }

    private function loadCollege(int $collegeId): void
    {
        $college = College::query()->findOrFail($collegeId);
        $this->authorize('update', $college);
        $this->collegeId = $college->id;
        $this->name = $college->name;
        $this->code = $college->code;
    }
}
