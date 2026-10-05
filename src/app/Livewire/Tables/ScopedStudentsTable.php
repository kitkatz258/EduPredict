<?php

declare(strict_types=1);

namespace App\Livewire\Tables;

use App\Models\AuditLog;
use App\Models\Program;
use App\Models\Student;
use App\Services\Prediction\ProgramShiftEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScopedStudentsTable extends BaseTable
{
    public string $sortField = 'student_number';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);
        $this->filters = [
            'dropout_risk' => '',
            'year_level' => '',
            'program_id' => '',
        ];
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Student::class);
        $rows = $this->filteredQuery()->get();

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => 'students.export',
            'subject_type' => Student::class,
            'subject_id' => null,
            'meta' => [
                'rows' => $rows->count(),
                'year_level' => (string) ($this->filters['year_level'] ?? ''),
                'dropout_risk' => (string) ($this->filters['dropout_risk'] ?? ''),
                'program_id' => (string) ($this->filters['program_id'] ?? ''),
                'search' => trim($this->search) === '' ? '' : 'set',
            ],
            'ip' => request()->ip(),
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'student_number',
                'name',
                'program',
                'year_level',
                'dropout_risk',
                'employability_score',
                'program_shift_flag',
                'prediction_date',
            ]);
            foreach ($rows as $row) {
                $latest = $row->latestPrediction;
                fputcsv($handle, [
                    $row->student_number,
                    $row->user?->name,
                    $row->program?->code,
                    $row->year_level,
                    $latest?->dropout_risk ?? '',
                    $latest ? number_format((float) $latest->employability_score, 2, '.', '') : '',
                    $latest?->program_shift_flag ?? '',
                    $latest?->created_at?->timezone((string) config('app.timezone'))->toDateString() ?? '',
                ]);
            }
            fclose($handle);
        }, 'edupredict-students.csv', ['Content-Type' => 'text/csv']);
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

        $programId = (string) ($this->filters['program_id'] ?? '');
        if ($programId !== '' && ctype_digit($programId)) {
            $query->where('program_id', (int) $programId);
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
        $programIds = (clone $this->baseQuery())->distinct()->pluck('program_id');

        return view('livewire.tables.scoped-students-table', [
            ...$this->tableViewData(),
            'programs' => Program::query()->whereIn('id', $programIds)->orderBy('code')->get(),
            'shiftLabels' => app(ProgramShiftEvaluator::class),
        ]);
    }
}
