<?php

declare(strict_types=1);

namespace App\Services\Database;

use RuntimeException;

final class PostgresTestDatabaseGuard
{
    public function assertSafe(
        string $environment,
        string $connection,
        mixed $database,
        string $testing,
        string $development,
        string $defense,
    ): void {
        if ($environment !== 'testing' || $connection !== 'pgsql') {
            return;
        }

        if ($testing === '' || $testing === $development || $testing === $defense || $development === $defense) {
            throw new RuntimeException('Development, testing, and defense database names must stay different.');
        }

        if (! is_string($database) || $database !== $testing) {
            $actual = is_string($database) ? $database : get_debug_type($database);

            throw new RuntimeException("Refusing PostgreSQL tests against [{$actual}]. Tests must use [{$testing}].");
        }
    }
}
