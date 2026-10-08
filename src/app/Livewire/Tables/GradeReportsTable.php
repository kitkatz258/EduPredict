<?php

namespace App\Livewire\Tables;

use App\Models\GradeReport;
use App\Services\Grades\GradeRowNormalizer;
use App\Services\Grades\GwaCalculator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class GradeReportsTable extends BaseTable
{
    public string $sortField = 'school_year';

    public string $sortDirection = 'desc';

    public ?int $viewingId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', GradeReport::class);
        abort_unless(auth()->user()?->student, 403);
    }

    #[On('grades-updated')]
    public function refreshReports(): void
    {
        $this->resetPage();
    }

    public function openView(int $id): void
    {
        $report = $this->ownedQuery()->findOrFail($id);
        $this->authorize('view', $report);
        $this->viewingId = $report->id;
    }

    public function closeView(): void
    {
        $this->viewingId = null;
    }

    public function continueDraft(int $id): void
    {
        $report = $this->ownedQuery()->findOrFail($id);
        $this->authorize('update', $report);
        $this->dispatch('grade-report-continue', id: $report->id);
    }

    public function replaceReport(int $id): void
    {
        $report = $this->ownedQuery()->findOrFail($id);
        $this->authorize('replace', $report);
        $this->dispatch('grade-report-replace', id: $report->id);
    }

    /**
     * Drafts only. Confirmed versions stay for history and are replaced, not deleted.
     */
    public function deleteReport(int $id): void
    {
        $report = $this->ownedQuery()->findOrFail($id);
        $this->authorize('delete', $report);
        $report->subjectGrades()->delete();
        $report->delete();
        $this->toast('Draft deleted.');
    }

    protected function baseQuery(): Builder
    {
        $this->authorize('viewAny', GradeReport::class);

        return $this->ownedQuery()->with('supersededBy:id,supersedes_id,version');
    }

    protected function columns(): array
    {
        return [
            ['key' => 'school_year', 'label' => 'Academic year', 'sortable' => true],
            ['key' => 'semester', 'label' => 'Semester', 'sortable' => true],
            ['key' => 'version', 'label' => 'Version', 'sortable' => true],
            ['key' => 'source', 'label' => 'Source', 'sortable' => true],
            ['key' => 'status', 'label' => 'Status', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['school_year', 'semester', 'source', 'status'];
    }

    protected function emptyMessage(): string
    {
        return 'No grade reports yet.';
    }

    public function render(): \Illuminate\View\View
    {
        $viewing = null;
        $viewingGwa = null;
        if ($this->viewingId !== null) {
            $viewing = $this->ownedQuery()->with(['subjectGrades', 'supersededBy'])->find($this->viewingId);
            if ($viewing !== null) {
                $this->authorize('view', $viewing);
                $viewingGwa = app(GwaCalculator::class)->compute($viewing->subjectGrades);
            }
        }

        $normalizer = app(GradeRowNormalizer::class);

        return view('livewire.tables.grade-reports-table', $this->tableViewData() + [
            'viewing' => $viewing,
            'viewingGwa' => $viewingGwa,
            'subjectName' => fn (string $name): string => $normalizer->stripInstructorName($name),
        ]);
    }

    private function ownedQuery(): Builder
    {
        $studentId = auth()->user()?->student?->id;
        abort_unless($studentId, 403);

        return GradeReport::query()->where('student_id', $studentId);
    }
}
