<?php

namespace App\Livewire\Student;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\SkillsExperience;
use App\Models\Student;
use App\Models\StudentCertification;
use App\Models\StudentSkill;
use App\Models\StudentWorkExperience;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Structured Technical Skills, Certifications, and Work Experience entries.
 * Every action re-checks ownership; record ids from the client are never trusted.
 */
class SkillsExperienceSection extends Component
{
    use DispatchesToasts;

    private const MODELS = [
        'skill' => StudentSkill::class,
        'certification' => StudentCertification::class,
        'experience' => StudentWorkExperience::class,
    ];

    /** Open dialog: skill, certification, or experience. */
    public ?string $modal = null;

    public ?int $editingId = null;

    public bool $viewOnly = false;

    public bool $showArchived = false;

    /** @var array{name: string} */
    public array $skill = ['name' => ''];

    /** @var array<string, string|null> */
    public array $certification = [];

    /** @var array<string, string|bool|null> */
    public array $experience = [];

    public function mount(): void
    {
        $this->authorizeStudent();
        $this->resetForms();
    }

    public function openCreate(string $type): void
    {
        $this->authorizeStudent();
        $this->assertType($type);
        $this->authorize('create', self::MODELS[$type]);
        $this->resetForms();
        $this->modal = $type;
    }

    public function openEdit(string $type, int $id): void
    {
        $record = $this->ownedRecord($type, $id);
        $this->resetForms();
        $this->fillForm($type, $record);
        $this->editingId = $record->getKey();
        $this->modal = $type;
    }

    public function openView(string $type, int $id): void
    {
        $record = $this->ownedRecord($type, $id, 'view');
        $this->resetForms();
        $this->fillForm($type, $record);
        $this->editingId = $record->getKey();
        $this->viewOnly = true;
        $this->modal = $type;
    }

    public function closeModal(): void
    {
        $this->resetForms();
    }

    public function save(): void
    {
        $student = $this->authorizeStudent();
        $type = $this->modal;
        if ($type === null || $this->viewOnly) {
            return;
        }
        $this->assertType($type);

        $record = $this->editingId !== null ? $this->ownedRecord($type, $this->editingId) : null;
        if ($record === null) {
            $this->authorize('create', self::MODELS[$type]);
        }

        $attributes = match ($type) {
            'skill' => $this->validatedSkill($student),
            'certification' => $this->validatedCertification(),
            'experience' => $this->validatedExperience(),
        };

        if ($record !== null) {
            $record->update($attributes);
        } else {
            (self::MODELS[$type])::query()->create($attributes + ['student_id' => $student->id]);
        }

        $labels = ['skill' => 'Skill', 'certification' => 'Certification', 'experience' => 'Work experience'];
        $this->toast($labels[$type].($record !== null ? ' updated.' : ' added.'));
        $this->resetForms();
    }

    public function archive(string $type, int $id): void
    {
        $record = $this->ownedRecord($type, $id);
        $record->update(['archived_at' => now()]);
        $this->toast('Entry archived. Past prediction snapshots keep their copy.');
        if ($this->editingId === $record->getKey()) {
            $this->resetForms();
        }
    }

    public function restore(string $type, int $id): void
    {
        $record = $this->ownedRecord($type, $id);
        $record->update(['archived_at' => null]);
        $this->toast('Entry restored.');
    }

