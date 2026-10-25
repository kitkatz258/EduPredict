<?php

namespace App\Services\Grades;

final class GradeScale
{
    /**
     * @return list<float>
     */
    public function numericScale(): array
    {
        return array_map('floatval', config('edupredict.grades.numeric_scale', [1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, 5.00]));
    }

    public function passingMax(): float
    {
        return (float) config('edupredict.grades.passing_max', 3.00);
    }

    public function failing(): float
    {
        return (float) config('edupredict.grades.failing', 5.00);
    }

    /**
     * @return list<string>
     */
    public function gpaExcludedPrefixes(): array
    {
        return array_map('strtoupper', config('edupredict.grades.gpa_excluded_prefixes', ['NSTP']));
    }

    public function subjectCodePattern(): string
    {
        return config('edupredict.grades.subject_code_pattern', '/^[A-Z]{2,8}\s?\d{1,3}[A-Z]?$/');
    }

    public function normalizeGradeToken(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $token = strtoupper(trim($raw));
        if ($token === '' || $token === '-' || $token === 'N/A' || $token === 'NA') {
            return null;
        }

        $compact = strtoupper((string) preg_replace('/\s+/', '', $token));
        if ($this->isIncomplete($compact)) {
            return 'INC';
        }
        if ($this->isDropped($compact)) {
            return in_array($compact, ['W', 'WITHDRAWN'], true) ? 'W' : 'DRP';
        }

        $token = str_replace([',', ' '], ['.', ''], $token);
        $token = str_replace(['L.', 'I.', '|.'], ['1.', '1.', '1.'], $token);
        if (preg_match('/^[LI|](?=\d|\.)/', $token)) {
            $token = preg_replace('/^[LI|]/', '1', $token) ?? $token;
        }

        if (is_numeric($token)) {
            return number_format((float) $token, 2, '.', '');
        }

        return $token;
    }

    public function isIncomplete(string $token): bool
    {
        $token = $this->compactToken($token);

        return in_array($token, array_map('strtoupper', config('edupredict.grades.incomplete_tokens', ['INC', 'INCOMPLETE'])), true);
    }

    public function isDropped(string $token): bool
    {
        $token = $this->compactToken($token);

        return in_array($token, array_map('strtoupper', config('edupredict.grades.dropped_tokens', ['DRP', 'W', 'WITHDRAWN', 'DROPPED'])), true);
    }

    /**
     * Canonical status remark for a grade token or a remarks cell.
     * Free-text notes return null so a justified remark can be kept.
     */
    public function statusRemark(?string $token): ?string
    {
        $compact = $this->compactToken((string) $token);
        if ($compact === '') {
            return null;
        }
        if ($this->isIncomplete($compact)) {
            return 'INCOMPLETE';
        }
        if ($this->isDropped($compact)) {
            return in_array($compact, ['W', 'WITHDRAWN'], true) ? 'WITHDRAWN' : 'DROPPED';
        }
        if (in_array($compact, ['PASSED', 'FAILED'], true)) {
            return $compact;
        }

        return null;
    }

    private function compactToken(string $token): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', trim($token)));
    }

    public function isNumericGrade(string $token): bool
    {
        if (! is_numeric($token)) {
            return false;
        }

        $value = round((float) $token, 2);

        foreach ($this->numericScale() as $allowed) {
            if (abs($value - $allowed) < 0.001) {
                return true;
            }
        }

        return false;
    }

    public function isFailed(?string $finalGrade, ?string $remarks): bool
    {
        $grade = $this->normalizeGradeToken($finalGrade) ?? '';
        $remark = strtoupper(trim((string) $remarks));

        if ($this->isIncomplete($grade) || $this->isIncomplete($remark)) {
            return false;
        }
        if ($this->isDropped($grade) || $this->isDropped($remark)) {
            return false;
        }
        if ($remark === 'FAILED') {
            return true;
        }

        return is_numeric($grade) && abs((float) $grade - $this->failing()) < 0.001;
    }

    public function remarksFor(?string $finalGrade, ?string $existing = null): string
    {
        $existing = strtoupper(trim((string) $existing));
        if ($existing !== '') {
            if ($this->isIncomplete($existing)) {
                return 'INCOMPLETE';
            }
            if ($this->isDropped($existing)) {
                return $existing === 'W' || $existing === 'WITHDRAWN' ? 'WITHDRAWN' : 'DROPPED';
            }
            if (in_array($existing, ['PASSED', 'FAILED', 'INCOMPLETE', 'DROPPED', 'WITHDRAWN'], true)) {
                return $existing;
            }
        }

        $grade = $this->normalizeGradeToken($finalGrade) ?? '';
        if ($this->isIncomplete($grade)) {
            return 'INCOMPLETE';
        }
        if ($this->isDropped($grade)) {
            return $grade === 'W' ? 'WITHDRAWN' : 'DROPPED';
        }
        if ($this->isFailed($grade, null)) {
            return 'FAILED';
        }
        if (is_numeric($grade) && (float) $grade <= $this->passingMax()) {
            return 'PASSED';
        }

        return $existing !== '' ? $existing : '';
    }

    public function excludesFromGpa(string $subjectCode): bool
    {
        $code = strtoupper(trim($subjectCode));
        foreach ($this->gpaExcludedPrefixes() as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * INC and dropped grades have no numeric value and stay out of the GWA.
     */
    public function gpaNumericValue(string $finalGrade): ?float
    {
        $token = $this->normalizeGradeToken($finalGrade);
        if ($token === null || $this->isIncomplete($token) || $this->isDropped($token)) {
            return null;
        }
        if (is_numeric($token)) {
            return (float) $token;
        }

        return null;
    }
}
