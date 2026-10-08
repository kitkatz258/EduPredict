<?php

declare(strict_types=1);

namespace App\Services\Academic;

use Illuminate\Support\Facades\DB;

/**
 * Aligns the academic structure with the CLAS pilot scope.
 *
 * Safe to run repeatedly and against preserved data: rows are updated in
 * place (ids and foreign keys are kept) and nothing is deleted. Colleges and
 * programs outside the pilot are only marked inactive. Uses the query builder
 * (not Eloquent models) because it also runs inside a migration.
 */
final class ClasStructureSynchronizer
{
    public const COLLEGE_CODE = 'CLAS';

    public const COLLEGE_NAME = 'College of Liberal Arts and Sciences';

    /** Placeholder college code from the M1 seed that becomes CLAS. */
    private const LEGACY_COLLEGE_CODE = 'CLA';

    /**
     * Department grouping is an assumption pending confirmation from CLAS.
     *
     * @var list<array{code: string, name: string, programs: list<array{code: string, name: string}>}>
     */
    public const DEPARTMENTS = [
        [
            'code' => 'CLAS-COMM',
            'name' => 'Communication',
            'programs' => [
                ['code' => 'ABCOMM', 'name' => 'BA Communication'],
            ],
        ],
        [
            'code' => 'CLAS-PA',
            'name' => 'Public Administration',
            'programs' => [
                ['code' => 'BPA', 'name' => 'Bachelor of Public Administration'],
            ],
        ],
        [
            'code' => 'CLAS-CS',
            'name' => 'Computer Studies',
            'programs' => [
                ['code' => 'BSCS', 'name' => 'Bachelor of Science in Computer Science'],
                ['code' => 'BSEMC', 'name' => 'Bachelor of Science in Entertainment and Multimedia Computing'],
                ['code' => 'BSIS', 'name' => 'Bachelor of Science in Information Systems'],
                ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
            ],
        ],
        [
            'code' => 'CLAS-MATH',
            'name' => 'Mathematics',
            'programs' => [
                ['code' => 'BSMATH', 'name' => 'Bachelor of Science in Mathematics'],
            ],
        ],
        [
            'code' => 'CLAS-PSY',
            'name' => 'Psychology',
            'programs' => [
                ['code' => 'BSPSYCH', 'name' => 'Bachelor of Science in Psychology'],
            ],
        ],
    ];

    public function sync(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $clasId = $this->clasCollegeId($now);
            $previousCollegeIds = DB::table('programs')->distinct()->pluck('college_id')->map(fn ($id): int => (int) $id)->all();
            $pilotProgramIds = [];

            foreach (self::DEPARTMENTS as $department) {
                $departmentId = $this->upsert('departments', ['code' => $department['code']], [
                    'college_id' => $clasId,
                    'name' => $department['name'],
                    'is_active' => true,
                ], $now);

                foreach ($department['programs'] as $program) {
                    $pilotProgramIds[] = $this->upsert('programs', ['code' => $program['code']], [
                        'college_id' => $clasId,
                        'department_id' => $departmentId,
                        'name' => $program['name'],
                        'is_active' => true,
                    ], $now);
                }
            }

            DB::table('programs')->whereNotIn('id', $pilotProgramIds)->update(['is_active' => false, 'updated_at' => $now]);
            DB::table('colleges')->where('id', '!=', $clasId)->update(['is_active' => false, 'updated_at' => $now]);
            DB::table('departments')->where('college_id', '!=', $clasId)->update(['is_active' => false, 'updated_at' => $now]);

            $this->moveDeansOfEmptiedColleges($previousCollegeIds, $clasId, $now);
            $this->backfillDepartmentHeads($now);
        });
    }

    private function clasCollegeId(\DateTimeInterface $now): int
    {
        $existing = DB::table('colleges')->where('code', self::COLLEGE_CODE)->value('id')
            ?? DB::table('colleges')->where('code', self::LEGACY_COLLEGE_CODE)->value('id');

        if ($existing !== null) {
            DB::table('colleges')->where('id', $existing)->update([
                'code' => self::COLLEGE_CODE,
                'name' => self::COLLEGE_NAME,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            return (int) $existing;
        }

        return (int) DB::table('colleges')->insertGetId([
            'code' => self::COLLEGE_CODE,
            'name' => self::COLLEGE_NAME,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * A dean whose college lost every program to CLAS follows those programs.
     *
     * @param  list<int>  $previousCollegeIds
     */
    private function moveDeansOfEmptiedColleges(array $previousCollegeIds, int $clasId, \DateTimeInterface $now): void
    {
        $stillUsed = DB::table('programs')->distinct()->pluck('college_id')->map(fn ($id): int => (int) $id)->all();
        $emptied = array_values(array_diff($previousCollegeIds, $stillUsed, [$clasId]));

        if ($emptied === []) {
            return;
        }

        DB::table('users')
            ->where('role', 'dean')
            ->whereIn('college_id', $emptied)
            ->update(['college_id' => $clasId, 'updated_at' => $now]);
    }

    /**
     * Program-scoped heads keep their program (no widening of access) and gain
     * the matching department and college.
     */
    private function backfillDepartmentHeads(\DateTimeInterface $now): void
    {
        $heads = DB::table('users')
            ->where('role', 'department_head')
            ->whereNull('department_id')
            ->whereNotNull('program_id')
            ->get(['id', 'program_id']);

        foreach ($heads as $head) {
            $program = DB::table('programs')->where('id', $head->program_id)->first(['college_id', 'department_id']);
            if ($program === null) {
                continue;
            }

            DB::table('users')->where('id', $head->id)->update([
                'department_id' => $program->department_id,
                'college_id' => $program->college_id,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $table, array $keys, array $values, \DateTimeInterface $now): int
    {
        $id = DB::table($table)->where($keys)->value('id');

        if ($id !== null) {
            DB::table($table)->where('id', $id)->update([...$values, 'updated_at' => $now]);

            return (int) $id;
        }

        return (int) DB::table($table)->insertGetId([...$keys, ...$values, 'created_at' => $now, 'updated_at' => $now]);
    }
}
