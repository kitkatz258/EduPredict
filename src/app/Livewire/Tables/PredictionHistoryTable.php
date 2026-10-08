<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\Prediction;
use App\Models\Student;
use App\Services\Prediction\AttemptSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PredictionHistoryTable extends BaseTable
{
    public int $studentId;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public ?int $viewingId = null;

    public function mount(int $studentId): void
    {
        $this->authorizeStudent($studentId);
        $this->studentId = $studentId;
        $this->filters = ['dropout_risk' => ''];
    }

    public function updatedStudentId(int $studentId): void
    {
        $this->authorizeStudent($studentId);
    }

    public function updating(string $name): void
    {
        if (str_starts_with($name, 'filters')) {
            $this->resetPage();
        }
    }

    /**
     * The saved attempt snapshot holds questionnaire answers and skill entries,
     * so only the owning student can open it.
     */
    public function openView(int $id): void
    {
        $student = $this->authorizeOwner();
        $this->viewingId = Prediction::query()->where('student_id', $student->id)->findOrFail($id)->id;
    }

    public function closeView(): void
    {
        $this->viewingId = null;
    }

    protected function baseQuery(): Builder
    {
        $student = $this->authorizeStudent($this->studentId);

        return Prediction::query()->where('student_id', $student->id);
    }

    protected function applyFilters(Builder $query): Builder
    {
        $risk = (string) ($this->filters['dropout_risk'] ?? '');
        if (in_array($risk, ['low', 'moderate', 'high'], true)) {
            $query->where('dropout_risk', $risk);
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Date and time', 'sortable' => true],
            ['key' => 'employability_score', 'label' => 'Employability', 'sortable' => true],
            ['key' => 'dropout_risk', 'label' => 'Dropout risk', 'sortable' => true],
            ['key' => 'confidence', 'label' => 'Confidence', 'sortable' => true],
            ['key' => 'model_version', 'label' => 'Model', 'sortable' => true],
        ];
    }

    protected function searchColumns(): array
    {
        return ['dropout_risk', 'confidence', 'model_version'];
    }

    protected function emptyMessage(): string
    {
        return 'No predictions yet.';
    }

    public function render(): View
    {
        $snapshots = app(AttemptSnapshot::class);
        $student = $this->authorizeStudent($this->studentId);
        $isOwner = $student->user_id === auth()->id();
        $viewing = null;

        if ($this->viewingId !== null && $isOwner) {
            $prediction = Prediction::query()->where('student_id', $student->id)->find($this->viewingId);
            $viewing = $prediction ? $snapshots->for($prediction) : null;
        }

        return view('livewire.tables.prediction-history-table', $this->tableViewData() + [
            'isOwner' => $isOwner,
            'latestId' => $student->predictions()->latest('created_at')->latest('id')->value('id'),
            'snapshots' => $snapshots,
            'viewing' => $viewing,
        ]);
    }

    private function authorizeStudent(int $studentId): Student
    {
        $student = Student::query()->findOrFail($studentId);
        $this->authorize('view', $student);

        return $student;
    }

    private function authorizeOwner(): Student
    {
        $student = $this->authorizeStudent($this->studentId);
        abort_unless($student->user_id === auth()->id(), 403);

        return $student;
    }
}
