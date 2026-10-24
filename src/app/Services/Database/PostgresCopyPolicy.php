<?php

declare(strict_types=1);

namespace App\Services\Database;

final class PostgresCopyPolicy
{
    public function assertSourceDriver(string $driver): void
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new PostgresCopyRejected("Source driver [{$driver}] is not MariaDB or MySQL.");
        }
    }

    public function assertTargetDriver(string $driver): void
    {
        if ($driver !== 'pgsql') {
            throw new PostgresCopyRejected("Target driver [{$driver}] is not PostgreSQL.");
        }
    }

    /**
     * @param  array{driver: string, host: string, port: string, database: string}  $source
     * @param  array{driver: string, host: string, port: string, database: string}  $target
     */
    public function assertDistinctEndpoints(array $source, array $target): void
    {
        $left = $this->endpoint($source);
        $right = $this->endpoint($target);

        if ($left === '' || $right === '') {
            throw new PostgresCopyRejected('Source and target need a host, port, and database name.');
        }

        if ($left === $right) {
            throw new PostgresCopyRejected('Source and target describe the same database endpoint.');
        }
    }

    public function assertDevelopmentTarget(string $database, string $development, string $testing, string $defense): void
    {
        foreach (['development' => $development, 'testing' => $testing, 'defense' => $defense] as $label => $name) {
            if ($name === '') {
                throw new PostgresCopyRejected("The {$label} database name is empty.");
            }
        }

        if (count(array_unique([$development, $testing, $defense])) < 3) {
            throw new PostgresCopyRejected('Development, testing, and defense database names must be different.');
        }

        if ($database !== $development) {
            throw new PostgresCopyRejected("Refusing to write to [{$database}]. Copy and rollback write only to the development database [{$development}].");
        }
    }

    /**
     * @param  array{driver: string, host: string, port: string, database: string}  $endpoint
     */
    private function endpoint(array $endpoint): string
    {
        $driver = strtolower(trim($endpoint['driver']));
        $host = strtolower(trim($endpoint['host']));
        $port = trim($endpoint['port']);
        $database = strtolower(trim($endpoint['database']));

        if ($host === '' || $port === '' || $database === '') {
            return '';
        }

        return $driver.'|'.$host.'|'.$port.'|'.$database;
    }
}
