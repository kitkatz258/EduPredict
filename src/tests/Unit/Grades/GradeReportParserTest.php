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
        $this->assertEqualsWithDelta($expected['portal_gpa'], $parsed->detectedGpa, 0.01);
        if (! $gwa->isProvisional()) {
            $this->assertEqualsWithDelta($parsed->detectedGpa, $gwa->roundedGpa, 0.01);
        }
    }

    public function test_portal_paste_with_faculty_and_section_columns_keeps_names_out(): void
    {
        $paste = implode("\n", [
            'School Year and Semester: 2024-2025 | Second',
            "#\tSubject Code\tDescription\tUnits\tMidterm\tFinal\tFinal Grade\tRemarks\tFaculty\tSection",
            "1\tCCS 106\tApplications Development and Emerging Technologies\t5\t2.50\t2.25\t2.25\tPASSED\tDELA CRUZ, JUAN M.\tBSIS 3A",
            "2\tGEE 002\tLiving in the IT Era\t3\t1.50\t1.00\t1.25\tPASSED\tSANTOS, MARIA\tBSIS 3A",
        ]);

        $parsed = $this->parser()->parse($paste, 'pasted');

        $this->assertCount(2, $parsed->rows);
        $this->assertSame('Applications Development and Emerging Technologies', $parsed->rows[0]->subjectName);
        $this->assertSame('PASSED', $parsed->rows[0]->remarks);
        $this->assertSame('Living in the IT Era', $parsed->rows[1]->subjectName);
        $this->assertStringNotContainsString('DELA CRUZ', json_encode(array_map(fn ($row) => $row->toArray(), $parsed->rows)));
        $this->assertStringNotContainsString('SANTOS', json_encode(array_map(fn ($row) => $row->toArray(), $parsed->rows)));
    }

    public function test_headerless_rows_drop_trailing_instructor_and_section(): void
    {
        $paste = implode("\n", [
            'CCS 106 Applications Development 5 2.50 2.25 2.25 PASSED DELA CRUZ, JUAN M. BSIS 3A',
            'IS 103 Database System Enterprise 5 1.75 INC INC INCOMPLETE Prof. Ana Reyes BSIS 3A',
        ]);

        $parsed = $this->parser()->parse($paste, 'pasted');

        $this->assertCount(2, $parsed->rows);
        $this->assertSame('Applications Development', $parsed->rows[0]->subjectName);
        $this->assertSame('2.25', $parsed->rows[0]->finalGrade);
        $this->assertSame('PASSED', $parsed->rows[0]->remarks);
        $this->assertSame('Database System Enterprise', $parsed->rows[1]->subjectName);
        $this->assertSame('INC', $parsed->rows[1]->finalGrade);
        $this->assertTrue($parsed->rows[1]->isIncomplete);
        $this->assertFalse($parsed->rows[1]->isFailed);
    }

    public function test_ucc_portal_row_order_drops_faculty_and_section(): void
    {
        // Portal order: No, Code, Description, Faculty, Units, Section, Midterm, Final, Final Grade, Remarks.
        $paste = implode("\n", [
            '1 CCS 118 MULTIMEDIA SYSTEMS REYES, ANA B 3 BSIS 3-A-SOUTH 1.00 1.75 1.50 PASSED',
            '2 GEE 003 GENDER AND SOCIETY STA. MARIA, ANA G 3 BSIS 3-A-SOUTH 1.50 1.00 1.25 PASSED',
            '3 IS 103 DATABASE SYSTEM ENTERPRISE TBA 5 BSIS 3-A-SOUTH 1.75 INC INC INCOMPLETE',
            '4 IS 106 IS MAJOR ELECTIVE 1 DELA CRUZ, JUAN II R. 3 BSIS 3-A-SOUTH 2.00 5.00 5.00 FAILED',
        ]);

        $parsed = $this->parser()->parse($paste, 'pasted');

        $this->assertSame(
            ['MULTIMEDIA SYSTEMS', 'GENDER AND SOCIETY', 'DATABASE SYSTEM ENTERPRISE', 'IS MAJOR ELECTIVE 1'],
            array_map(fn ($row) => $row->subjectName, $parsed->rows),
        );
        $this->assertSame(['3', '3', '5', '3'], array_map(fn ($row) => $row->units, $parsed->rows));
        $this->assertSame(['1.50', '1.25', 'INC', '5.00'], array_map(fn ($row) => $row->finalGrade, $parsed->rows));
        $this->assertTrue($parsed->rows[3]->isFailed);
        $this->assertTrue($parsed->rows[2]->isIncomplete);
        $this->assertFalse($parsed->rows[0]->needsReview);
        $this->assertContains('Instructor and section columns were left out of 4 rows. Check that each subject name is complete.', $parsed->warnings);
    }

    public function test_space_aligned_faculty_column_does_not_leak_into_remarks(): void
    {
        $paste = implode("\n", [
            'Subject Code     Description             Units  Final Grade  Remarks   Faculty',
            'CCS 110          Computer Graphics 1     3      1.50         PASSED    REYES, ANA',
        ]);

        $parsed = $this->parser()->parse($paste, 'pasted');

        $this->assertSame('Computer Graphics 1', $parsed->rows[0]->subjectName);
        $this->assertSame('PASSED', $parsed->rows[0]->remarks);
    }

    #[DataProvider('descriptions')]
    public function test_instructor_names_are_stripped_from_descriptions(string $raw, string $expected): void
    {
        $normalizer = new GradeRowNormalizer(new GradeScale);

        $this->assertSame($expected, $normalizer->stripInstructorName($raw));

        $row = $normalizer->normalize(['subject_code' => 'CCS 106', 'subject_name' => $raw, 'units' => '3', 'final_grade' => '1.50']);
        $this->assertSame($raw !== $expected, in_array('An instructor name was removed from the description. Check the subject name.', $row->warnings, true));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function descriptions(): array
    {
        return [
            'honorific' => ['Web Development 2 Prof. Maria Santos', 'Web Development 2'],
            'dash honorific' => ['Computer Graphics 1 - Dr. Jose Rizal', 'Computer Graphics 1'],
            'surname first' => ['Ethics DELA CRUZ, Juan M.', 'Ethics'],
            'plain title' => ['Living in the IT Era', 'Living in the IT Era'],
            'title with comma' => ['Science, Technology and Society', 'Science, Technology and Society'],
            'acronym title' => ['IS Innovations & New Technologies', 'IS Innovations & New Technologies'],
            'all caps title' => ['PURPOSIVE COMMUNICATION, ORAL', 'PURPOSIVE COMMUNICATION, ORAL'],
            'merged portal cell' => ['GENDER AND SOCIETY STA. MARIA, ANA G BSIS 3-A-SOUTH', 'GENDER AND SOCIETY'],
            'section-like title' => ['CALCULUS 1-A', 'CALCULUS 1-A'],
        ];
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
