<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Services\Database\MariaDbToPostgresCopier;
use App\Services\Database\PostgresCopyPolicy;
use App\Services\Database\PostgresCopyRejected;
use App\Services\Database\PostgresTestDatabaseGuard;
use App\Services\Database\PostgresValueNormalizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PostgresPortabilityTest extends TestCase
{
    public function test_copy_plan_lists_every_application_table(): void
    {
        $created = [];
        foreach (glob(dirname(__DIR__, 3).'/database/migrations/*.php') ?: [] as $file) {
            preg_match_all("/Schema::create\\('([a-z0-9_]+)'/", (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $table) {
                $created[] = $table;
            }
        }

        $missing = array_values(array_diff($created, MariaDbToPostgresCopier::TABLES));

        $this->assertNotEmpty($created);
        $this->assertSame([], $missing);
        $this->assertSame(count(MariaDbToPostgresCopier::TABLES), count(array_unique(MariaDbToPostgresCopier::TABLES)));
    }

    public function test_copy_and_rollback_do_not_refresh_the_schema(): void
    {
        $files = [
            dirname(__DIR__, 3).'/app/Services/Database/MariaDbToPostgresCopier.php',
            dirname(__DIR__, 3).'/app/Console/Commands/CopyMariaDbToPostgresCommand.php',
            dirname(__DIR__, 3).'/app/Console/Commands/RollbackPostgresDataCommand.php',
        ];
        $source = implode("\n", array_map(fn (string $file): string => (string) file_get_contents($file), $files));

        $this->assertStringNotContainsString('migrate:fresh', $source);
        $this->assertStringNotContainsString('migrate:refresh', $source);
    }

    public function test_copy_refuses_testing_and_defense_databases(): void
    {
        $policy = new PostgresCopyPolicy;

        $this->expectException(PostgresCopyRejected::class);
        $policy->assertDevelopmentTarget('edupredict_defense', 'edupredict', 'edupredict_testing', 'edupredict_defense');
    }

    public function test_copy_refuses_the_testing_database(): void
    {
        $policy = new PostgresCopyPolicy;

        $this->expectException(PostgresCopyRejected::class);
        $policy->assertDevelopmentTarget('edupredict_testing', 'edupredict', 'edupredict_testing', 'edupredict_defense');
    }

    public function test_copy_refuses_the_same_endpoint(): void
    {
        $policy = new PostgresCopyPolicy;
        $endpoint = [
            'driver' => 'pgsql',
            'host' => 'postgres',
            'port' => '5432',
            'database' => 'edupredict',
        ];

        $this->expectException(PostgresCopyRejected::class);
        $policy->assertDistinctEndpoints($endpoint, $endpoint);
    }

    public function test_mariadb_and_postgres_with_the_same_name_are_different_endpoints(): void
    {
        $policy = new PostgresCopyPolicy;

        $policy->assertDistinctEndpoints(
            ['driver' => 'mysql', 'host' => 'db', 'port' => '3306', 'database' => 'edupredict'],
            ['driver' => 'pgsql', 'host' => 'postgres', 'port' => '5432', 'database' => 'edupredict'],
        );

        $policy->assertDevelopmentTarget('edupredict', 'edupredict', 'edupredict_testing', 'edupredict_defense');
        $this->assertTrue(true);
    }

    public function test_postgres_tests_cannot_use_the_development_database(): void
    {
        $guard = new PostgresTestDatabaseGuard;

        $guard->assertSafe('testing', 'sqlite', 'edupredict', 'edupredict_testing', 'edupredict', 'edupredict_defense');
        $guard->assertSafe('local', 'pgsql', 'edupredict', 'edupredict_testing', 'edupredict', 'edupredict_defense');

        $this->expectException(RuntimeException::class);
        $guard->assertSafe('testing', 'pgsql', 'edupredict', 'edupredict_testing', 'edupredict', 'edupredict_defense');
    }

    public function test_values_are_normalized_for_postgres(): void
    {
        $normalizer = new PostgresValueNormalizer;

        $this->assertFalse($normalizer->normalize(0, 'boolean', false));
        $this->assertTrue($normalizer->normalize('1', 'boolean', false));
        $this->assertFalse($normalizer->normalize(false, 'boolean', false));
        $this->assertNull($normalizer->normalize('0000-00-00', 'date', true));
        $this->assertSame('{"name":"SQL"}', $normalizer->normalize('{"name":"SQL"}', 'json', true));
        $this->assertSame('["SQL"]', $normalizer->normalize(['SQL'], 'json', true));
    }

    public function test_invalid_json_and_required_zero_dates_are_rejected(): void
    {
        $normalizer = new PostgresValueNormalizer;

        try {
            $normalizer->normalize('0000-00-00 00:00:00', 'timestamp without time zone', false);
            $this->fail('A required zero date should be rejected.');
        } catch (PostgresCopyRejected) {
            $this->assertTrue(true);
        }

        $this->expectException(PostgresCopyRejected::class);
        $normalizer->normalize('{', 'json', true);
    }
}
