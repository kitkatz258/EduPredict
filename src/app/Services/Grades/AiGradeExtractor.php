<?php

namespace App\Services\Grades;

use App\Contracts\AiClientInterface;
use App\Services\Ai\Deidentifier;

final class AiGradeExtractor
{
    public function __construct(
        private AiClientInterface $ai,
        private Deidentifier $deidentifier,
        private GradeRowNormalizer $normalizer,
        private TextTableParser $textParser,
    ) {}

    public function extract(string $text): ?ParsedGradeReport
    {
        if (! config('edupredict.ai.extraction_fallback_enabled', true)) {
            return null;
        }

        $lines = $this->deidentifier->gradeRowLines($text);
        if ($lines === '') {
            return null;
        }

        $prompt = <<<PROMPT
Extract student subject grade rows from the text. Return JSON only:
{"rows":[{"subject_code":"","subject_name":"","units":"","midterm_grade":null,"final_exam_grade":null,"final_grade":"","remarks":""}]}
Ignore faculty names, sections, student names, and student numbers. Use Philippine grade tokens (1.00-3.00, 5.00, INC, DRP).
TEXT:
{$lines}
PROMPT;

        $content = $this->ai->complete($prompt, true);
        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            $decoded = json_decode($this->unwrapJson($content), true);
        }
        if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
            return null;
        }

        $rawRows = [];
        foreach ($decoded['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rawRows[] = $row;
        }

        $codes = array_map(fn (array $row): string => $this->normalizer->normalizeCode((string) ($row['subject_code'] ?? $row['code'] ?? '')), $rawRows);
        $rows = [];
        foreach ($rawRows as $row) {
            $normalized = $this->normalizer->normalize($row, $codes);
            if ($normalized->subjectCode === '') {
                continue;
            }
            $rows[] = $normalized;
        }

        if ($rows === []) {
            return null;
        }

        $meta = $this->textParser->extractMeta($text);

        return new ParsedGradeReport(
            source: 'ai_extracted',
            rows: $rows,
            program: $meta['program'],
            schoolYear: $meta['school_year'],
            semester: $meta['semester'],
            detectedGpa: $meta['gpa'],
            warnings: ['AI unavailable text was replaced with model-extracted rows. Please review every cell.'],
            usedAiFallback: true,
        );
    }

    private function unwrapJson(string $content): string
    {
        if (preg_match('/\{.*\}/s', $content, $match)) {
            return $match[0];
        }

        return $content;
    }
}
