<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Services\Admin\InstitutionStudentImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class InstitutionStudentForm extends Component
{
    use DispatchesToasts;

    public bool $show = false;

    public ?int $recordId = null;

    public bool $registered = false;

    public string $studentNumber = '';

    public string $lastName = '';

    public string $firstName = '';

    public ?int $programId = null;

    public mixed $yearLevel = null;

    public string $email = '';

    public string $birthdate = '';

    public function mount(): void
    {
        $this->authorize('create', InstitutionStudent::class);
    }

    #[On('add-eligible-student')]
    public function startCreate(): void
    {
        $this->authorize('create', InstitutionStudent::class);
        $this->resetForm();
        $this->show = true;
    }

    #[On('edit-eligible-student')]
    public function startEdit(int $recordId): void
    {
        $record = InstitutionStudent::query()->findOrFail($recordId);
        $this->authorize('update', $record);
        $this->resetValidation();
        $this->recordId = $record->id;
        $this->registered = $record->is_registered;
        $this->studentNumber = $record->student_number;
        $this->lastName = $record->last_name;
        $this->firstName = $record->first_name;
        $this->programId = $record->program_id;
        $this->yearLevel = $record->year_level;
        $this->email = (string) ($record->email ?? '');
        $this->birthdate = $record->birthdate?->format('Y-m-d') ?? '';
        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
        $this->resetForm();
    }

    public function save(InstitutionStudentImporter $registry): void
    {
        $this->authorize('create', InstitutionStudent::class);

        if ($this->recordId !== null && $this->registered) {
            $validated = $this->validate([
                'yearLevel' => ['required', 'integer', 'between:1,4'],
                'email' => ['nullable', 'email', 'max:255'],
                'birthdate' => ['nullable', 'date'],
            ], $this->yearMessages());
            $record = InstitutionStudent::query()->findOrFail($this->recordId);
            $this->authorize('update', $record);
            $registry->update($record, [
                'year_level' => $validated['yearLevel'],
                'email' => $validated['email'] ?? null,
                'birthdate' => $validated['birthdate'] ?? null,
            ], auth()->user(), request()->ip());
            $this->toast('Eligible student updated. The account and history were not changed.');
        } else {
            $validated = $this->validate([
                'studentNumber' => ['required', 'string', 'max:32', Rule::unique('institution_students', 'student_number')->ignore($this->recordId)],
                'lastName' => ['required', 'string', 'max:255'],
                'firstName' => ['required', 'string', 'max:255'],
                'programId' => ['required', 'integer', Rule::exists('programs', 'id')->where('is_active', true)],
                'yearLevel' => ['required', 'integer', 'between:1,4'],
                'email' => ['nullable', 'email', 'max:255'],
                'birthdate' => ['nullable', 'date'],
            ], $this->yearMessages());
            $payload = [
                'student_number' => $validated['studentNumber'],
                'last_name' => $validated['lastName'],
                'first_name' => $validated['firstName'],
                'program_id' => (int) $validated['programId'],
                'year_level' => (int) $validated['yearLevel'],
                'email' => $validated['email'] ?? null,
                'birthdate' => $validated['birthdate'] ?? null,
            ];

            if ($this->recordId === null) {
                $registry->create($payload, auth()->user(), request()->ip());
                $this->toast('Eligible student added. No account was created.');
            } else {
                $record = InstitutionStudent::query()->findOrFail($this->recordId);
                $this->authorize('update', $record);
                $registry->update($record, $payload, auth()->user(), request()->ip());
                $this->toast('Eligible student updated.');
            }
        }

        $this->show = false;
        $this->resetForm();
        $this->dispatch('eligible-students-imported');
    }

    public function render(): View
    {
        $this->authorize('create', InstitutionStudent::class);

        return view('livewire.admin.institution-student-form', [
            'programs' => Program::query()->inPilotScope()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function yearMessages(): array
    {
        $message = InstitutionStudentImporter::YEAR_MESSAGE;

        return [
            'yearLevel.required' => 'Year level is required.',
            'yearLevel.integer' => $message,
            'yearLevel.between' => $message,
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['recordId', 'registered', 'studentNumber', 'lastName', 'firstName', 'programId', 'yearLevel', 'email', 'birthdate']);
        $this->resetValidation();
    }
}
