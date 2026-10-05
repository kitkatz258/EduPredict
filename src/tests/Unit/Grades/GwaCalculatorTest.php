<?php

namespace Tests\Unit\Grades;

use App\Services\Grades\GradeScale;
use App\Services\Grades\GwaCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GwaCalculatorTest extends TestCase
{
    #[DataProvider('sheets')]
    public function test_reference_sheets_compute_expected_gpa(string $file, float $expected, array $failedCodes, array $notFailedCodes): void
    {
        $payload = json_decode((string) file_get_contents($file), true);
        $calculator = new GwaCalculator(new GradeScale);
        $result = $calculator->compute($payload['rows']);

        $this->assertSame($expected, $result->roundedGpa);
        $this->assertEqualsWithDelta($payload['portal_gpa'], $result->roundedGpa, 0.01);

        foreach ($payload['rows'] as $row) {
            if (in_array($row['subject_code'], $failedCodes, true)) {
                $this->assertTrue($row['is_failed'], $row['subject_code'].' should be failed');
            }
            if (in_array($row['subject_code'], $notFailedCodes, true)) {
                $this->assertFalse($row['is_failed'], $row['subject_code'].' should not be failed');
            }
        }

        $this->assertSame(count($failedCodes), $result->failedCount);
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: list<string>, 3: list<string>}>
     */
    public static function sheets(): array
    {
        $base = dirname(__DIR__, 2).'/Fixtures/grade-reports/';

        return [
            'sheet A 1.44' => [$base.'sheet-a.expected.json', 1.44, [], []],
            'sheet B INC and fail' => [$base.'sheet-b.expected.json', 2.33, ['IS 106'], ['IS 103']],
            'sheet C NSTP excluded' => [$base.'sheet-c.expected.json', 1.31, [], []],
        ];
    }
}
