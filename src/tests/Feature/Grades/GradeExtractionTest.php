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

    public function test_real_sample_files_match_reference_sheets(): void
    {
        if (! $this->commandExists('python3') || ! $this->commandExists('tesseract')) {
            $this->markTestSkipped('python3 or tesseract is unavailable.');
        }

        $samples = [
            'A' => $this->firstExistingSample([
                'grade_sample_a.png',
                'Screenshot_2026-10-05_143510.png',
            ]),
            'B' => $this->firstExistingSample([
                'grade_sample_b.png',
                'Screenshot_2026-10-05_184653.png',
            ]),
            'C' => $this->firstExistingSample([
                'grade_sample_c.pdf',
                'Screenshot_2026-10-05_184732.pdf',
            ]),
        ];

        $missing = array_keys(array_filter($samples, fn (?string $path): bool => $path === null));
        if ($missing !== []) {
            $this->markTestSkipped('Sample grade-report files are not in cursor/samples/: '.implode(', ', $missing).'.');
        }

        Http::fake();
        $parser = app(GradeReportParser::class);
        $calculator = app(GwaCalculator::class);

        foreach ($samples as $sheet => $path) {
            $expected = json_decode((string) file_get_contents(base_path('tests/Fixtures/grade-reports/sheet-'.strtolower($sheet).'.expected.json')), true);
            $parsed = $parser->parseFile($path, '', basename($path));

            $this->assertSame('grid_ocr', $parsed->source, $sheet);
            $this->assertFalse($parsed->usedAiFallback, $sheet);
            $this->assertSame($expected['school_year'], $parsed->schoolYear, $sheet);
            $this->assertSame($expected['semester'], $parsed->semester, $sheet);
            $this->assertNotNull($parsed->program, $sheet);
            $this->assertStringContainsStringIgnoringCase('information systems', (string) $parsed->program, $sheet);
            $this->assertCount(count($expected['rows']), $parsed->rows, $sheet);

            foreach ($expected['rows'] as $index => $row) {
                $actual = $parsed->rows[$index];
                $this->assertSame($row['subject_code'], $actual->subjectCode, "{$sheet} row {$index}");
                $this->assertSame(strtoupper($row['subject_name']), strtoupper($actual->subjectName), "{$sheet} row {$index}");
                $this->assertEquals((float) $row['units'], (float) $actual->units, "{$sheet} row {$index}");
                $this->assertSame($row['midterm_grade'], $actual->midtermGrade, "{$sheet} row {$index}");
                $this->assertSame($row['final_exam_grade'], $actual->finalExamGrade, "{$sheet} row {$index}");
                $this->assertSame($row['final_grade'], $actual->finalGrade, "{$sheet} row {$index}");
                $this->assertSame($row['remarks'], $actual->remarks, "{$sheet} row {$index}");
                $this->assertSame($row['is_failed'], $actual->isFailed, "{$sheet} row {$index}");
                $this->assertFalse($actual->needsReview, "{$sheet} row {$index}");
            }

            $gwa = $calculator->compute($parsed->rows);
            $this->assertSame($expected['computed_gpa'], $gwa->roundedGpa, $sheet);
            $this->assertEqualsWithDelta($expected['portal_gpa'], $parsed->detectedGpa, 0.01, $sheet);
        }

        Http::assertNothingSent();
    }

    /**
     * @param  list<string>  $filenames
     */
    private function firstExistingSample(array $filenames): ?string
    {
        foreach ($filenames as $filename) {
            $path = base_path('cursor/samples/'.$filename);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function commandExists(string $command): bool
    {
        $which = @shell_exec('command -v '.escapeshellarg($command));

        return is_string($which) && trim($which) !== '';
    }
}
