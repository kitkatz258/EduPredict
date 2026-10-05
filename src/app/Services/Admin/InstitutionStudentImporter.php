<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class InstitutionStudentImporter
{
    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, User $actor, ?string $ip = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return ['imported' => 0, 'errors' => [['row' => 0, 'message' => 'Could not read the CSV file.']]];
        }

        $header = fgetcsv($handle);
        $errors = [];
        $imported = 0;
        $rowNumber = 1;

        $expected = ['student_number', 'last_name', 'first_name', 'program_code', 'year_level'];
        $normalized = array_map(fn ($value) => strtolower(trim((string) $value)), $header ?: []);

        foreach ($expected as $column) {
            if (! in_array($column, $normalized, true)) {
                fclose($handle);

                return ['imported' => 0, 'errors' => [['row' => 1, 'message' => "Missing column: {$column}"]]];
            }
        }

        $indexes = array_flip($normalized);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($this->rowEmpty($row)) {
                    continue;
                }

                $number = trim((string) ($row[$indexes['student_number']] ?? ''));
                $last = trim((string) ($row[$indexes['last_name']] ?? ''));
                $first = trim((string) ($row[$indexes['first_name']] ?? ''));
                $code = strtoupper(trim((string) ($row[$indexes['program_code']] ?? '')));
                $year = (int) ($row[$indexes['year_level']] ?? 0);
                $email = isset($indexes['email']) ? trim((string) $row[$indexes['email']]) : null;
                $birthdate = isset($indexes['birthdate']) ? trim((string) $row[$indexes['birthdate']]) : null;

                if ($number === '' || $last === '' || $first === '' || $code === '') {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Required fields are missing.'];
                    continue;
                }

                $program = Program::query()->where('code', $code)->first();
                if ($program === null) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Unknown program code {$code}."];
                    continue;
                }

                if ($year < 1 || $year > 6) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Year level must be between 1 and 6.'];
                    continue;
                }

                if (InstitutionStudent::query()->where('student_number', $number)->exists()) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Duplicate student number {$number}."];
                    continue;
                }

                InstitutionStudent::query()->create([
                    'student_number' => $number,
                    'last_name' => $last,
                    'first_name' => $first,
                    'program_id' => $program->id,
                    'year_level' => $year,
                    'email' => $email !== '' ? $email : null,
                    'birthdate' => $birthdate !== '' ? $birthdate : null,
                    'is_registered' => false,
                ]);
                $imported++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = ['row' => $rowNumber, 'message' => 'Import failed: '.$e->getMessage()];
        }

        fclose($handle);

        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => 'institution_students_imported',
            'subject_type' => InstitutionStudent::class,
            'subject_id' => null,
            'meta' => ['imported' => $imported, 'error_count' => count($errors)],
            'ip' => $ip,
        ]);

        return ['imported' => $imported, 'errors' => $errors];
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
