<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Deidentifier;
use PHPUnit\Framework\TestCase;

class DeidentifierTest extends TestCase
{
    public function test_grade_row_filter_drops_pii_and_keeps_subject_lines(): void
    {
        $text = implode("\n", [
            'Name: Juan Dela Cruz',
            'Student Number: 2024-00001',
            'Email: juan@example.com',
            'Birthdate: 2004-01-15',
            'CCS 106 Applications Development 5 2.25 PASSED',
            'Program: BS Information Systems',
        ]);

        $kept = (new Deidentifier)->gradeRowLines($text);

        $this->assertStringContainsString('CCS 106', $kept);
        $this->assertStringNotContainsString('Juan', $kept);
        $this->assertStringNotContainsString('2024-00001', $kept);
        $this->assertStringNotContainsString('juan@example.com', $kept);
        $this->assertStringNotContainsString('2004-01-15', $kept);
    }

    public function test_scrub_removes_pii_keys(): void
    {
        $clean = (new Deidentifier)->scrub([
            'name' => 'Ana',
            'student_number' => '2024-12345',
            'email' => 'ana@example.com',
            'gwa_band' => '1.75-2.00',
            'failed_subjects' => 1,
        ]);

        $this->assertArrayNotHasKey('name', $clean);
        $this->assertArrayNotHasKey('student_number', $clean);
        $this->assertArrayNotHasKey('email', $clean);
        $this->assertSame('1.75-2.00', $clean['gwa_band']);
    }
}
