<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Enums\UserRole;
use App\Livewire\Concerns\ParsesReportFilters;
use App\Models\Program;
use App\Models\Student;
use App\Services\Analytics\CohortAnalytics;
use App\Services\Audit\AuditLogger;
use App\Support\SafeCsv;
use App\Support\StaffReportPreamble;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeanReport extends Component
{
    use ParsesReportFilters;

    public function mount(): void
    {
        $this->authorizeDean();
    }

    public function updatedDepartmentId(): void
    {
        if ($this->departmentId !== '' && ! ctype_digit($this->departmentId)) {
            $this->departmentId = '';
        }

        if ($this->programId !== '' && $this->departmentId !== '') {
            $belongs = Program::query()
                ->whereKey((int) $this->programId)
                ->where('department_id', (int) $this->departmentId)
                ->exists();
            if (! $belongs) {
                $this->programId = '';
            }
        }
    }

    public function export(CohortAnalytics $analytics): StreamedResponse
    {
        $this->authorizeDean();
        $stats = $this->stats($analytics);

        app(AuditLogger::class)->record('aggregates.export', null, [
            'surface' => 'dean_reports',
            'students' => $stats['students'],
            'year_level' => $this->yearLevel,
            'program_id' => $this->programId,
            'department_id' => $this->departmentId,
            'period' => $this->period,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);

        $stamp = now()->timezone((string) config('app.timezone'))->format('Ymd-Hi');
        $filename = SafeCsv::filename('edupredict-clas-aggregates-'.$stamp);
        $preamble = StaffReportPreamble::rows('CLAS aggregate report', $this->filterLabels(), true);

        return response()->streamDownload(function () use ($stats, $preamble): void {
            $handle = fopen('php://output', 'w');
            foreach ($preamble as $meta) {
                fputcsv($handle, SafeCsv::row($meta));
            }
            fputcsv($handle, []);
            fputcsv($handle, SafeCsv::row(['metric', 'value', 'unit_or_note']));
            fputcsv($handle, SafeCsv::row(['students', $stats['students'], 'count']));
            fputcsv($handle, SafeCsv::row(['with_prediction', $stats['with_prediction'], 'count']));
            fputcsv($handle, SafeCsv::row(['average_employability', $stats['average_employability'] ?? '', '0-100 estimate; not a probability']));
            fputcsv($handle, SafeCsv::row(['high_risk', $stats['high_risk'], 'count of latest high-risk estimates']));
            fputcsv($handle, SafeCsv::row(['moderate_risk', $stats['risk']['moderate'], 'count']));
            fputcsv($handle, SafeCsv::row(['low_risk', $stats['risk']['low'], 'count']));
            fputcsv($handle, SafeCsv::row(['program_fit_concern', $stats['program_shift']['program_fit'], 'count; qualitative, no percentage']));
            fputcsv($handle, SafeCsv::row(['broader_disengagement', $stats['program_shift']['disengagement'], 'count; qualitative, no percentage']));
            fputcsv($handle, SafeCsv::row(['mixed_signals', $stats['program_shift']['mixed'], 'count; qualitative, no percentage']));
            fputcsv($handle, SafeCsv::row(['no_shift_pattern', $stats['program_shift']['none'], 'count; qualitative, no percentage']));
            fputcsv($handle, SafeCsv::row(['assessments_this_year', $stats['assessments_this_year'], 'count of stored estimates this calendar year']));
            fputcsv($handle, []);
            fputcsv($handle, SafeCsv::row(['year', 'students', 'low', 'moderate', 'high', 'average_employability']));
            foreach ($stats['year_levels'] as $level) {
                fputcsv($handle, SafeCsv::row([
                    $level['year'],
                    $level['students'],
                    $level['low'],
                    $level['moderate'],
                    $level['high'],
                    $level['average_employability'] ?? '',
                ]));
            }
            fputcsv($handle, []);
            fputcsv($handle, SafeCsv::row(['program', 'students', 'average_employability', 'low', 'moderate', 'high']));
            foreach ($stats['programs'] as $program) {
                fputcsv($handle, SafeCsv::row([
                    $program['code'],
                    $program['students'],
                    $program['average_employability'] ?? '',
                    $program['low'],
                    $program['moderate'],
                    $program['high'],
                ]));
            }
            if ($stats['departments'] !== []) {
                fputcsv($handle, []);
                fputcsv($handle, SafeCsv::row(['department', 'students', 'average_employability', 'low', 'moderate', 'high']));
                foreach ($stats['departments'] as $department) {
                    fputcsv($handle, SafeCsv::row([
                        $department['name'],
                        $department['students'],
                        $department['average_employability'] ?? '',
                        $department['low'],
                        $department['moderate'],
                        $department['high'],
                    ]));
                }
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(CohortAnalytics $analytics): View
    {
        $this->authorizeDean();
        $user = auth()->user();

        return view('livewire.staff.dean-report', [
            'stats' => $this->stats($analytics),
            'programs' => $analytics->programsFor($user),
            'departmentOptions' => $analytics->departmentsFor($user),
            'generatedAt' => now()->timezone((string) config('app.timezone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(CohortAnalytics $analytics): array
    {
        return $analytics->forUser(
            auth()->user(),
            $this->year(),
            $this->program(),
            $this->department(),
            $this->fromDate() === null && $this->toDate() === null ? $this->period() : null,
            $this->fromDate(),
            $this->toDate(),
        );
    }

    private function authorizeDean(): void
    {
        abort_unless(auth()->user()?->isRole(UserRole::Dean) === true, 403);
        abort_unless(auth()->user()?->can('viewAggregates', Student::class) === true, 403);
    }
}
