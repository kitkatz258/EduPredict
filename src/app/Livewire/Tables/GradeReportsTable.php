<?php

namespace App\Livewire\Tables;

use App\Models\GradeReport;
use Illuminate\Database\Eloquent\Builder;

class GradeReportsTable extends BaseTable
{
    public string $sortField = 'school_year';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->authorize('viewAny', GradeReport::class);
        abort_unless(auth()->user()?->student, 403);
    }

    public function deleteReport(int $id): void
    {
        $report = $this->ownedQuery()->findOrFail($id);
        $this->authorize('delete', $report);
        $report->subjectGrades()->delete();
        $report->delete();
        session()->flash('success', 'Grade report deleted.');
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', GradeReport::class);

        return $this->ownedQuery();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'school_year', 'label' => 'School year', 'sortable' => true],
            ['key' => 'semester', 'label' => 'Semester', 'sortable' => true],
            ['key' => 'source', 'label' => 'Source', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['school_year', 'semester', 'source', 'status'];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tables.grade-reports-table', $this->tableViewData());
    }

    private function ownedQuery(): Builder
    {
        $studentId = auth()->user()?->student?->id;
        abort_unless($studentId, 403);

        return GradeReport::query()->where('student_id', $studentId);
    }
}