    public function toggleArchived(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function completeSection(): void
    {
        $student = $this->authorizeStudent();
        $existing = $student->skillsExperience;
        $existing
            ? $this->authorize('update', $existing)
            : $this->authorize('create', SkillsExperience::class);

        $section = $student->skillsExperience()->updateOrCreate(['student_id' => $student->id], ['is_draft' => false]);
        $this->authorize('update', $section);

        $this->toast('Skills and experience section saved.');
        $this->dispatch('skills-section-saved');
    }

    public function render(): View
    {
        $student = $this->authorizeStudent();
        $legacy = $student->skillsExperience;

        return view('livewire.student.skills-experience-section', [
            'skills' => $student->skills()->active()->orderBy('name')->get(),
            'certifications' => $student->certifications()->active()->orderByDesc('issued_year')->orderBy('title')->get(),
            'experiences' => $student->workExperiences()->active()->orderByDesc('start_date')->orderBy('organization')->get(),
            'archivedSkills' => $this->showArchived ? $student->skills()->archived()->orderBy('name')->get() : collect(),
            'archivedCertifications' => $this->showArchived ? $student->certifications()->archived()->orderBy('title')->get() : collect(),
            'archivedExperiences' => $this->showArchived ? $student->workExperiences()->archived()->orderBy('organization')->get() : collect(),
            'archivedCount' => $student->skills()->archived()->count()
                + $student->certifications()->archived()->count()
                + $student->workExperiences()->archived()->count(),
            'sectionSaved' => $legacy !== null && ! $legacy->is_draft,
            'legacyProjectCount' => is_array($legacy?->projects) ? count($legacy->projects) : 0,
            'experienceTypes' => config('edupredict.profile.experience_types', []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSkill(Student $student): array
    {
        $this->skill['name'] = trim((string) ($this->skill['name'] ?? ''));
        $validated = $this->validate(
            ['skill.name' => ['required', 'string', 'max:120']],
            [],
            ['skill.name' => 'skill name'],
        );

        $duplicate = $student->skills()->active()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['skill']['name'])])
            ->when($this->editingId !== null, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['skill.name' => 'This skill is already listed.']);
        }

        return ['name' => $validated['skill']['name']];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCertification(): array
    {
        $this->certification = array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value), $this->certification);
        $year = (int) now()->format('Y');
        $rules = [
            'certification.title' => ['required', 'string', 'max:160'],
            'certification.issuer' => ['required', 'string', 'max:160'],
            'certification.issued_year' => ['nullable', 'required_without:certification.issued_on', 'integer', 'between:1980,'.$year],
            'certification.issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'certification.expires_on' => ['nullable', 'date'],
            'certification.credential_reference' => ['nullable', 'string', 'max:120'],
            'certification.description' => ['nullable', 'string', 'max:1000'],
        ];
        if (! empty($this->certification['issued_on'])) {
            $rules['certification.expires_on'][] = 'after_or_equal:certification.issued_on';
        }

        $data = $this->validate($rules, [], [
            'certification.title' => 'certificate title',
            'certification.issuer' => 'issuer or provider',
            'certification.issued_year' => 'issue year',
            'certification.issued_on' => 'issue date',
            'certification.expires_on' => 'expiry date',
            'certification.credential_reference' => 'credential or reference',
            'certification.description' => 'description',
        ])['certification'];

        $issuedOn = $data['issued_on'] ?? null;

        return [
            'title' => $data['title'],
            'issuer' => $data['issuer'],
            'issued_on' => $issuedOn,
            'issued_year' => $issuedOn !== null ? (int) Carbon::parse($issuedOn)->format('Y') : (int) $data['issued_year'],
            'expires_on' => $data['expires_on'] ?? null,
            'credential_reference' => $data['credential_reference'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedExperience(): array
    {
        $this->experience = array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value), $this->experience);
        $ongoing = (bool) ($this->experience['is_ongoing'] ?? false);
        $rules = [
            'experience.experience_type' => ['required', Rule::in(array_keys(config('edupredict.profile.experience_types', [])))],
            'experience.organization' => ['required', 'string', 'max:160'],
            'experience.role_title' => ['required', 'string', 'max:160'],
            'experience.start_date' => ['required', 'date', 'before_or_equal:today'],
            'experience.end_date' => $ongoing ? ['nullable'] : ['required', 'date', 'after_or_equal:experience.start_date'],
            'experience.description' => ['nullable', 'string', 'max:2000'],
        ];

        $data = $this->validate($rules, [], [
            'experience.experience_type' => 'experience type',
            'experience.organization' => 'organization or employer',
            'experience.role_title' => 'role or title',
            'experience.start_date' => 'start date',
            'experience.end_date' => 'end date',
            'experience.description' => 'responsibilities',
        ])['experience'];

        return [
            'experience_type' => $data['experience_type'],
            'organization' => $data['organization'],
            'role_title' => $data['role_title'],
            'start_date' => $data['start_date'],
            'end_date' => $ongoing ? null : $data['end_date'],
            'is_ongoing' => $ongoing,
            'description' => $data['description'] ?? null,
        ];
    }

    private function fillForm(string $type, Model $record): void
    {
        match ($type) {
            'skill' => $this->skill = ['name' => (string) $record->name],
            'certification' => $this->certification = [
                'title' => $record->title,
                'issuer' => $record->issuer,
                'issued_year' => $record->issued_year !== null ? (string) $record->issued_year : null,
                'issued_on' => $record->issued_on?->toDateString(),
                'expires_on' => $record->expires_on?->toDateString(),
                'credential_reference' => $record->credential_reference,
                'description' => $record->description,
            ],
            'experience' => $this->experience = [
                'experience_type' => $record->experience_type,
                'organization' => $record->organization,
                'role_title' => $record->role_title,
                'start_date' => $record->start_date?->toDateString(),
                'end_date' => $record->end_date?->toDateString(),
                'is_ongoing' => (bool) $record->is_ongoing,
                'description' => $record->description,
            ],
        };
    }

    private function resetForms(): void
    {
        $this->resetValidation();
        $this->modal = null;
        $this->editingId = null;
        $this->viewOnly = false;
        $this->skill = ['name' => ''];
        $this->certification = [
            'title' => null,
            'issuer' => null,
            'issued_year' => null,
            'issued_on' => null,
            'expires_on' => null,
            'credential_reference' => null,
            'description' => null,
        ];
        $this->experience = [
            'experience_type' => StudentWorkExperience::TYPE_OJT_INTERNSHIP,
            'organization' => null,
            'role_title' => null,
            'start_date' => null,
            'end_date' => null,
            'is_ongoing' => false,
            'description' => null,
        ];
    }

    private function ownedRecord(string $type, int $id, string $ability = 'update'): Model
    {
        $this->authorizeStudent();
        $this->assertType($type);
        $record = (self::MODELS[$type])::query()->findOrFail($id);
        $this->authorize($ability, $record);

        return $record;
    }

    private function assertType(string $type): void
    {
        abort_unless(array_key_exists($type, self::MODELS), 404);
    }

    private function authorizeStudent(): Student
    {
        $this->authorize('viewAny', StudentSkill::class);
        $student = auth()->user()?->student;
        abort_unless($student instanceof Student, 403);

        return $student;
    }
}
