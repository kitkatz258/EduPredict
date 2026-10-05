<?php

namespace App\Services\Grades;

final class GradeReportParser
{
    public function __construct(
        private TextTableParser $textParser,
        private PdfTextExtractor $pdf,
        private GridOcrExtractor $gridOcr,
        private GenericOcrExtractor $genericOcr,
        private AiGradeExtractor $ai,
        private GradeRowNormalizer $normalizer,
    ) {}

    public function parseText(string $text, string $source = 'pasted'): ParsedGradeReport
    {
        return $this->textParser->parse($text, $source);
    }

    public function parseFile(string $path, string $mime = '', string $originalName = ''): ParsedGradeReport
    {
        $mime = strtolower($mime);
        $name = strtolower($originalName);
        $isPdf = str_contains($mime, 'pdf') || str_ends_with($name, '.pdf') || str_ends_with(strtolower($path), '.pdf');
        $isImage = str_starts_with($mime, 'image/') || preg_match('/\.(png|jpe?g|webp|gif)$/i', $name.$path);

        if ($isPdf) {
            return $this->parsePdf($path);
        }
        if ($isImage) {
            return $this->parseImage($path);
        }

        $text = @file_get_contents($path) ?: '';

        return $this->maybeAi($this->parseText($text, 'pasted'), $text);
    }

    private function parsePdf(string $path): ParsedGradeReport
    {
        $text = $this->pdf->extract($path);
        $fromText = $text !== '' ? $this->parseText($text, 'pdf_text') : new ParsedGradeReport(source: 'pdf_text', warnings: ['No text layer in this PDF.']);

        if ($fromText->rows !== [] && ! $fromText->isLowConfidence()) {
            return $fromText;
        }

        $directory = storage_path('app/private/grade-ocr/'.uniqid('pdf_', true));
        try {
            $images = $this->pdf->extractPageImages($path, $directory);
            $best = $fromText;
            foreach ($images as $image) {
                $parsed = $this->parseImage($image);
                if (count($parsed->rows) > count($best->rows)) {
                    $best = $parsed;
                }
            }

            return $this->maybeAi($best, $text !== '' ? $text : $this->rowsAsText($best));
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    private function parseImage(string $path): ParsedGradeReport
    {
        $grid = $this->gridOcr->extract($path);
        if (! isset($grid['error']) && isset($grid['rows']) && is_array($grid['rows'])) {
            $parsed = $this->fromOcrPayload($grid, 'grid_ocr');
            if (! $parsed->isLowConfidence()) {
                return $parsed;
            }

            $generic = $this->genericOcr->extract($path);
            $best = count($generic->rows) > count($parsed->rows) ? $generic : $parsed;

            return $this->maybeAi($best, $this->rowsAsText($best));
        }

        $generic = $this->genericOcr->extract($path);

        return $this->maybeAi($generic, $this->rowsAsText($generic));
    }

    /**
     * @param  array{meta?: array<string, mixed>, rows: list<array<string, mixed>>}  $payload
     */
    private function fromOcrPayload(array $payload, string $source): ParsedGradeReport
    {
        $rawRows = [];
        foreach ($payload['rows'] as $row) {
            $rawRows[] = [
                'subject_code' => $row['code'] ?? $row['subject_code'] ?? '',
                'subject_name' => $row['description'] ?? $row['subject_name'] ?? '',
                'units' => $row['units'] ?? '',
                'midterm_grade' => $row['midterm'] ?? $row['midterm_grade'] ?? null,
                'final_exam_grade' => $row['final'] ?? $row['final_exam_grade'] ?? null,
                'final_grade' => $row['final_grade'] ?? '',
                'remarks' => $row['remarks'] ?? '',
            ];
        }

        $codes = array_map(fn (array $row): string => $this->normalizer->normalizeCode((string) $row['subject_code']), $rawRows);
        $rows = array_map(fn (array $row): ParsedGradeRow => $this->normalizer->normalize($row, $codes), $rawRows);
        $meta = $payload['meta'] ?? [];

        return new ParsedGradeReport(
            source: $source,
            rows: $rows,
            program: isset($meta['program']) ? (string) $meta['program'] : null,
            schoolYear: isset($meta['school_year']) ? (string) $meta['school_year'] : null,
            semester: isset($meta['semester']) ? (string) $meta['semester'] : null,
            detectedGpa: isset($meta['gpa_shown']) ? (float) $meta['gpa_shown'] : null,
            warnings: [],
        );
    }

    private function maybeAi(ParsedGradeReport $parsed, string $text): ParsedGradeReport
    {
        if (! $parsed->isLowConfidence()) {
            return $parsed;
        }

        $ai = $this->ai->extract($text);
        if ($ai !== null && count($ai->rows) >= count($parsed->rows)) {
            if ($parsed->rows !== []) {
                $ai->warnings[] = 'AI fallback used because parsing confidence was low.';
            }

            return $ai;
        }

        $parsed->warnings[] = 'AI unavailable, using standard text. Review or enter rows manually.';

        return $parsed;
    }

    private function rowsAsText(ParsedGradeReport $parsed): string
    {
        $lines = [];
        foreach ($parsed->rows as $row) {
            $lines[] = implode("\t", [
                $row->subjectCode,
                $row->subjectName,
                $row->units,
                $row->midtermGrade ?? '',
                $row->finalExamGrade ?? '',
                $row->finalGrade,
                $row->remarks,
            ]);
        }

        return implode("\n", $lines);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $files = glob($directory.DIRECTORY_SEPARATOR.'*') ?: [];
        foreach ($files as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : @unlink($file);
        }
        @rmdir($directory);
    }
}
