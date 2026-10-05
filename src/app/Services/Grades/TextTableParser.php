<?php

namespace App\Services\Grades;

final class TextTableParser
{
    public function __construct(
        private GradeRowNormalizer $normalizer,
        private GradeScale $scale,
    ) {}

    public function parse(string $text, string $source = 'pasted'): ParsedGradeReport
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_values(array_filter(array_map('rtrim', explode("\n", $text)), fn (string $line): bool => trim($line) !== ''));

        $meta = $this->extractMeta($text);
        $headerIndex = $this->findHeaderIndex($lines);

        $rawRows = [];
        if ($headerIndex !== null) {
            $delimiter = str_contains($lines[$headerIndex], "\t") ? "\t" : null;
            $columns = $this->mapHeader($lines[$headerIndex], $delimiter);
            for ($i = $headerIndex + 1; $i < count($lines); $i++) {
                if ($this->isFooterLine($lines[$i])) {
                    continue;
                }
                $parsed = $this->rowFromHeader($lines[$i], $lines[$headerIndex], $columns, $delimiter);
                if ($parsed !== null) {
                    $rawRows[] = $parsed;
                }
            }
        }

        if ($rawRows === []) {
            foreach ($lines as $line) {
                if ($this->isFooterLine($line) || $this->looksLikeHeader($line)) {
                    continue;
                }
                $fallback = $this->fallbackRow($line);
                if ($fallback !== null) {
                    $rawRows[] = $fallback;
                }
            }
        }

        $codes = array_map(fn (array $row): string => $this->normalizer->normalizeCode((string) ($row['subject_code'] ?? '')), $rawRows);
        $rows = array_map(fn (array $row): ParsedGradeRow => $this->normalizer->normalize($row, $codes), $rawRows);

        $warnings = [];
        if ($rows === []) {
            $warnings[] = 'No subject rows were detected. You can enter grades manually.';
        }

