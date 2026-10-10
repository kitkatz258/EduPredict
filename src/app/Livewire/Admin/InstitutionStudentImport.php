<?php

namespace App\Livewire\Admin;

use App\Models\InstitutionStudent;
use App\Services\Admin\InstitutionStudentImporter;
use Livewire\Component;
use Livewire\WithFileUploads;

class InstitutionStudentImport extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public $csv;

    /** @var list<array{row: int, message: string}> */
    public array $errorsList = [];

    public int $imported = 0;

    public function mount(): void
    {
        $this->authorize('create', InstitutionStudent::class);
    }

    public function openImport(): void
    {
        $this->authorize('create', InstitutionStudent::class);
        $this->resetValidation();
        $this->open = true;
    }

    public function closeImport(): void
    {
        $this->open = false;
        $this->reset(['csv', 'errorsList', 'imported']);
        $this->resetValidation();
    }

    public function import(InstitutionStudentImporter $importer): void
    {
        $this->authorize('create', InstitutionStudent::class);
        $this->open = true;
        $this->errorsList = [];
        $this->imported = 0;

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $importer->import($this->csv, auth()->user(), request()->ip());
        $this->imported = $result['imported'];
        $this->errorsList = $result['errors'];
        $this->reset('csv');
        $this->dispatch('eligible-students-imported');
    }

    public function render()
    {
        return view('livewire.admin.institution-student-import');
    }
}
