<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\PsocOccupation;
use App\Services\Audit\AuditLogger;
use App\Support\PsocCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class PsocOccupationForm extends Component
{
    use DispatchesToasts;

    public bool $show = false;

    public bool $readOnly = false;

    public ?int $occupationId = null;

    public string $psocCode = '';

    public string $title = '';

    public string $majorGroup = '';

    public string $description = '';

    /** @var list<string> */
    public array $selectedSkills = [];

    /** @var list<string> */
    public array $selectedPrograms = [];

    public string $newSkillTag = '';

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
        $this->reset([
            'occupationId', 'psocCode', 'title', 'majorGroup', 'description',
            'selectedSkills', 'selectedPrograms', 'newSkillTag', 'statusMessage',
        ]);
        $this->readOnly = false;
        $this->resetValidation();
        $this->show = true;
    }

    #[On('edit-occupation')]
    public function startEdit(int $occupationId): void
    {
        $this->resetValidation();
        $this->statusMessage = '';
        $this->newSkillTag = '';
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
        $this->newSkillTag = '';
        $this->readOnly = true;
        $this->loadOccupation($occupationId);
        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
        $this->readOnly = false;
    }

    public function updatedPsocCode(): void
    {
        $suggested = PsocCatalog::majorGroupForCode($this->psocCode);
        if ($suggested !== null && ($this->majorGroup === '' || PsocCatalog::isOfficialMajorGroup($this->majorGroup))) {
            $this->majorGroup = $suggested;
        }
    }

    public function addSkillTag(AuditLogger $audit): void
    {
        if ($this->readOnly) {
            return;
        }

        $tag = mb_strtolower(trim($this->newSkillTag));
        if ($tag === '') {
            return;
        }
        if (! preg_match('/^[a-z0-9][a-z0-9 \-\/]{0,40}$/', $tag)) {
            $this->addError('newSkillTag', 'Use a short, plain skill label.');

            return;
        }
        if (! in_array($tag, $this->selectedSkills, true)) {
            $this->selectedSkills[] = $tag;
            $subject = $this->occupationId ? PsocOccupation::query()->find($this->occupationId) : null;
            $audit->record('psoc_skill_tag_added', $subject, ['tag' => $tag]);
        }
        $this->newSkillTag = '';
        $this->resetValidation('newSkillTag');
    }

    public function save(AuditLogger $audit): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->authorize('create', PsocOccupation::class);
        $this->psocCode = trim($this->psocCode);
        $validated = $this->validate([
            'psocCode' => ['required', 'string', 'max:32', Rule::unique('psoc_occupations', 'psoc_code')->ignore($this->occupationId)],
            'title' => ['required', 'string', 'max:255'],
            'majorGroup' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'selectedSkills' => ['nullable', 'array'],
            'selectedSkills.*' => ['string', 'max:64'],
            'selectedPrograms' => ['nullable', 'array'],
            'selectedPrograms.*' => ['string', 'max:32'],
        ]);

        if (! PsocCatalog::codeLooksOfficial($validated['psocCode'])) {
            throw ValidationException::withMessages([
                'psocCode' => 'Enter the official 1–4 digit PSA PSOC 2012 code. Codes are not auto-generated.',
            ]);
        }

        $official = array_values(PsocCatalog::majorGroups());
        $allowedGroups = $official;
        if ($this->occupationId) {
            $existing = PsocOccupation::query()->find($this->occupationId);
            if ($existing && ! in_array($existing->major_group, $allowedGroups, true)) {
                $allowedGroups[] = $existing->major_group;
            }
        }
        if (! in_array($validated['majorGroup'], $allowedGroups, true)) {
            throw ValidationException::withMessages([
                'majorGroup' => 'Choose a PSA PSOC 2012 major group.',
            ]);
        }

        $expected = PsocCatalog::majorGroupForCode($validated['psocCode']);
        if ($expected !== null && PsocCatalog::isOfficialMajorGroup($validated['majorGroup']) && $validated['majorGroup'] !== $expected) {
            throw ValidationException::withMessages([
                'majorGroup' => 'That code belongs to '.$expected.'.',
            ]);
        }

        $programCodes = array_values(array_intersect(
            array_map('strtoupper', $validated['selectedPrograms'] ?? []),
            PsocCatalog::programOptions()->pluck('code')->all(),
        ));

        $payload = [
            'psoc_code' => $validated['psocCode'],
            'title' => $validated['title'],
            'major_group' => $validated['majorGroup'],
            'description' => $validated['description'] ?? '',
            'skill_tags' => array_values(array_unique(array_filter($validated['selectedSkills'] ?? []))),
            'related_program_codes' => $programCodes,
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

        $this->toast($this->statusMessage);
        $this->show = false;
        $audit->record('psoc_saved', $occupation, ['psoc_code' => $occupation->psoc_code]);
        $this->dispatch('catalog-changed');
    }

    public function render(): View
    {
        $this->authorize('create', PsocOccupation::class);
        $groups = PsocCatalog::majorGroups();
        if ($this->majorGroup !== '' && ! in_array($this->majorGroup, $groups, true)) {
            $groups['legacy'] = $this->majorGroup;
        }

        return view('livewire.admin.psoc-occupation-form', [
            'majorGroups' => $groups,
            'skillOptions' => array_values(array_unique([...PsocCatalog::knownSkillTags(), ...$this->selectedSkills])),
            'programOptions' => PsocCatalog::programOptions(),
        ]);
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
        $this->selectedSkills = array_values(array_filter(array_map(
            fn (mixed $tag): string => mb_strtolower(trim((string) $tag)),
            is_array($occupation->skill_tags) ? $occupation->skill_tags : [],
        )));
        $this->selectedPrograms = array_values(array_filter(array_map(
            'strtoupper',
            is_array($occupation->related_program_codes) ? $occupation->related_program_codes : [],
        )));
    }
}
