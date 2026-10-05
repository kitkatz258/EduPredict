<?php

namespace Tests\Unit\Grades;

use App\Services\Grades\GradeRowNormalizer;
use App\Services\Grades\GradeScale;
use App\Services\Grades\GwaCalculator;
use App\Services\Grades\TextTableParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradeReportParserTest extends TestCase
{
    #[DataProvider('pasteSheets')]
    public function test_paste_path_matches_reference_sheet(string $pasteFile, string $expectedFile): void
    {
        $expected = json_decode((string) file_get_contents($expectedFile), true);
        $parsed = $this->parser()->parse(file_get_contents($pasteFile), 'pasted');

        $this->assertSame($expected['school_year'], $parsed->schoolYear);
        $this->assertSame($expected['semester'], $parsed->semester);
        $this->assertCount(count($expected['rows']), $parsed->rows);

        foreach ($expected['rows'] as $index => $row) {
            $actual = $parsed->rows[$index];
            $this->assertSame($row['subject_code'], $actual->subjectCode);
            $this->assertSame($row['subject_name'], $actual->subjectName);
            $this->assertEquals((float) $row['units'], (float) $actual->units);
            $this->assertSame($row['midterm_grade'], $actual->midtermGrade);
            $this->assertSame($row['final_exam_grade'], $actual->finalExamGrade);
            $this->assertSame($row['final_grade'], $actual->finalGrade);
            $this->assertSame($row['remarks'], $actual->remarks);
            $this->assertSame($row['is_failed'], $actual->isFailed);
        }

        $gwa = (new GwaCalculator(new GradeScale))->compute($parsed->rows);
        $this->assertSame($expected['computed_gpa'], $gwa->roundedGpa);
        $this->assertEqualsWithDelta($parsed->detectedGpa, $gwa->roundedGpa, 0.01);
    }

    public function test_layout_variants_still_parse_sheet_a_codes_and_gpa(): void
    {
        $expected = json_decode((string) file_get_contents($this->fixture('sheet-a.expected.json')), true);
        $files = [
            'variant-column-order.txt',
            'variant-no-midterm.txt',
            'variant-space-aligned.txt',
        ];

        foreach ($files as $file) {
            $parsed = $this->parser()->parse((string) file_get_contents($this->fixture($file)), 'pasted');
            $codes = array_map(fn ($row) => $row->subjectCode, $parsed->rows);
            $this->assertSame(array_column($expected['rows'], 'subject_code'), $codes, $file);
            $gwa = (new GwaCalculator(new GradeScale))->compute($parsed->rows);
            $this->assertSame(1.44, $gwa->roundedGpa, $file);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pasteSheets(): array
    {
        $base = dirname(__DIR__, 2).'/Fixtures/grade-reports/';

        return [
            'A' => [$base.'paste-a.txt', $base.'sheet-a.expected.json'],
            'B' => [$base.'paste-b.txt', $base.'sheet-b.expected.json'],
            'C' => [$base.'paste-c.txt', $base.'sheet-c.expected.json'],
        ];
    }

    private function parser(): TextTableParser
    {
        $scale = new GradeScale;
        $normalizer = new GradeRowNormalizer($scale);

        return new TextTableParser($normalizer, $scale);
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2).'/Fixtures/grade-reports/'.$name;
    }
}
