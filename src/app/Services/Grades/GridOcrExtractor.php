<?php

namespace App\Services\Grades;

use Symfony\Component\Process\Process;

final class GridOcrExtractor
{
    /**
     * @return array{meta: array<string, mixed>, rows: list<array<string, mixed>>}|array{error: string}
     */
    public function extract(string $imagePath): array
    {
        $script = base_path('tools/grade_table_ocr.py');
        $process = new Process(['python3', $script, $imagePath]);
        $process->setTimeout(90);
        $process->run();

        $decoded = json_decode($process->getOutput(), true);
        if (! is_array($decoded)) {
            return ['error' => 'Grid OCR returned invalid JSON.'];
        }
        if (! $process->isSuccessful() || isset($decoded['error'])) {
            return ['error' => (string) ($decoded['error'] ?? 'Grid OCR failed.')];
        }

        return $decoded;
    }
}
