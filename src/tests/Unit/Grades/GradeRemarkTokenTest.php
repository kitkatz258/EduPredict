<?php

namespace Tests\Unit\Grades;

use App\Services\Grades\GradeRowNormalizer;
use App\Services\Grades\GradeScale;
use App\Services\Grades\GwaCalculator;
use Tests\TestCase;

class GradeRemarkTokenTest extends TestCase
{
    public function test_dropped_inc_and_failing_grades_set_remarks_without_changing_gwa_rules(): void
    {
        $normalizer = new GradeRowNormalizer(new GradeScale);
        $dropped = $normalizer->normalize([
            'subject_code' => 'CCS 101',
            'subject_name' => 'Information Management',
            'units' => '3',
            'final_grade' => '  Dropped ',
            'remarks' => '',
        ]);
        $incomplete = $normalizer->normalize([
            'subject_code' => 'CCS 102',
            'subject_name' => 'Systems Analysis',
            'units' => '3',
            'final_grade' => 'inc',
            'remarks' => '',
        ]);
        $failed = $normalizer->normalize([
            'subject_code' => 'CCS 103',
            'subject_name' => 'Networking',
            'units' => '3',
            'final_grade' => ' 5.00 ',
            'remarks' => '',
        ]);

        $this->assertSame('DRP', $dropped->finalGrade);
        $this->assertSame('DROPPED', $dropped->remarks);
        $this->assertFalse($dropped->isFailed);
        $this->assertSame('INC', $incomplete->finalGrade);
        $this->assertSame('INCOMPLETE', $incomplete->remarks);
        $this->assertTrue($incomplete->isIncomplete);
        $this->assertSame('5.00', $failed->finalGrade);
        $this->assertSame('FAILED', $failed->remarks);
        $this->assertTrue($failed->isFailed);

        $gwa = (new GwaCalculator(new GradeScale))->compute([
            ['subject_code' => 'CCS 101', 'units' => 3, 'final_grade' => 'DRP', 'remarks' => 'DROPPED'],
            ['subject_code' => 'CCS 102', 'units' => 3, 'final_grade' => 'INC', 'remarks' => 'INCOMPLETE'],
            ['subject_code' => 'CCS 104', 'units' => 3, 'final_grade' => '1.00', 'remarks' => 'PASSED'],
            ['subject_code' => 'CCS 103', 'units' => 3, 'final_grade' => '5.00', 'remarks' => 'FAILED'],
        ]);

        $this->assertSame(3.0, $gwa->roundedGpa);
        $this->assertSame(1, $gwa->failedCount);
        $this->assertSame(1, $gwa->incompleteCount);
    }

    public function test_a_justified_note_is_kept_and_a_conflicting_status_word_follows_the_grade(): void
    {
        $normalizer = new GradeRowNormalizer(new GradeScale);
        $noted = $normalizer->normalize([
            'subject_code' => 'CCS 201',
            'subject_name' => 'Database',
            'units' => '3',
            'final_grade' => '5.00',
            'remarks' => 'Registrar confirmed the failing mark',
        ]);
        $conflict = $normalizer->normalize([
            'subject_code' => 'CCS 202',
            'subject_name' => 'Programming',
            'units' => '3',
            'final_grade' => '1.25',
            'remarks' => 'Dropped',
        ]);

        $this->assertSame('Registrar confirmed the failing mark', $noted->remarks);
        $this->assertTrue($noted->needsReview);
        $this->assertTrue($noted->isFailed);
        $this->assertSame('PASSED', $conflict->remarks);
        $this->assertTrue($conflict->needsReview);
        $this->assertFalse($conflict->isFailed);
    }
}
