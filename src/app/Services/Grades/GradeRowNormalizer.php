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
        $rawName = trim((string) ($raw['subject_name'] ?? $raw['description'] ?? ''));
        $name = $this->stripInstructorName($rawName);
        $units = trim((string) ($raw['units'] ?? ''));
        $midterm = $this->scale->normalizeGradeToken($raw['midterm_grade'] ?? $raw['midterm'] ?? null);
        $finalExam = $this->scale->normalizeGradeToken($raw['final_exam_grade'] ?? $raw['final'] ?? null);
        $finalGrade = $this->scale->normalizeGradeToken($raw['final_grade'] ?? $raw['final_grade'] ?? null) ?? '';
        $remarks = $this->scale->remarksFor($finalGrade, (string) ($raw['remarks'] ?? ''));
        $gradeIsStatus = $this->scale->isIncomplete($finalGrade) || $this->scale->isDropped($finalGrade);
        $remarksIsStatus = $this->scale->isIncomplete($remarks) || in_array($remarks, ['DROPPED', 'WITHDRAWN'], true);
        if ($gradeIsStatus || ($this->scale->isNumericGrade($finalGrade) && $remarksIsStatus)) {
            $remarks = $this->scale->remarksFor($finalGrade);
        }
        $warnings = [];

        if ($code === '' || ! preg_match($this->scale->subjectCodePattern(), $code)) {
            $warnings[] = 'Subject code does not match the expected pattern.';
        }
        if ($name !== $rawName) {
            $warnings[] = 'An instructor name was removed from the description. Check the subject name.';
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
        $isIncomplete = $this->scale->isIncomplete($finalGrade) || $this->scale->isIncomplete($remarks);

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
            isIncomplete: $isIncomplete,
        );
    }

    /**
     * Portal copies can carry the instructor into the description cell. Removes a
     * merged "FACULTY, NAME SECTION" tail, a trailing honorific-led name
     * ("Prof. Juan Cruz"), or a trailing "SURNAME, Given" name. A single all-caps
     * word after a comma ("PURPOSIVE COMMUNICATION, ORAL") stays, because it
     * cannot be told apart from the subject title.
     */
    public function stripInstructorName(string $description): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $description) ?? $description);

        // A trailing UCC section ("BSIS 3-A-SOUTH") means Faculty and Section were merged into the cell.
        $section = '/(?:\s+\p{Lu}{2,8})?\s+\d[\p{Lu}\d]*(?:-[\p{Lu}\d]+)+$/u';
        if (preg_match($section, $clean)) {
            $withoutSection = trim(preg_replace($section, '', $clean) ?? $clean);
            $withoutFaculty = $this->stripFacultySuffix($withoutSection);
            if ($withoutFaculty !== $withoutSection) {
                $clean = $withoutFaculty;
            }
        }

        $honorific = '/\s*(?:[-–|\/(]\s*)?\b(?:Prof(?:essor)?|Dr|Engr|Atty|Mr|Mrs|Ms|Instructor|Inst)\.?\s+\p{Lu}[\p{L}.\'\-\s,]*\)?$/u';
        $clean = trim(preg_replace($honorific, '', $clean) ?? $clean);

        if ($clean === mb_strtoupper($clean)) {
            if ($this->hasPersonShapedCommaTail($clean)) {
                $clean = $this->stripFacultySuffix($clean);
            }
        } else {
            $surnameFirst = '/\s*(?:[-–|\/(]\s*)?\b\p{Lu}{2,}(?:\s+\p{Lu}{2,})*,\s*\p{Lu}[\p{L}\'\-]*(?:\s+\p{Lu}[\p{L}\'\-]*\.?)*\)?$/u';
            $clean = trim(preg_replace($surnameFirst, '', $clean) ?? $clean);
        }

        return $clean;
    }

    /**
     * True when the text ends in a person-shaped "SURNAME, Given …" tail.
     * One trailing word is not enough: "COMMUNICATION, ORAL" is a subject title.
     */
    private function hasPersonShapedCommaTail(string $text): bool
    {
        return (bool) preg_match('/,\s*\p{L}[\p{L}\'\-.]*(?:(?:\s+\p{L}[\p{L}\'\-.]*)+|\s*\.)\s*$/u', $text);
    }

    /**
     * Removes a trailing "SURNAME, Given M." (or TBA) from text known to end with the
     * Faculty column. The surname is the word before the last comma plus any
     * leading particles such as DELA, DE LOS, or STA.
     */
    public function stripFacultySuffix(string $text): string
    {
        $text = trim($text);
        if (preg_match('/\s+TBA\.?$/i', $text)) {
            return trim(preg_replace('/\s+TBA\.?$/i', '', $text) ?? $text);
        }

        $comma = mb_strrpos($text, ',');
        if ($comma === false) {
            return $text;
        }

        $before = preg_split('/\s+/u', trim(mb_substr($text, 0, $comma))) ?: [];
        if (count($before) < 2) {
            return $text;
        }

        $particles = ['DE', 'DEL', 'DELA', 'DELOS', 'DELAS', 'LOS', 'LAS', 'LA', 'DI', 'SAN', 'STA', 'STA.', 'STO', 'STO.', 'VDA', 'VDA.', 'VAN', 'VON', 'MC', 'MAC'];
        $start = count($before) - 1;
        while ($start > 1 && in_array(mb_strtoupper($before[$start - 1]), $particles, true)) {
            $start--;
        }

        return trim(implode(' ', array_slice($before, 0, $start)));
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
