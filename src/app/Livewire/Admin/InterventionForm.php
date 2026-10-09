<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Intervention;
use App\Services\Audit\AuditLogger;
use App\Support\CommaList;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class InterventionForm extends Component
{
    public bool $show = false;

    public bool $readOnly = false;

    public ?int $interventionId = null;

    public string $code = '';

    public string $title = '';

    public string $description = '';

    public string $targets = '';

    public string $minRiskLevel = 'moderate';

    public string $statusMessage = '';

    public function mount(?int $interventionId = null): void
    {
        $this->authorize('create', Intervention::class);
        if ($interventionId) {
            $this->loadIntervention($interventionId);
            $this->show = true;
        }
    }

    #[On('add-intervention')]
    public function startCreate(): void
    {
        $this->authorize('create', Intervention::class);
        $this->reset(['interventionId', 'code', 'title', 'description', 'targets', 'statusMessage']);
        $this->minRiskLevel = 'moderate';
        $this->readOnly = false;
        $this->resetValidation();
        $this->show = true;
    }

    #[On('edit-intervention')]
    public function startEdit(int $interventionId): void
    {
        $this->resetValidation();
        $this->statusMessage = '';
        $this->readOnly = false;
        $this->loadIntervention($interventionId);
        $this->show = true;
    }

    #[On('view-intervention')]
    public function startView(int $interventionId): void
    {
        $intervention = Intervention::query()->findOrFail($interventionId);
        $this->authorize('view', $intervention);
        $this->resetValidation();
        $this->statusMessage = '';
        $this->readOnly = true;
        $this->loadIntervention($interventionId);
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

        $this->authorize('create', Intervention::class);
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('interventions', 'code')->ignore($this->interventionId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'targets' => ['required', 'string', 'max:500'],
            'minRiskLevel' => ['required', 'in:low,moderate,high'],
        ]);

        $targets = array_map(function (string $token): string {
            return Str::snake(str_replace('-', '_', strtolower($token)));
        }, CommaList::parse($validated['targets']));

        $payload = [
            'code' => $validated['code'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'targets_factor' => array_values(array_unique($targets)),
            'min_risk_level' => $validated['minRiskLevel'],
        ];

        if ($this->interventionId) {
            $intervention = Intervention::query()->findOrFail($this->interventionId);
            $this->authorize('update', $intervention);
            $intervention->update($payload);
            $this->statusMessage = 'Intervention updated. Actions already stored on predictions are not rewritten.';
        } else {
            $intervention = Intervention::query()->create($payload);
            $this->interventionId = $intervention->id;
            $this->statusMessage = 'Intervention added to the predefined list.';
        }

        $this->show = true;
        $audit->record('intervention_saved', $intervention, ['code' => $intervention->code]);
        $this->dispatch('catalog-changed');
    }

    public function render(): View
    {
        $this->authorize('create', Intervention::class);

        return view('livewire.admin.intervention-form');
    }

    private function loadIntervention(int $interventionId): void
    {
        $intervention = Intervention::query()->findOrFail($interventionId);
        $this->authorize('update', $intervention);
        $this->interventionId = $intervention->id;
        $this->code = $intervention->code;
        $this->title = $intervention->title;
        $this->description = $intervention->description;
        $this->targets = CommaList::display($intervention->targets_factor);
        $this->minRiskLevel = $intervention->min_risk_level;
    }
}
