<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\PsocOccupation;
use App\Services\Audit\AuditLogger;
use App\Support\CommaList;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class PsocOccupationForm extends Component
{
    public bool $show = false;

    public bool $readOnly = false;

    public ?int $occupationId = null;

    public string $psocCode = '';

    public string $title = '';

    public string $majorGroup = '';

    public string $description = '';

    public string $skillTags = '';

    public string $programCodes = '';

    public string $statusMessage = '';

    public function mount(?int $occupationId = null): void
    {
        $this->authorize('create', PsocOccupation::class);
        if ($occupationId) {
            $this->loadOccupation($occupationId);
            $this->show = true;
        }
    }

    #[On('add-occupation')]
    public function startCreate(): void
    {
        $this->authorize('create', PsocOccupation::class);
        $this->reset(['occupationId', 'psocCode', 'title', 'majorGroup', 'description', 'skillTags', 'programCodes', 'statusMessage']);
        $this->readOnly = false;
        $this->resetValidation();
        $this->show = true;
    }

    #[On('edit-occupation')]
    public function startEdit(int $occupationId): void
    {
        $this->resetValidation();
        $this->statusMessage = '';
        $this->readOnly = false;
        $this->loadOccupation($occupationId);
        $this->show = true;
    }

    #[On('view-occupation')]
    public function startView(int $occupationId): void
    {
        $occupation = PsocOccupation::query()->findOrFail($occupationId);
        $this->authorize('view', $occupation);
        $this->resetValidation();
        $this->statusMessage = '';
        $this->readOnly = true;
        $this->loadOccupation($occupationId);
        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
        $this->readOnly = false;
    }

    public function save(AuditLogger $audit): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->authorize('create', PsocOccupation::class);
        $validated = $this->validate([
            'psocCode' => ['required', 'string', 'max:32', Rule::unique('psoc_occupations', 'psoc_code')->ignore($this->occupationId)],
            'title' => ['required', 'string', 'max:255'],
            'majorGroup' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'skillTags' => ['nullable', 'string', 'max:1000'],
            'programCodes' => ['nullable', 'string', 'max:500'],
        ]);

        $payload = [
            'psoc_code' => strtoupper($validated['psocCode']),
            'title' => $validated['title'],
            'major_group' => $validated['majorGroup'],
            'description' => $validated['description'] ?? '',
            'skill_tags' => CommaList::parse($validated['skillTags'] ?? ''),
            'related_program_codes' => array_map('strtoupper', CommaList::parse($validated['programCodes'] ?? '')),
        ];

        if ($this->occupationId) {
            $occupation = PsocOccupation::query()->findOrFail($this->occupationId);
            $this->authorize('update', $occupation);
            $occupation->update($payload);
            $this->statusMessage = 'Occupation updated. Stored career matches are not rewritten.';
        } else {
            $occupation = PsocOccupation::query()->create($payload);
            $this->occupationId = $occupation->id;
            $this->statusMessage = 'Occupation added.';
        }

        $this->show = true;
        $audit->record('psoc_saved', $occupation, ['psoc_code' => $occupation->psoc_code]);
        $this->dispatch('catalog-changed');
    }

    public function render(): View
    {
        $this->authorize('create', PsocOccupation::class);

        return view('livewire.admin.psoc-occupation-form');
    }

    private function loadOccupation(int $occupationId): void
    {
        $occupation = PsocOccupation::query()->findOrFail($occupationId);
        $this->authorize('update', $occupation);
        $this->occupationId = $occupation->id;
        $this->psocCode = $occupation->psoc_code;
        $this->title = $occupation->title;
        $this->majorGroup = $occupation->major_group;
        $this->description = (string) $occupation->description;
        $this->skillTags = CommaList::display($occupation->skill_tags);
        $this->programCodes = CommaList::display($occupation->related_program_codes);
    }
}
