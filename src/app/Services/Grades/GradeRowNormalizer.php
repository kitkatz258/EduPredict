<?php

namespace App\Services\Grades;

final class GradeRowNormalizer
{
    public function __construct(private GradeScale $scale) {}

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $allCodes
     */
    public function normalize(array $raw, array $allCodes = []): ParsedGradeRow
    {
        $code = $this->normalizeCode((string) ($raw['subject_code'] ?? $raw['code'] ?? ''));
        $name = trim((string) ($raw['subject_name'] ?? $raw['description'] ?? ''));
        $units = trim((string) ($raw['units'] ?? ''));
        $midterm = $this->scale->normalizeGradeToken($raw['midterm_grade'] ?? $raw['midterm'] ?? null);
        $finalExam = $this->scale->normalizeGradeToken($raw['final_exam_grade'] ?? $raw['final'] ?? null);
        $finalGrade = $this->scale->normalizeGradeToken($raw['final_grade'] ?? $raw['final_grade'] ?? null) ?? '';
        $remarks = $this->scale->remarksFor($finalGrade, (string) ($raw['remarks'] ?? ''));
        $warnings = [];

        if ($code === '' || ! preg_match($this->scale->subjectCodePattern(), $code)) {
            $warnings[] = 'Subject code does not match the expected pattern.';
        }
        if ($name === '') {
            $warnings[] = 'Subject description is missing.';
        }

        $unitsValue = is_numeric($units) ? (float) $units : null;
        if ($unitsValue === null || $unitsValue < 0 || $unitsValue > 9) {
            $warnings[] = 'Units must be numeric between 0 and 9.';
        }

        if ($finalGrade === '') {
            $warnings[] = 'Final grade is missing.';
        } elseif (! $this->scale->isNumericGrade($finalGrade) && ! $this->scale->isIncomplete($finalGrade) && ! $this->scale->isDropped($finalGrade)) {
            $warnings[] = 'Final grade is not on the configured scale.';
        }

        if ($this->scale->isNumericGrade($finalGrade)) {
            $expected = $this->scale->remarksFor($finalGrade);
            if ($remarks !== '' && $expected !== '' && $remarks !== $expected && ! ($this->scale->isIncomplete($remarks) || $this->scale->isDropped($remarks))) {
                $warnings[] = 'Remarks are not consistent with the final grade.';
            }
        }

        $duplicates = array_filter($allCodes, fn (string $other): bool => strtoupper($other) === strtoupper($code));
        if ($code !== '' && count($duplicates) > 1) {
            $warnings[] = 'Duplicate subject code in this term.';
        }

        $isFailed = $this->scale->isFailed($finalGrade, $remarks);

        return new ParsedGradeRow(
            subjectCode: $code,
            subjectName: $name,
            units: $units,
            midtermGrade: $midterm,
            finalExamGrade: $finalExam,
            finalGrade: $finalGrade,
            remarks: $remarks,
            isFailed: $isFailed,
            needsReview: $warnings !== [],
            warnings: $warnings,
            isMajorSubject: (bool) ($raw['is_major_subject'] ?? false),
        );
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(trim(preg_replace('/\s+/', ' ', $code) ?? $code));
        $code = str_replace(['PROO', 'CCSHO', 'CCSH0'], ['PR 00', 'CCS 10', 'CCS 10'], $code);

        if (preg_match('/^([A-Z]{2,8})(\d{1,3}[A-Z]?)$/', $code, $match)) {
            return $match[1].' '.$match[2];
        }

        return $code;
    }
}
