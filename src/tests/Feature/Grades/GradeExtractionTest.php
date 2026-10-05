<?php

namespace Tests\Feature\Grades;

use App\Services\Grades\GradeReportParser;
use App\Services\Grades\GwaCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GradeExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_ai_key_does_not_call_http_and_paste_still_works(): void
    {
        config(['edupredict.ai.api_key' => '']);
        Http::fake();

        $parser = app(GradeReportParser::class);
        $parsed = $parser->parseText((string) file_get_contents(base_path('tests/Fixtures/grade-reports/paste-c.txt')));

        Http::assertNothingSent();
        $this->assertSame('pasted', $parsed->source);
        $this->assertFalse($parsed->usedAiFallback);
        $this->assertCount(8, $parsed->rows);
        $this->assertSame('NSTP 122', $parsed->rows[5]->subjectCode);
        $this->assertFalse($parsed->rows[5]->isFailed);

        $gwa = app(GwaCalculator::class)->compute($parsed->rows);
        $this->assertSame(1.31, $gwa->roundedGpa);
    }

    public function test_ocr_samples_are_skipped_when_missing(): void
    {
        $samples = [
            base_path('cursor/samples/Screenshot_2026-10-05_143510.png'),
            base_path('cursor/samples/Screenshot_2026-10-05_184653.png'),
            base_path('cursor/samples/Screenshot_2026-10-05_184732.pdf'),
        ];

        $missing = array_filter($samples, fn (string $path): bool => ! is_file($path));
        if ($missing !== []) {
            $this->markTestSkipped('Sample grade-report images are not in cursor/samples/.');
        }

        if (! $this->commandExists('python3') || ! $this->commandExists('tesseract')) {
            $this->markTestSkipped('python3 or tesseract is unavailable.');
        }

        $parser = app(GradeReportParser::class);
        $parsed = $parser->parseFile($samples[2], 'application/pdf', 'sheet-c.pdf');
        $this->assertNotEmpty($parsed->rows);
        $this->assertSame(1.31, app(GwaCalculator::class)->compute($parsed->rows)->roundedGpa);
    }

    private function commandExists(string $command): bool
    {
        $which = @shell_exec('command -v '.escapeshellarg($command));

        return is_string($which) && trim($which) !== '';
    }
}
