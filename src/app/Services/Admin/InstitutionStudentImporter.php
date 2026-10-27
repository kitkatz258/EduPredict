<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstitutionStudentImporter
{
    public const YEAR_MESSAGE = 'Year level must be an integer from 1 to 4.';

    public function __construct(
        private readonly StudentAccountProvisioner $provisioner,
    ) {}

    /**
     * @return array{
     *     examined: int,
     *     valid: int,
     *     imported: int,
     *     skipped: int,
     *     errors: list<array{row: int, message: string}>,
     *     skipped_rows: list<array{row: int, message: string}>
     * }
     */
    public function import(UploadedFile $file, User $actor, ?string $ip = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return $this->emptyResult([['row' => 0, 'message' => 'Could not read the CSV file.']]);
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return $this->emptyResult([['row' => 1, 'message' => 'The CSV file has no header row.']]);
        }

        $normalized = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $expected = ['student_number', 'last_name', 'first_name', 'program_code', 'year_level'];

        foreach ($expected as $column) {
            if (! in_array($column, $normalized, true)) {
                fclose($handle);

                return $this->emptyResult([['row' => 1, 'message' => "Missing column: {$column}"]]);
            }
        }

        $indexes = array_flip($normalized);
        $errors = [];
        $skippedRows = [];
        $imported = 0;
        $examined = 0;
        $seen = [];
        $seenEmails = [];
        $rowNumber = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($this->rowEmpty($row)) {
                    continue;
                }

                $examined++;
                $parsed = $this->parseRow($row, $indexes);
                $programId = null;
                $problem = $this->rowProblem($parsed, $seen, $seenEmails, $programId);
                if ($problem !== null) {
                    if ($problem['skipped']) {
                        $skippedRows[] = ['row' => $rowNumber, 'message' => $problem['message']];
                    } else {
                        $errors[] = ['row' => $rowNumber, 'message' => $problem['message']];
                    }

                    continue;
                }

                $seen[$parsed['student_number']] = true;
                if ($parsed['email'] !== null) {
                    $seenEmails[Str::lower($parsed['email'])] = true;
                }

                try {
                    DB::transaction(function () use ($parsed, $programId, $actor, $ip): void {
                        $record = InstitutionStudent::query()->create([
                            'student_number' => $parsed['student_number'],
                            'last_name' => $parsed['last_name'],
                            'first_name' => $parsed['first_name'],
                            'program_id' => $programId,
                            'year_level' => (int) $parsed['year_level_raw'],
                            'email' => $parsed['email'],
                            'birthdate' => $parsed['birthdate'],
                            'is_registered' => false,
                        ]);
                        $this->provisioner->provision($record, $actor, $parsed['email'], $ip);
                    });
                    $imported++;
                } catch (ValidationException $exception) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => $exception->validator->errors()->first() ?: 'Could not create the student account.',
                    ];
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $imported = 0;
            $errors[] = ['row' => $rowNumber, 'message' => 'Import failed and no rows from this file were saved.'];
        }

        fclose($handle);

        $result = [
            'examined' => $examined,
            'valid' => $imported + count($skippedRows),
            'imported' => $imported,
            'skipped' => count($skippedRows),
            'errors' => $errors,
            'skipped_rows' => $skippedRows,
        ];

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'institution_students_imported',
            'subject_type' => InstitutionStudent::class,
            'subject_id' => null,
            'meta' => [
                'examined' => $result['examined'],
                'valid' => $result['valid'],
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'error_count' => count($errors),
            ],
            'ip' => $ip,
        ]);

        return $result;
    }

    /**
     * @param  array{student_number: string, last_name: string, first_name: string, program_id: int, year_level: int, email?: string|null, birthdate?: string|null}  $data
     */
    public function create(array $data, User $actor, ?string $ip = null): InstitutionStudent
    {
        $record = DB::transaction(function () use ($data, $actor, $ip): InstitutionStudent {
            $this->assertAvailableNumber($data['student_number']);
            $program = $this->pilotProgram($data['program_id']);

            $record = InstitutionStudent::query()->create([
                'student_number' => trim($data['student_number']),
                'last_name' => trim($data['last_name']),
                'first_name' => trim($data['first_name']),
                'program_id' => $program->id,
                'year_level' => $this->yearLevel($data['year_level']),
                'email' => $this->blankToNull($data['email'] ?? null),
                'birthdate' => $this->blankToNull($data['birthdate'] ?? null),
                'is_registered' => false,
            ]);
            $this->provisioner->provision($record, $actor, $record->email, $ip);

            return $record->refresh();
        });

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'eligible_student_saved',
            'subject_type' => InstitutionStudent::class,
            'subject_id' => $record->id,
            'meta' => ['student_number' => $record->student_number, 'mode' => 'create'],
            'ip' => $ip,
        ]);

        return $record;
    }

    /**
     * @param  array{student_number?: string, last_name?: string, first_name?: string, program_id?: int, year_level: int, email?: string|null, birthdate?: string|null}  $data
     */
    public function update(InstitutionStudent $record, array $data, User $actor, ?string $ip = null): InstitutionStudent
    {
        DB::transaction(function () use ($record, $data): void {
            if ($record->is_registered) {
                $record->update([
                    'year_level' => $this->yearLevel($data['year_level']),
                    'email' => $this->blankToNull($data['email'] ?? null),
                    'birthdate' => $this->blankToNull($data['birthdate'] ?? null),
                ]);

                return;
            }

            $number = trim((string) ($data['student_number'] ?? ''));
            $this->assertAvailableNumber($number, $record->id);
            $program = $this->pilotProgram((int) ($data['program_id'] ?? 0));
            $record->update([
                'student_number' => $number,
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'program_id' => $program->id,
                'year_level' => $this->yearLevel($data['year_level']),
                'email' => $this->blankToNull($data['email'] ?? null),
                'birthdate' => $this->blankToNull($data['birthdate'] ?? null),
            ]);
        });

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'eligible_student_saved',
            'subject_type' => InstitutionStudent::class,
            'subject_id' => $record->id,
            'meta' => ['student_number' => $record->student_number, 'mode' => 'update', 'registered' => $record->is_registered],
            'ip' => $ip,
        ]);

        return $record->refresh();
    }

    /**
     * @param  list<array{row: int, message: string}>  $errors
     * @return array{examined: int, valid: int, imported: int, skipped: int, errors: list<array{row: int, message: string}>, skipped_rows: list<array{row: int, message: string}>}
     */
    private function emptyResult(array $errors): array
    {
        return [
            'examined' => 0,
            'valid' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => $errors,
            'skipped_rows' => [],
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return strtolower(trim($value));
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $indexes
     * @return array{student_number: string, last_name: string, first_name: string, program_code: string, year_level_raw: string, email: ?string, birthdate: ?string}
     */
    private function parseRow(array $row, array $indexes): array
    {
        $email = isset($indexes['email']) ? trim((string) ($row[$indexes['email']] ?? '')) : '';
        $birthdate = isset($indexes['birthdate']) ? trim((string) ($row[$indexes['birthdate']] ?? '')) : '';

        return [
            'student_number' => trim((string) ($row[$indexes['student_number']] ?? '')),
            'last_name' => trim((string) ($row[$indexes['last_name']] ?? '')),
            'first_name' => trim((string) ($row[$indexes['first_name']] ?? '')),
            'program_code' => strtoupper(trim((string) ($row[$indexes['program_code']] ?? ''))),
            'year_level_raw' => trim((string) ($row[$indexes['year_level']] ?? '')),
            'email' => $email !== '' ? $email : null,
            'birthdate' => $birthdate !== '' ? $birthdate : null,
        ];
    }

    /**
     * @param  array{student_number: string, last_name: string, first_name: string, program_code: string, year_level_raw: string, email: ?string, birthdate: ?string}  $parsed
     * @param  array<string, true>  $seen
     * @param  array<string, true>  $seenEmails
     * @return array{skipped: bool, message: string}|null
     */
    private function rowProblem(array $parsed, array $seen, array $seenEmails, ?int &$programId): ?array
    {
        if ($parsed['student_number'] === '' || $parsed['last_name'] === '' || $parsed['first_name'] === '' || $parsed['program_code'] === '') {
            return ['skipped' => false, 'message' => 'Required fields are missing.'];
        }

        if (! preg_match('/^[1-4]$/', $parsed['year_level_raw'])) {
            return ['skipped' => false, 'message' => self::YEAR_MESSAGE];
        }

        if ($parsed['birthdate'] !== null && strtotime($parsed['birthdate']) === false) {
            return ['skipped' => false, 'message' => 'Birthdate is not a valid date.'];
        }

        if ($parsed['email'] !== null && ! filter_var($parsed['email'], FILTER_VALIDATE_EMAIL)) {
            return ['skipped' => false, 'message' => 'Email is not valid.'];
        }

        $program = Program::query()->where('code', $parsed['program_code'])->first();
        if ($program === null) {
            return ['skipped' => false, 'message' => "Unknown program code {$parsed['program_code']}."];
        }

        if (! $program->is_active) {
            return ['skipped' => false, 'message' => "Program {$parsed['program_code']} is outside the CLAS pilot scope."];
        }

        if (
            isset($seen[$parsed['student_number']])
            || InstitutionStudent::query()->where('student_number', $parsed['student_number'])->exists()
            || Student::query()->where('student_number', $parsed['student_number'])->exists()
        ) {
            return ['skipped' => true, 'message' => "Skipped duplicate student number {$parsed['student_number']}."];
        }

        if ($parsed['email'] !== null) {
            $emailKey = Str::lower($parsed['email']);
            if (isset($seenEmails[$emailKey]) || User::query()->whereRaw('LOWER(email) = ?', [$emailKey])->exists()) {
                return ['skipped' => false, 'message' => 'That email is already used by another account.'];
            }
        }

        $programId = $program->id;

        return null;
    }

    private function assertAvailableNumber(string $number, ?int $ignoreId = null): void
    {
        $number = trim($number);
        $query = InstitutionStudent::query()->where('student_number', $number);
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'studentNumber' => "Student number {$number} is already on the eligible list.",
            ]);
        }

        if (Student::query()->where('student_number', $number)->exists()) {
            throw ValidationException::withMessages([
                'studentNumber' => "Student number {$number} already has a login. Duplicate accounts are not created.",
            ]);
        }
    }

    private function pilotProgram(int $programId): Program
    {
        $program = Program::query()->find($programId);
        if ($program === null || ! $program->is_active) {
            throw ValidationException::withMessages([
                'programId' => 'Choose an active CLAS program.',
            ]);
        }

        return $program;
    }

    private function yearLevel(mixed $year): int
    {
        $raw = is_string($year) ? trim($year) : $year;
        $valid = (is_int($raw) && $raw >= 1 && $raw <= 4)
            || (is_string($raw) && preg_match('/^[1-4]$/', $raw) === 1);

        if (! $valid) {
            throw ValidationException::withMessages([
                'yearLevel' => self::YEAR_MESSAGE,
            ]);
        }

        return (int) $raw;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
