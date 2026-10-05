<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Intervention;
use App\Services\Audit\AuditLogger;
use App\Support\CommaList;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class InterventionForm extends Component
{
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
        }
    }

    public function save(AuditLogger $audit): void
    {
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

        $audit->record('intervention_saved', $intervention, ['code' => $intervention->code]);
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
