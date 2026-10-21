<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\InstitutionStudent;
use App\Services\Admin\InstitutionStudentImporter;
use Livewire\Component;
use Livewire\WithFileUploads;

class InstitutionStudentImport extends Component
{
    use DispatchesToasts;
    use WithFileUploads;

    public bool $open = false;

    public $csv;

    /** @var list<array{row: int, message: string}> */
    public array $errorsList = [];

    /** @var list<array{row: int, message: string}> */
    public array $skippedList = [];

    public int $examined = 0;

    public int $valid = 0;

    public int $imported = 0;

    public int $skipped = 0;

    public string $summary = '';

    public function mount(): void
    {
        $this->authorize('create', InstitutionStudent::class);
    }

    public function openImport(): void
    {
        $this->authorize('create', InstitutionStudent::class);
        $this->resetValidation();
        $this->resetResult();
        $this->open = true;
    }

    public function closeImport(): void
    {
        $this->open = false;
        $this->reset(['csv']);
        $this->resetResult();
        $this->resetValidation();
    }

    public function import(InstitutionStudentImporter $importer): void
    {
        $this->authorize('create', InstitutionStudent::class);
        $this->open = true;
        $this->resetResult();

        $this->validate([
            'csv' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ], [
            'csv.required' => 'Choose a CSV file before importing.',
            'csv.extensions' => 'The file must be a .csv or .txt file.',
        ]);

        $result = $importer->import($this->csv, auth()->user(), request()->ip());
        $this->examined = $result['examined'];
        $this->valid = $result['valid'];
        $this->imported = $result['imported'];
        $this->skipped = $result['skipped'];
        $this->errorsList = $result['errors'];
        $this->skippedList = $result['skipped_rows'];
        $this->summary = sprintf(
            '%d row(s) read, %d valid, %d imported, %d skipped, %d error(s).',
            $this->examined,
            $this->valid,
            $this->imported,
            $this->skipped,
            count($this->errorsList),
        );
        $this->reset('csv');

        if ($this->imported > 0) {
            $this->dispatch('eligible-students-imported');
            $this->toast($this->imported === 1 ? '1 eligible student imported.' : "{$this->imported} eligible students imported.");
        } elseif ($this->errorsList !== []) {
            $this->toast('The file was read, but nothing new was imported.', 'error');
        } else {
            $this->toast($this->summary, 'info');
        }
    }

    public function render()
    {
        $this->authorize('create', InstitutionStudent::class);

        return view('livewire.admin.institution-student-import');
    }

    private function resetResult(): void
    {
        $this->errorsList = [];
        $this->skippedList = [];
        $this->examined = 0;
        $this->valid = 0;
        $this->imported = 0;
        $this->skipped = 0;
        $this->summary = '';
    }
}
