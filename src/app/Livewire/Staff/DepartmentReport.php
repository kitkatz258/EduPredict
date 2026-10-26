<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Enums\UserRole;
use App\Livewire\Concerns\ParsesReportFilters;
use App\Models\Program;
use App\Models\Student;
use App\Services\Audit\AuditLogger;
use App\Services\Prediction\ProgramShiftEvaluator;
use App\Support\SafeCsv;
use App\Support\StaffReportPreamble;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartmentReport extends Component
{
    use ParsesReportFilters;

    public function mount(): void
    {
        $this->authorizeDepartmentHead();
    }

    public function export(): StreamedResponse
    {
        $this->authorizeDepartmentHead();
        $rows = $this->students();
        $shift = app(ProgramShiftEvaluator::class);

        app(AuditLogger::class)->record('students.export', null, [
            'surface' => 'department_reports',
            'rows' => $rows->count(),
            'year_level' => $this->yearLevel,
            'dropout_risk' => $this->dropoutRisk,
            'program_id' => $this->programId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);

        $stamp = now()->timezone((string) config('app.timezone'))->format('Ymd-Hi');
        $filename = SafeCsv::filename('edupredict-department-students-'.$stamp);
        $preamble = StaffReportPreamble::rows('Department student summaries', $this->filterLabels());

        return response()->streamDownload(function () use ($rows, $shift, $preamble): void {
            $handle = fopen('php://output', 'w');
            foreach ($preamble as $meta) {
                fputcsv($handle, SafeCsv::row($meta));
            }
            fputcsv($handle, []);
            fputcsv($handle, SafeCsv::row([
                'student_number',
                'name',
                'program',
                'year_level',
                'dropout_risk',
                'employability_score',
                'program_shift_flag',
                'program_shift_label',
                'prediction_date',
                'model_version',
            ]));
            foreach ($rows as $row) {
                $latest = $row->latestPrediction;
                $flag = (string) ($latest?->program_shift_flag ?? '');
                fputcsv($handle, SafeCsv::row([
                    $row->student_number,
                    $row->user?->name,
                    $row->program?->code,
                    $row->year_level,
                    $latest?->dropout_risk ?? '',
                    $latest ? number_format((float) $latest->employability_score, 2, '.', '') : '',
                    $flag,
                    $flag !== '' ? $shift->label($flag) : '',
                    $latest?->created_at?->timezone((string) config('app.timezone'))->toDateString() ?? '',
                    $latest?->model_version ?? '',
                ]));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $this->authorizeDepartmentHead();
        $user = auth()->user();
        $rows = $this->students();
        $programIds = Student::query()->visibleTo($user)->distinct()->pluck('program_id');

        return view('livewire.staff.department-report', [
            'rows' => $rows,
            'programs' => Program::query()->whereIn('id', $programIds)->orderBy('code')->get(),
            'shiftLabels' => app(ProgramShiftEvaluator::class),
            'yearOptions' => [
                1 => '1st Year',
                2 => '2nd Year',
                3 => '3rd Year',
                4 => '4th Year',
            ],
            'generatedAt' => now()->timezone((string) config('app.timezone')),
        ]);
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(): Collection
    {
        $query = Student::query()
            ->visibleTo(auth()->user())
            ->with(['user', 'program', 'latestPrediction']);

        $year = $this->year();
        if ($year !== null) {
            $query->where('year_level', $year);
        }

        $program = $this->program();
        if ($program !== null) {
            $allowed = Student::query()->visibleTo(auth()->user())->distinct()->pluck('program_id')->all();
            if (in_array($program, array_map('intval', $allowed), true)) {
                $query->where('program_id', $program);
            }
        }

        $risk = $this->dropoutRisk;
        if (in_array($risk, ['low', 'moderate', 'high'], true)) {
            $query->whereHas('latestPrediction', fn (Builder $predictions) => $predictions->where('dropout_risk', $risk));
        }

        $from = $this->fromDate();
        $to = $this->toDate();
        if ($from !== null || $to !== null) {
            $query->whereHas('latestPrediction', function (Builder $predictions) use ($from, $to): void {
                if ($from !== null) {
                    $predictions->where('created_at', '>=', $from);
                }
                if ($to !== null) {
                    $predictions->where('created_at', '<=', $to);
                }
            });
        }

        return $query->orderBy('student_number')->orderBy('id')->get();
    }

    private function authorizeDepartmentHead(): void
    {
        abort_unless(auth()->user()?->isRole(UserRole::DepartmentHead) === true, 403);
        $this->authorize('viewAny', Student::class);
    }
}
