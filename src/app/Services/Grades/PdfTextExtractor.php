<?php

namespace App\Services\Grades;

use Symfony\Component\Process\Process;

final class PdfTextExtractor
{
    public function extract(string $path): string
    {
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    /**
     * @return list<string>
     */
    public function extractPageImages(string $path, string $directory): array
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $prefix = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'page';
        $images = new Process(['pdfimages', '-png', $path, $prefix]);
        $images->setTimeout(30);
        $images->run();

        $files = $this->pngFiles($directory);
        if ($files !== []) {
            return $files;
        }

        $ppm = new Process(['pdftoppm', '-r', '200', '-png', $path, $prefix]);
        $ppm->setTimeout(45);
        $ppm->run();

        return $this->pngFiles($directory);
    }

    /**
     * @return list<string>
     */
    private function pngFiles(string $directory): array
    {
        $files = glob($directory.DIRECTORY_SEPARATOR.'*.png') ?: [];
        sort($files);

        return array_values($files);
    }
}
