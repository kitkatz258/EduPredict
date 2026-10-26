<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SafeCsv;
use PHPUnit\Framework\TestCase;

class SafeCsvTest extends TestCase
{
    public function test_formula_like_cells_are_prefixed_and_filenames_are_sanitized(): void
    {
        $this->assertSame("'=1+1", SafeCsv::cell('=1+1'));
        $this->assertSame("'+cmd", SafeCsv::cell('+cmd'));
        $this->assertSame("'-SUM(A1)", SafeCsv::cell('-SUM(A1)'));
        $this->assertSame("'@cmd", SafeCsv::cell('@cmd'));
        $this->assertSame('Ada Student', SafeCsv::cell('Ada Student'));
        $this->assertSame('', SafeCsv::cell(null));
        $this->assertSame(['S-HIGH', "'=CMD"], SafeCsv::row(['S-HIGH', '=CMD']));
        $this->assertSame('edupredict-department-students-20261026.csv', SafeCsv::filename('EduPredict Department Students 20261026'));
        $this->assertSame('edupredict-report.csv', SafeCsv::filename('***'));
        $this->assertSame('report.csv', SafeCsv::filename('report', 'csv.exe'));
    }
}
