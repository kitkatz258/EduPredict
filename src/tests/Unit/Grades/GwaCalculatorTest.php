<?php

namespace Tests\Unit\Grades;

use App\Services\Grades\GradeScale;
use App\Services\Grades\GwaCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GwaCalculatorTest extends TestCase
{
    #[DataProvider('sheets')]
    public function test_reference_sheets_compute_expected_gpa(string $file, float $expected, array $failedCodes, array $notFailedCodes, int $incomplete): void
    {
        $payload = json_decode((string) file_get_contents($file), true);
        $calculator = new GwaCalculator(new GradeScale);
        $result = $calculator->compute($payload['rows']);

        $this->assertSame($expected, $result->roundedGpa);
        $this->assertSame($incomplete, $result->incompleteCount);
        $this->assertSame($incomplete > 0, $result->isProvisional());
        if ($incomplete === 0) {
            $this->assertEqualsWithDelta($payload['portal_gpa'], $result->roundedGpa, 0.01);
        }

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

    public function test_inc_is_excluded_from_numerator_and_denominator(): void
    {
        $result = (new GwaCalculator(new GradeScale))->compute([
            ['subject_code' => 'IS 101', 'units' => 3, 'final_grade' => '1.00', 'remarks' => 'PASSED'],
            ['subject_code' => 'IS 102', 'units' => 5, 'final_grade' => 'INC', 'remarks' => 'INCOMPLETE'],
            ['subject_code' => 'IS 103', 'units' => 3, 'final_grade' => '2.00', 'remarks' => 'PASSED'],
        ]);

        $this->assertSame(1.5, $result->roundedGpa);
        $this->assertSame(6.0, $result->gpaUnits);
        $this->assertSame(9.0, $result->qualityPoints);
        $this->assertSame(0, $result->failedCount);
        $this->assertSame(1, $result->incompleteCount);
        $this->assertTrue($result->isProvisional());
    }

    public function test_a_term_with_only_inc_has_no_gwa(): void
    {
        $result = (new GwaCalculator(new GradeScale))->compute([
            ['subject_code' => 'IS 102', 'units' => 5, 'final_grade' => 'INC', 'remarks' => ''],
        ]);

        $this->assertNull($result->roundedGpa);
        $this->assertSame(0, $result->failedCount);
        $this->assertTrue($result->isProvisional());
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: list<string>, 3: list<string>, 4: int}>
     */
    public static function sheets(): array
    {
        $base = dirname(__DIR__, 2).'/Fixtures/grade-reports/';

        return [
            'sheet A 1.44' => [$base.'sheet-a.expected.json', 1.44, [], [], 0],
            'sheet B INC excluded, provisional 1.93' => [$base.'sheet-b.expected.json', 1.93, ['IS 106'], ['IS 103'], 1],
            'sheet C NSTP excluded' => [$base.'sheet-c.expected.json', 1.31, [], [], 0],
        ];
    }
}
