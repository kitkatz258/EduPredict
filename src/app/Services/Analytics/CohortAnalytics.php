<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard totals use each student's latest prediction.
 * Latest means the newest created_at, then the highest id.
 */
final class CohortAnalytics
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(
        User $user,
        ?int $yearLevel = null,
        ?int $programId = null,
        ?int $departmentId = null,
        ?string $period = null,
    ): array {
        $scoped = $this->students($user, $yearLevel, $programId, $departmentId);
        $rows = $this->latestRows($scoped);
        $periodStart = $this->periodStart($period);
        $periodApplied = $periodStart !== null;

        if ($periodApplied) {
            $rows = $rows
                ->filter(fn (Prediction $row): bool => $row->created_at !== null && $row->created_at->greaterThanOrEqualTo($periodStart))
                ->values();
            $ids = $rows->pluck('student_id')->map(fn (mixed $id): int => (int) $id)->all();
            $cohort = Student::query()->whereIn('students.id', $ids === [] ? [0] : $ids);
        } else {
            $cohort = $scoped;
        }

        $studentCount = (clone $cohort)->count();

        $risk = [
            'low' => $rows->where('dropout_risk', 'low')->count(),
            'moderate' => $rows->where('dropout_risk', 'moderate')->count(),
            'high' => $rows->where('dropout_risk', 'high')->count(),
        ];
        $shift = [
            'none' => $rows->where('program_shift_flag', 'none')->count(),
            'program_fit' => $rows->where('program_shift_flag', 'program_fit')->count(),
            'disengagement' => $rows->where('program_shift_flag', 'disengagement')->count(),
            'mixed' => $rows->where('program_shift_flag', 'mixed')->count(),
        ];

        $average = $rows->isEmpty() ? null : round((float) $rows->avg('employability_score'), 1);
        $programRows = $this->programs($cohort, $rows);

        return [
            'students' => $studentCount,
            'with_prediction' => $rows->count(),
            'average_employability' => $average,
            'high_risk' => $risk['high'],
            'risk' => $risk,
            'program_shift' => $shift,
            'program_concern' => $shift['program_fit'] + $shift['mixed'],
            'unreviewed_high' => $this->unreviewedHigh($scoped),
            'assessments_this_year' => $this->assessmentsThisYear($scoped),
            'employability_bands' => $this->bands($rows),
            'year_levels' => $this->yearLevels($cohort, $rows),
            'trend' => $this->trend($rows),
            'programs' => $programRows,
            'departments' => $this->departmentSummary($programRows),
            'period_applied' => $periodApplied,
            'charts' => $this->charts($risk, $average, $rows),
        ];
    }

    /**
     * @return Collection<int, Program>
     */
    public function programsFor(User $user): Collection
    {
        return Program::query()
            ->whereIn('id', $this->allowedProgramIds($user))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'department_id']);
    }

    /**
     * @return Collection<int, Department>
     */
    public function departmentsFor(User $user): Collection
    {
        return Department::query()
            ->whereIn('id', $this->allowedDepartmentIds($user))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    private function students(User $user, ?int $yearLevel, ?int $programId, ?int $departmentId = null): Builder
    {
        $query = Student::query()->aggregatableBy($user);
        $allowedPrograms = $this->allowedProgramIds($user);

        if (in_array($yearLevel, [1, 2, 3, 4], true)) {
            $query->where('year_level', $yearLevel);
        }

        if ($departmentId !== null && in_array($departmentId, $this->allowedDepartmentIds($user), true)) {
            $departmentPrograms = array_values(array_map(
                'intval',
                Program::query()->where('department_id', $departmentId)->whereIn('id', $allowedPrograms)->pluck('id')->all(),
            ));
            $query->whereIn('students.program_id', $departmentPrograms === [] ? [0] : $departmentPrograms);
        }

        if ($programId !== null && in_array($programId, $allowedPrograms, true)) {
            $query->where('students.program_id', $programId);
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function allowedDepartmentIds(User $user): array
    {
        return array_values(array_unique(array_map(
            'intval',
            Program::query()
                ->whereIn('id', $this->allowedProgramIds($user))
                ->whereNotNull('department_id')
                ->pluck('department_id')
                ->all(),
        )));
    }

    private function periodStart(?string $period): ?Carbon
    {
        return match ($period) {
            'this_year' => now()->startOfYear(),
            'last_12_months' => now()->copy()->subMonths(12)->startOfDay(),
            default => null,
        };
    }

    /**
     * Mirrors Student::aggregatableBy so filters never widen the scope.
     *
     * @return list<int>
     */
    private function allowedProgramIds(User $user): array
    {
        $programs = Program::query();

        match ($user->role) {
            UserRole::DepartmentHead => $user->program_id !== null
                ? $programs->whereKey($user->program_id)
                : $programs->where('department_id', $user->department_id ?? 0),
            UserRole::Dean => $programs->where('college_id', $user->college_id ?? 0)->where('is_active', true),
            UserRole::Administrator => $programs->where('is_active', true),
            UserRole::Student, UserRole::Faculty => $programs->whereRaw('1 = 0'),
        };

        return array_values(array_map('intval', $programs->pluck('id')->all()));
    }

    /**
     * @return Collection<int, Prediction>
     */
    private function latestRows(Builder $students): Collection
    {
        $studentIds = (clone $students)->select('students.id');
        $latestIds = Prediction::query()
            ->selectRaw('MAX(predictions.id) as id')
            ->joinSub(
                Prediction::query()
                    ->select('student_id')
                    ->selectRaw('MAX(created_at) as created_at')
                    ->whereIn('student_id', $studentIds)
                    ->groupBy('student_id'),
                'latest_time',
                function ($join): void {
                    $join->on('predictions.student_id', '=', 'latest_time.student_id')
                        ->on('predictions.created_at', '=', 'latest_time.created_at');
                },
            )
            ->groupBy('predictions.student_id');

        return Prediction::query()
            ->join('students', 'students.id', '=', 'predictions.student_id')
            ->join('programs', 'programs.id', '=', 'students.program_id')
            ->whereIn('predictions.id', $latestIds)
            ->orderBy('programs.code')
            ->get([
                'predictions.id',
                'predictions.student_id',
                'predictions.employability_score',
                'predictions.dropout_risk',
                'predictions.program_shift_flag',
                'predictions.created_at',
                'students.year_level',
                'students.program_id',
                'programs.code as program_code',
                'programs.name as program_name',
            ]);
    }

    private function unreviewedHigh(Builder $students): int
    {
        $latestIds = $this->latestIdSubquery($students);

        return Prediction::query()
            ->whereIn('id', $latestIds)
            ->where('dropout_risk', 'high')
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('recommendedActions')
                    ->orWhereHas('recommendedActions', fn (Builder $actions) => $actions->whereNull('reviewed_at'));
            })
            ->count();
    }

    private function assessmentsThisYear(Builder $students): int
    {
        return Prediction::query()
            ->whereIn('student_id', (clone $students)->select('students.id'))
            ->where('created_at', '>=', now()->startOfYear())
            ->count();
    }

    private function latestIdSubquery(Builder $students): Builder
    {
        $studentIds = (clone $students)->select('students.id');

        return Prediction::query()
            ->selectRaw('MAX(predictions.id) as id')
            ->joinSub(
                Prediction::query()
                    ->select('student_id')
                    ->selectRaw('MAX(created_at) as created_at')
                    ->whereIn('student_id', $studentIds)
                    ->groupBy('student_id'),
                'latest_time',
                function ($join): void {
                    $join->on('predictions.student_id', '=', 'latest_time.student_id')
                        ->on('predictions.created_at', '=', 'latest_time.created_at');
                },
            )
            ->groupBy('predictions.student_id');
    }

    /**
     * @param  Collection<int, Prediction>  $rows
     * @return array<string, int>
     */
    private function bands(Collection $rows): array
    {
        $bands = [
            '0-19' => 0,
            '20-39' => 0,
            '40-59' => 0,
            '60-79' => 0,
            '80-100' => 0,
        ];

        foreach ($rows as $row) {
            $score = (float) $row->employability_score;
            $key = match (true) {
                $score < 20 => '0-19',
                $score < 40 => '20-39',
                $score < 60 => '40-59',
                $score < 80 => '60-79',
                default => '80-100',
            };
            $bands[$key]++;
        }

        return $bands;
    }

    /**
     * @param  Collection<int, Prediction>  $rows
     * @return list<array<string, mixed>>
     */
    private function yearLevels(Builder $students, Collection $rows): array
    {
        $levels = [];
        for ($year = 1; $year <= 4; $year++) {
            $inYear = $rows->filter(fn (Prediction $row): bool => (int) $row->year_level === $year)->values();
            $levels[] = [
                'year' => $year,
                'students' => (clone $students)->where('year_level', $year)->count(),
                'with_prediction' => $inYear->count(),
                'low' => $inYear->where('dropout_risk', 'low')->count(),
                'moderate' => $inYear->where('dropout_risk', 'moderate')->count(),
                'high' => $inYear->where('dropout_risk', 'high')->count(),
                'average_employability' => $inYear->isEmpty() ? null : round((float) $inYear->avg('employability_score'), 1),
            ];
        }

        return $levels;
    }

    /**
     * @param  Collection<int, Prediction>  $rows
     * @return list<array<string, mixed>>
     */
    private function trend(Collection $rows): array
    {
        $grouped = $rows->groupBy(
            fn (Prediction $row): string => $row->created_at?->timezone((string) config('app.timezone'))->format('Y-m') ?? 'unknown',
        )->sortKeys();

        $points = [];
        foreach ($grouped as $label => $group) {
            $points[] = [
                'label' => (string) $label,
                'average_employability' => round((float) $group->avg('employability_score'), 1),
                'low' => $group->where('dropout_risk', 'low')->count(),
                'moderate' => $group->where('dropout_risk', 'moderate')->count(),
                'high' => $group->where('dropout_risk', 'high')->count(),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, Prediction>  $rows
     * @return list<array<string, mixed>>
     */
    private function programs(Builder $students, Collection $rows): array
    {
        $programs = (clone $students)
            ->join('programs', 'programs.id', '=', 'students.program_id')
            ->leftJoin('departments', 'departments.id', '=', 'programs.department_id')
            ->select(
                'programs.id',
                'programs.code',
                'programs.name',
                'departments.id as department_id',
                'departments.name as department_name',
            )
            ->selectRaw('count(students.id) as student_count')
            ->groupBy('programs.id', 'programs.code', 'programs.name', 'departments.id', 'departments.name')
            ->orderBy('programs.code')
            ->get();

        $comparison = [];
        foreach ($programs as $program) {
            $matched = $rows->filter(fn (Prediction $row): bool => (int) $row->program_id === (int) $program->id)->values();
            $comparison[] = [
                'id' => (int) $program->id,
                'code' => (string) $program->code,
                'name' => (string) $program->name,
                'department_id' => $program->department_id !== null ? (int) $program->department_id : null,
                'department_name' => $program->department_name !== null ? (string) $program->department_name : null,
                'students' => (int) $program->student_count,
                'with_prediction' => $matched->count(),
                'average_employability' => $matched->isEmpty() ? null : round((float) $matched->avg('employability_score'), 1),
                'low' => $matched->where('dropout_risk', 'low')->count(),
                'moderate' => $matched->where('dropout_risk', 'moderate')->count(),
                'high' => $matched->where('dropout_risk', 'high')->count(),
            ];
        }

        return $comparison;
    }

    /**
     * @param  list<array<string, mixed>>  $programs
     * @return list<array<string, mixed>>
     */
    private function departmentSummary(array $programs): array
    {
        $groups = [];

        foreach ($programs as $program) {
            $key = (int) ($program['department_id'] ?? 0);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $key,
                    'name' => $program['department_name'] ?? 'No department',
                    'students' => 0,
                    'with_prediction' => 0,
                    'low' => 0,
                    'moderate' => 0,
                    'high' => 0,
                    'average_sum' => 0.0,
                    'average_weight' => 0,
                ];
            }

            $groups[$key]['students'] += (int) $program['students'];
            $groups[$key]['with_prediction'] += (int) $program['with_prediction'];
            $groups[$key]['low'] += (int) $program['low'];
            $groups[$key]['moderate'] += (int) $program['moderate'];
            $groups[$key]['high'] += (int) $program['high'];

            if ($program['average_employability'] !== null && (int) $program['with_prediction'] > 0) {
                $groups[$key]['average_sum'] += (float) $program['average_employability'] * (int) $program['with_prediction'];
                $groups[$key]['average_weight'] += (int) $program['with_prediction'];
            }
        }

        $summary = [];
        foreach ($groups as $group) {
            $summary[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'students' => $group['students'],
                'average_employability' => $group['average_weight'] === 0
                    ? null
                    : round($group['average_sum'] / $group['average_weight'], 1),
                'low' => $group['low'],
                'moderate' => $group['moderate'],
                'high' => $group['high'],
            ];
        }

        usort($summary, fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));

        return $summary;
    }

    /**
     * @param  array{low: int, moderate: int, high: int}  $risk
     * @param  Collection<int, Prediction>  $rows
     * @return array<string, mixed>
     */
    private function charts(array $risk, ?float $average, Collection $rows): array
    {
        $bands = $this->bands($rows);
        $trend = $this->trend($rows);
        $years = [];
        for ($year = 1; $year <= 4; $year++) {
            $inYear = $rows->filter(fn (Prediction $row): bool => (int) $row->year_level === $year);
            $years[] = [
                'low' => $inYear->where('dropout_risk', 'low')->count(),
                'moderate' => $inYear->where('dropout_risk', 'moderate')->count(),
                'high' => $inYear->where('dropout_risk', 'high')->count(),
            ];
        }

        $byProgram = $rows->groupBy(fn (Prediction $row): string => (string) $row->program_code)->sortKeys();

        return [
            'risk' => [
                'labels' => ['Low', 'Moderate', 'High'],
                'data' => [$risk['low'], $risk['moderate'], $risk['high']],
                'colors' => ['#15803D', '#D97706', '#B91C1C'],
            ],
            'bands' => [
                'labels' => array_keys($bands),
                'data' => array_values($bands),
            ],
            'trend' => [
                'labels' => array_column($trend, 'label'),
                'employability' => array_column($trend, 'average_employability'),
                'high' => array_column($trend, 'high'),
            ],
            'years' => [
                'labels' => ['Year 1', 'Year 2', 'Year 3', 'Year 4'],
                'low' => array_column($years, 'low'),
                'moderate' => array_column($years, 'moderate'),
                'high' => array_column($years, 'high'),
            ],
            'programs' => [
                'labels' => $byProgram->keys()->values()->all(),
                'averages' => $byProgram->map(fn (Collection $group): float => round((float) $group->avg('employability_score'), 1))->values()->all(),
            ],
            'has_average' => $average !== null,
        ];
    }
}
