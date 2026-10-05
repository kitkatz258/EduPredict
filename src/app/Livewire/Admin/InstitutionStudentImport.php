<?php

namespace App\Livewire\Admin;

use App\Models\InstitutionStudent;
use App\Services\Admin\InstitutionStudentImporter;
use Livewire\Component;
use Livewire\WithFileUploads;

class InstitutionStudentImport extends Component
{
    use WithFileUploads;

    public $csv;

    /** @var list<array{row: int, message: string}> */
    public array $errorsList = [];

    public int $imported = 0;

    public function mount(): void
    {
        $this->authorize('create', InstitutionStudent::class);
    }

    public function import(InstitutionStudentImporter $importer): void
    {
        $this->authorize('create', InstitutionStudent::class);

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $importer->import($this->csv, auth()->user(), request()->ip());
        $this->imported = $result['imported'];
        $this->errorsList = $result['errors'];
        $this->reset('csv');
    }

    public function render()
    {
        return view('livewire.admin.institution-student-import');
    }
}
