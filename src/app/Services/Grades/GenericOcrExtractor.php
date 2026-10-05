<?php

namespace App\Services\Grades;

use Symfony\Component\Process\Process;

final class GenericOcrExtractor
{
    public function __construct(private TextTableParser $textParser) {}

    public function extract(string $imagePath): ParsedGradeReport
    {
        $process = new Process(['tesseract', $imagePath, 'stdout', 'tsv']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return new ParsedGradeReport(
                source: 'generic_ocr',
                warnings: ['Generic OCR failed. Enter the rows manually.'],
            );
        }

        $lines = $this->clusterTsv($process->getOutput());
        $parsed = $this->textParser->parse(implode("\n", $lines), 'generic_ocr');
        $parsed->warnings[] = 'Used generic OCR fallback; please review every row.';

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function clusterTsv(string $tsv): array
    {
        $rows = preg_split("/\r\n|\n|\r/", $tsv) ?: [];
        $words = [];
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $parts = explode("\t", $row);
            if (count($parts) < 12) {
                continue;
            }
            $conf = (float) $parts[10];
            $text = trim($parts[11]);
            if ($text === '' || $conf < 20) {
                continue;
            }
            $words[] = [
                'left' => (int) $parts[6],
                'top' => (int) $parts[7],
                'text' => $text,
            ];
        }

        usort($words, fn (array $a, array $b): int => $a['top'] <=> $b['top'] ?: $a['left'] <=> $b['left']);

        $lines = [];
        $current = [];
        $currentTop = null;
        foreach ($words as $word) {
            if ($currentTop !== null && abs($word['top'] - $currentTop) > 12) {
                $lines[] = $this->joinLine($current);
                $current = [];
            }
            if ($current === []) {
                $currentTop = $word['top'];
            }
            $current[] = $word;
        }
        if ($current !== []) {
            $lines[] = $this->joinLine($current);
        }

        return $lines;
    }

    /**
     * @param  list<array{left: int, top: int, text: string}>  $words
     */
    private function joinLine(array $words): string
    {
        usort($words, fn (array $a, array $b): int => $a['left'] <=> $b['left']);

        return implode('  ', array_map(fn (array $word): string => $word['text'], $words));
    }
}
