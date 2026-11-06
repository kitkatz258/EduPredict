<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use RuntimeException;

/**
 * The populated-database questionnaire update stopped before writing.
 */
final class QuestionnaireReleaseBlocked extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(implode("\n", $reasons));
    }
}
