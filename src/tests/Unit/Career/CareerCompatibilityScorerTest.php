<?php

declare(strict_types=1);

namespace Tests\Unit\Career;

use App\Services\Career\CareerCompatibilityScorer;
use Tests\TestCase;

class CareerCompatibilityScorerTest extends TestCase
{
    public function test_scores_are_deterministic_and_prefer_program_and_skill_overlap(): void
    {
        $scorer = new CareerCompatibilityScorer;
        $args = [
            'BSIS',
            ['SQL', 'Programming'],
            1.50,
            ['programming', 'algorithms', 'sql'],
            ['BSIS', 'BSIT', 'BSCS'],
        ];

        $first = $scorer->score(...$args);
        $second = $scorer->score(...$args);

        $this->assertSame($first, $second);
        $this->assertSame(87, $first['score']);
        $this->assertSame(['programming', 'sql'], $first['matched']);
        $this->assertSame(['algorithms'], $first['missing']);

        $unrelated = $scorer->score('BSIS', ['sql'], 1.50, ['accounting'], ['BSA']);
        $this->assertSame(15, $unrelated['score']);
        $this->assertSame([], $unrelated['matched']);
    }
}
