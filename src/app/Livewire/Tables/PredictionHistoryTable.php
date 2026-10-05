<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\Prediction;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PredictionHistoryTable extends BaseTable
{
    public int $studentId;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

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
            ['key' => 'created_at', 'label' => 'Requested', 'sortable' => true],
            ['key' => 'employability_score', 'label' => 'Employability', 'sortable' => true],
            ['key' => 'dropout_risk', 'label' => 'Dropout risk', 'sortable' => true],
            ['key' => 'dropout_probability', 'label' => 'Probability', 'sortable' => true],
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
        return view('livewire.tables.prediction-history-table', $this->tableViewData());
    }

    private function authorizeStudent(int $studentId): Student
    {
        $student = Student::query()->findOrFail($studentId);
        $this->authorize('view', $student);

        return $student;
    }
}