        return new ParsedGradeReport(
            source: $source,
            rows: $rows,
            program: $meta['program'],
            schoolYear: $meta['school_year'],
            semester: $meta['semester'],
            detectedGpa: $meta['gpa'],
            warnings: $warnings,
        );
    }

    /**
     * @return array{program: ?string, school_year: ?string, semester: ?string, gpa: ?float}
     */
    public function extractMeta(string $text): array
    {
        $meta = ['program' => null, 'school_year' => null, 'semester' => null, 'gpa' => null];

        if (preg_match('/School\s*Year\s*and\s*Semester:\s*(\d{4})\s*-\s*(\d{4})\s*\|\s*([A-Za-z]+)/i', $text, $match)) {
            $meta['school_year'] = $match[1].'-'.$match[2];
            $meta['semester'] = ucfirst(strtolower($match[3]));
        } elseif (preg_match('/\b(20\d{2})\s*-\s*(20\d{2})\b/', $text, $match)) {
            $meta['school_year'] = $match[1].'-'.$match[2];
        }

        if ($meta['semester'] === null && preg_match('/\b(First|Second|Midyear|Summer)\b/i', $text, $match)) {
            $meta['semester'] = ucfirst(strtolower($match[1]));
        }

        if (preg_match('/Program:\s*(.+)/i', $text, $match)) {
            $meta['program'] = trim(preg_split('/\s{2,}|School/i', $match[1])[0] ?? $match[1]);
        }

        if (preg_match('/\b(?:GPA|GWA)\s*[:=]?\s*(\d+\.\d{2})/i', $text, $match)) {
            $meta['gpa'] = (float) $match[1];
        }

        return $meta;
    }

    /**
     * @param  list<string>  $lines
     */
    private function findHeaderIndex(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if ($this->looksLikeHeader($line)) {
                return $index;
            }
        }

        return null;
    }

    private function looksLikeHeader(string $line): bool
    {
        $hay = strtolower($line);

        return str_contains($hay, 'subject') && (str_contains($hay, 'code') || str_contains($hay, 'description') || str_contains($hay, 'units'));
    }

    private function isFooterLine(string $line): bool
    {
        $hay = strtolower($line);

        return str_contains($hay, 'gpa') || str_contains($hay, 'gwa') || str_contains($hay, 'total units') || str_starts_with($hay, 'program:');
    }

    /**
     * @return array<int, string>
     */
    private function mapHeader(string $header, ?string $delimiter): array
    {
        $parts = $this->split($header, $delimiter);
        $map = [];
        foreach ($parts as $index => $part) {
            $key = $this->headerKey($part);
            if ($key !== null) {
                $map[$index] = $key;
            }
        }

        return $map;
    }

    private function headerKey(string $part): ?string
    {
        $hay = strtolower(trim(preg_replace('/[^a-z]+/i', ' ', $part) ?? $part));
        if ($hay === '') {
            return null;
        }
        if (str_contains($hay, 'faculty') || str_contains($hay, 'section') || $hay === 'no' || $hay === '#') {
            return null;
        }
        if (str_contains($hay, 'code')) {
            return 'subject_code';
        }
        if (str_contains($hay, 'description') || ($hay === 'subject' || str_contains($hay, 'subject name'))) {
            return 'subject_name';
        }
        if (str_contains($hay, 'unit')) {
            return 'units';
        }
        if (str_contains($hay, 'midterm')) {
            return 'midterm_grade';
        }
        if (str_contains($hay, 'final exam') || ($hay === 'final' && ! str_contains($hay, 'grade'))) {
            return 'final_exam_grade';
        }
        if (str_contains($hay, 'final grade') || $hay === 'grade') {
            return 'final_grade';
        }
        if (str_contains($hay, 'remark')) {
            return 'remarks';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>|null
     */
    private function rowFromHeader(string $line, string $header, array $columns, ?string $delimiter): ?array
    {
        if ($delimiter !== null || str_contains($line, "\t")) {
            $parts = $this->split($line, "\t");
            $row = [];
            foreach ($columns as $index => $key) {
                $row[$key] = trim((string) ($parts[$index] ?? ''));
            }
        } else {
            $row = $this->sliceByHeaderPositions($line, $header, $columns);
        }

        $code = (string) ($row['subject_code'] ?? '');
        if ($code === '' || ! preg_match('/[A-Za-z]{2,8}/', $code)) {
            return $this->fallbackRow($line);
        }

        return $row;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, string>
     */
    private function sliceByHeaderPositions(string $line, string $header, array $columns): array
    {
        $headerParts = $this->split($header, null);
        $positions = [];
        $offset = 0;
        foreach ($headerParts as $index => $part) {
            $pos = stripos($header, $part, $offset);
            if ($pos === false) {
                continue;
            }
            if (isset($columns[$index])) {
                $positions[] = ['key' => $columns[$index], 'start' => $pos];
            }
            $offset = $pos + strlen($part);
        }

        $row = [];
        foreach ($positions as $i => $item) {
            $start = $item['start'];
            $end = $positions[$i + 1]['start'] ?? strlen($line);
            $row[$item['key']] = trim(substr($line, $start, max(0, $end - $start)));
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fallbackRow(string $line): ?array
    {
        if (! preg_match('/(?:^|\s)([A-Z]{2,8}\s?\d{1,3}[A-Z]?)(?:\s|$)/i', $line, $codeMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $code = strtoupper(trim($codeMatch[1][0]));
        $after = trim(substr($line, $codeMatch[0][1] + strlen($codeMatch[0][0])));
        $tokens = preg_split('/\s+/', $after) ?: [];
        if ($tokens === []) {
            return null;
        }

        $remarks = null;
        $last = strtoupper((string) end($tokens));
        if (in_array($last, ['PASSED', 'FAILED', 'INC', 'INCOMPLETE', 'DRP', 'W', 'WITHDRAWN', 'DROPPED'], true)) {
            $remarks = array_pop($tokens);
        }

        $grades = [];
        while ($tokens !== []) {
            $candidate = (string) end($tokens);
            $normalized = $this->scale->normalizeGradeToken($candidate);
            if ($normalized !== null && ($this->scale->isNumericGrade($normalized) || $this->scale->isIncomplete($normalized) || $this->scale->isDropped($normalized))) {
                array_unshift($grades, $normalized);
                array_pop($tokens);
                continue;
            }
            break;
        }

        $units = null;
        foreach ($tokens as $index => $token) {
            if (is_numeric($token) && (float) $token >= 0 && (float) $token <= 9 && (float) $token == (int) $token) {
                $units = $token;
                unset($tokens[$index]);
                $tokens = array_values($tokens);
                break;
            }
        }

        $name = trim(implode(' ', $tokens));
        $final = $grades !== [] ? (string) end($grades) : '';
        $midterm = $grades[0] ?? null;
        $finalExam = count($grades) > 2 ? $grades[1] : ($grades[1] ?? null);
        if (count($grades) === 1) {
            $midterm = null;
            $finalExam = null;
        } elseif (count($grades) === 2) {
            $midterm = $grades[0];
            $finalExam = null;
            $final = $grades[1];
        }

        return [
            'subject_code' => $code,
            'subject_name' => $name,
            'units' => $units ?? '',
            'midterm_grade' => $midterm,
            'final_exam_grade' => count($grades) >= 3 ? $grades[1] : $finalExam,
            'final_grade' => $final,
            'remarks' => $remarks,
        ];
    }

    /**
     * @return list<string>
     */
    private function split(string $line, ?string $delimiter): array
    {
        if ($delimiter === "\t" || str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\s{2,}/', $line) ?: []), fn (string $part): bool => $part !== ''));
    }
}
