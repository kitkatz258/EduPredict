<?php

namespace App\Livewire\Tables;

use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Services\Admin\StudentAccountProvisioner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;

class InstitutionStudentsTable extends BaseTable
{
    public string $sortField = 'id';

    public string $sortDirection = 'desc';

    public string $programFilter = '';

    public string $yearFilter = '';

    public string $registeredFilter = '';

    public ?int $provisioningId = null;

    public string $provisionEmail = '';

    public function mount(): void
    {
        $this->authorize('viewAny', InstitutionStudent::class);
    }

    public function updatingProgramFilter(): void
    {
        $this->resetPage();
    }

    public function updatingYearFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRegisteredFilter(): void
    {
        $this->resetPage();
    }

    public function editEligible(int $recordId): void
    {
        $record = InstitutionStudent::query()->findOrFail($recordId);
        $this->authorize('update', $record);
        $this->dispatch('edit-eligible-student', recordId: $recordId);
    }

    public function openProvision(int $recordId): void
    {
        $record = InstitutionStudent::query()->findOrFail($recordId);
        $this->authorize('update', $record);
        if ($record->is_registered) {
            $this->toast('This student number already has a login.', 'error');

            return;
        }
        $this->provisioningId = $record->id;
        $this->provisionEmail = (string) ($record->email ?? '');
        $this->resetValidation();
    }

    public function closeProvision(): void
    {
        $this->provisioningId = null;
        $this->provisionEmail = '';
        $this->resetValidation();
    }

    public function issueLogin(StudentAccountProvisioner $provisioner): void
    {
        $record = InstitutionStudent::query()->findOrFail((int) $this->provisioningId);
        $this->authorize('update', $record);
        $this->validate([
            'provisionEmail' => ['required', 'email', 'max:255'],
        ]);

        try {
            $provisioner->provision($record, auth()->user(), $this->provisionEmail, request()->ip());
        } catch (ValidationException $exception) {
            $messages = $exception->validator->getMessageBag();
            if ($messages->has('email')) {
                $this->addError('provisionEmail', $messages->first('email'));
            }
            if ($messages->has('studentNumber')) {
                $this->addError('provisionEmail', $messages->first('studentNumber'));
            }
            if ($messages->has('lastName')) {
                $this->addError('provisionEmail', $messages->first('lastName'));
            }

            return;
        }

        $this->closeProvision();
        $this->dispatch('eligible-students-imported');
        $this->successDialog(
            'The student can sign in with their student number. The initial password is their last name in uppercase. They must change it at first sign-in.',
            'Login issued.',
        );
    }

    #[On('eligible-students-imported')]
    public function refreshAfterImport(): void
    {
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', InstitutionStudent::class);

        return InstitutionStudent::query()->with('program');
    }

    protected function applyFilters(Builder $query): Builder
    {
        if (ctype_digit($this->programFilter)) {
            $query->where('program_id', (int) $this->programFilter);
        }

        if (in_array($this->yearFilter, ['1', '2', '3', '4', '5', '6'], true)) {
            $query->where('year_level', (int) $this->yearFilter);
        }

        if ($this->registeredFilter === 'yes') {
            $query->where('is_registered', true);
        } elseif ($this->registeredFilter === 'no') {
            $query->where('is_registered', false);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'student_number', 'label' => 'Student number', 'sortable' => true],
            ['key' => 'last_name', 'label' => 'Last name', 'sortable' => true],
            ['key' => 'first_name', 'label' => 'First name', 'sortable' => true],
            ['key' => 'year_level', 'label' => 'Year', 'sortable' => true],
            ['key' => 'is_registered', 'label' => 'Claimed', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['student_number', 'last_name', 'first_name'];
    }

    public function render(): View
    {
        return view('livewire.tables.institution-students-table', [
            ...$this->tableViewData(),
            'programs' => Program::query()->orderBy('code')->get(['id', 'code']),
            'outOfRangeYears' => InstitutionStudent::query()
                ->where(function (Builder $query): void {
                    $query->where('year_level', '<', 1)->orWhere('year_level', '>', 4);
                })
                ->orderBy('student_number')
                ->get(['id', 'student_number', 'year_level']),
            'provisioning' => $this->provisioningId
                ? InstitutionStudent::query()->find($this->provisioningId)
                : null,
        ]);
    }
}
