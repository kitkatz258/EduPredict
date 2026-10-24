<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use RuntimeException;

/**
 * A research-inference failure. It is not a student prediction and has no heuristic fallback.
 */
final class ResearchInferenceException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 0,
    ) {
        parent::__construct($message);
    }
}
