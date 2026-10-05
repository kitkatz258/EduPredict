<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ScopedStudentsTable extends BaseTable
{
    public string $sortField = 'student_number';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);
        $this->filters = [
            'dropout_risk' => '',
            'year_level' => '',
        ];
    }

    public function updating(string $name): void
    {
        if (str_starts_with($name, 'filters')) {
            $this->resetPage();
        }
    }

    protected function baseQuery(): Builder
    {
        $user = auth()->user();
        $this->authorize('viewAny', Student::class);

        return Student::query()
            ->visibleTo($user)
            ->with(['user', 'program', 'latestPrediction']);
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('student_number', 'like', '%'.$term.'%')
                ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$term.'%'))
                ->orWhereHas('program', fn (Builder $programs) => $programs->where('name', 'like', '%'.$term.'%'));
        });
    }

    protected function applyFilters(Builder $query): Builder
    {
        $year = (string) ($this->filters['year_level'] ?? '');
        if (in_array($year, ['1', '2', '3', '4'], true)) {
            $query->where('year_level', (int) $year);
        }

        $risk = (string) ($this->filters['dropout_risk'] ?? '');
        if (in_array($risk, ['low', 'moderate', 'high'], true)) {
            $query->whereHas('latestPrediction', fn (Builder $predictions) => $predictions->where('dropout_risk', $risk));
        }

        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'student_number', 'label' => 'Student number', 'sortable' => true],
            ['key' => 'year_level', 'label' => 'Year', 'sortable' => true],
            ['key' => 'id', 'label' => 'Student', 'sortable' => false],
        ];
    }

    protected function searchColumns(): array
    {
        return ['student_number'];
    }

    protected function emptyMessage(): string
    {
        return 'No students are in your scope.';
    }

    public function render(): View
    {
        return view('livewire.tables.scoped-students-table', $this->tableViewData());
    }
}
