<?php

declare(strict_types=1);

namespace App\Services\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Copies rows from MariaDB into an empty PostgreSQL development database.
 * The source session is read-only. This class does not migrate, refresh, or
 * truncate the source, and it will not write to the testing or defense database.
 */
final class MariaDbToPostgresCopier
{
    /**
     * Parents are listed before children. `migrations` is intentionally absent.
     *
     * @var list<string>
     */
    public const TABLES = [
        'colleges',
        'departments',
        'programs',
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'institution_students',
        'students',
        'consents',
        'audit_logs',
        'account_deletion_requests',
        'socioeconomic_profiles',
        'skills_experiences',
        'student_skills',
        'student_certifications',
        'student_work_experiences',
        'grade_reports',
        'subject_grades',
        'questionnaire_items',
        'questionnaire_responses',
        'questionnaire_answers',
        'predictions',
        'psoc_occupations',
        'career_matches',
        'interventions',
        'recommended_actions',
        'notifications',
    ];

    private const IGNORED_TABLES = ['migrations'];

    public function __construct(
        private readonly PostgresCopyPolicy $policy,
        private readonly PostgresValueNormalizer $normalizer,
    ) {}

    /**
     * @return list<array{table: string, source: int, target: int, keys_match: bool}>
     */
    public function copy(string $sourceName = 'mysql', string $targetName = 'pgsql'): array
    {
        [$source, $target] = $this->connections($sourceName, $targetName, true);
        $this->lockSourceReadOnly($source);
        $this->assertSchema($source, $target);

        if ($this->populatedTables($target) !== []) {
            throw new PostgresCopyRejected('The PostgreSQL development database already has rows. Refusing to overwrite it. Roll the copy back or use a new empty database. A populated schema is left in place.');
        }

        $foreignKeys = $this->foreignKeys($target);

        $target->transaction(function () use ($source, $target, $foreignKeys): void {
            foreach (self::TABLES as $table) {
                $this->copyTable($source, $target, $table, $foreignKeys);
            }
            $this->syncSequences($target);
        });

        return $this->verify($source, $target);
    }

    /**
     * @return list<array{table: string, source: int, target: int}>
     */
    public function dryRun(string $sourceName = 'mysql', string $targetName = 'pgsql'): array
    {
        [$source, $target] = $this->connections($sourceName, $targetName, true);
        $this->lockSourceReadOnly($source);
        $this->assertSchema($source, $target);

        $rows = [];
        foreach (self::TABLES as $table) {
            $rows[] = [
                'table' => $table,
                'source' => (int) $source->table($table)->count(),
                'target' => (int) $target->table($table)->count(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{table: string, source: int, target: int, keys_match: bool}>
     */
    public function verifyOnly(string $sourceName = 'mysql', string $targetName = 'pgsql'): array
    {
        [$source, $target] = $this->connections($sourceName, $targetName, false);
        $this->lockSourceReadOnly($source);
        $this->assertSchema($source, $target);

        return $this->verify($source, $target);
    }

    public function clearDevelopmentDatabase(string $targetName, string $expectedDatabase): void
    {
        $target = DB::connection($targetName);
        $this->policy->assertTargetDriver($target->getDriverName());
        $this->policy->assertDevelopmentTarget(
            (string) $target->getDatabaseName(),
            (string) config('edupredict.postgres.development_database'),
            (string) config('edupredict.postgres.testing_database'),
            (string) config('edupredict.postgres.defense_database'),
        );

        if ($target->getDatabaseName() !== $expectedDatabase) {
            throw new PostgresCopyRejected("Refusing to clear [{$target->getDatabaseName()}]. The confirmation name does not match.");
        }

        $this->assertSchema($target, $target);
        $quoted = array_map(
            fn (string $table): string => '"'.$this->identifier($table).'"',
            array_reverse(self::TABLES),
        );
        $target->statement('TRUNCATE TABLE '.implode(', ', $quoted).' RESTART IDENTITY CASCADE');
    }

    /**
     * @return array{0: Connection, 1: Connection}
     */
    private function connections(string $sourceName, string $targetName, bool $requireDevelopmentTarget): array
    {
        $source = DB::connection($sourceName);
        $target = DB::connection($targetName);
        $this->policy->assertSourceDriver($source->getDriverName());
        $this->policy->assertTargetDriver($target->getDriverName());
        $this->policy->assertDistinctEndpoints(
            $this->endpoint($source),
            $this->endpoint($target),
        );

        if ($requireDevelopmentTarget) {
            $this->policy->assertDevelopmentTarget(
                (string) $target->getDatabaseName(),
                (string) config('edupredict.postgres.development_database'),
                (string) config('edupredict.postgres.testing_database'),
                (string) config('edupredict.postgres.defense_database'),
            );
        }

        return [$source, $target];
    }

    /**
     * @return array{driver: string, host: string, port: string, database: string}
     */
    private function endpoint(Connection $connection): array
    {
        return [
            'driver' => $connection->getDriverName(),
            'host' => (string) $connection->getConfig('host'),
            'port' => (string) $connection->getConfig('port'),
            'database' => (string) $connection->getDatabaseName(),
        ];
    }

    private function lockSourceReadOnly(Connection $source): void
    {
        $source->statement('SET SESSION TRANSACTION READ ONLY');
    }

    private function assertSchema(Connection $left, Connection $right): void
    {
        foreach ([$left, $right] as $connection) {
            $present = $this->tableNames($connection);
            $missing = array_values(array_diff(self::TABLES, $present));
            $unknown = array_values(array_diff($present, self::TABLES, self::IGNORED_TABLES));

            if ($missing !== []) {
                throw new PostgresCopyRejected($connection->getDriverName().' is missing tables: '.implode(', ', $missing).'. Migrate that empty database first. Do not rebuild a populated database.');
            }

            if ($unknown !== []) {
                throw new PostgresCopyRejected($connection->getDriverName().' has tables that the copy plan does not list: '.implode(', ', $unknown).'.');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function populatedTables(Connection $target): array
    {
        $populated = [];
        foreach (self::TABLES as $table) {
            if ((int) $target->table($table)->count() > 0) {
                $populated[] = $table;
            }
        }

        return $populated;
    }

    /**
     * @param  list<array{table: string, column: string, foreign_table: string, foreign_column: string}>  $foreignKeys
     */
    private function copyTable(Connection $source, Connection $target, string $table, array $foreignKeys): void
    {
        $sourceColumns = $this->columns($source, $table);
        $targetColumns = $this->columns($target, $table);
        $sourceNames = array_column($sourceColumns, 'name');
        $targetNames = array_column($targetColumns, 'name');
        $sortedSource = $sourceNames;
        $sortedTarget = $targetNames;
        sort($sortedSource);
        sort($sortedTarget);

        if ($sortedSource !== $sortedTarget) {
            throw new PostgresCopyRejected("Column mismatch on [{$table}]. Migrate both databases to the same revision before copying.");
        }

        $keys = $this->primaryKey($source, $table);
        $selfColumns = [];
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['table'] === $table && $foreignKey['foreign_table'] === $table) {
                $selfColumns[] = $foreignKey['column'];
            }
        }

        $pending = [];
        $batch = [];
        $query = $source->table($table);
        foreach ($keys as $key) {
            $query->orderBy($key);
        }

        $flush = function () use (&$batch, $target, $table): void {
            if ($batch === []) {
                return;
            }
            $target->table($table)->insert($batch);
            $batch = [];
        };

        foreach ($query->cursor() as $record) {
            $row = array_change_key_case((array) $record, CASE_LOWER);
            $deferred = [];
            foreach ($selfColumns as $column) {
                if ($row[$column] !== null) {
                    $deferred[$column] = $row[$column];
                    $row[$column] = null;
                }
            }

            $insert = [];
            foreach ($targetColumns as $column) {
                $insert[$column['name']] = $this->normalizer->normalize(
                    $row[$column['name']] ?? null,
                    $column['data_type'],
                    $column['nullable'],
                );
            }
            $batch[] = $insert;

            if ($deferred !== []) {
                $identity = [];
                foreach ($keys as $key) {
                    $identity[$key] = $row[$key];
                }
                $pending[] = ['keys' => $identity, 'values' => $deferred];
            }

            if (count($batch) >= 100) {
                $flush();
            }
        }
        $flush();

        foreach ($pending as $update) {
            $values = [];
            foreach ($targetColumns as $column) {
                if (! array_key_exists($column['name'], $update['values'])) {
                    continue;
                }
                $values[$column['name']] = $this->normalizer->normalize(
                    $update['values'][$column['name']],
                    $column['data_type'],
                    $column['nullable'],
                );
            }
            $statement = $target->table($table);
            foreach ($update['keys'] as $key => $value) {
                $statement->where($key, $value);
            }
            $statement->update($values);
        }
    }

    /**
     * @return list<array{table: string, source: int, target: int, keys_match: bool}>
     */
    private function verify(Connection $source, Connection $target): array
    {
        $report = [];
        $mismatches = [];

        foreach (self::TABLES as $table) {
            $keys = $this->primaryKey($target, $table);
            $sourceProfile = $this->profile($source, $table, $keys);
            $targetProfile = $this->profile($target, $table, $keys);
            $match = $sourceProfile['count'] === $targetProfile['count']
                && $sourceProfile['key_hash'] === $targetProfile['key_hash'];
            $report[] = [
                'table' => $table,
                'source' => $sourceProfile['count'],
                'target' => $targetProfile['count'],
                'keys_match' => $match,
            ];
            if (! $match) {
                $mismatches[] = $table;
            }
        }

        $orphans = $this->orphanCounts($target);
        $sequences = $this->sequencesBehind($target);

        if ($mismatches !== [] || $orphans !== [] || $sequences !== []) {
            $parts = [];
            if ($mismatches !== []) {
                $parts[] = 'key mismatch in '.implode(', ', $mismatches);
            }
            if ($orphans !== []) {
                $parts[] = implode('; ', $orphans);
            }
            if ($sequences !== []) {
                $parts[] = implode('; ', $sequences);
            }

            throw new PostgresCopyRejected('PostgreSQL verification failed: '.implode('. ', $parts).'.');
        }

        return $report;
    }

    public function syncSequences(Connection $target): void
    {
        foreach (self::TABLES as $table) {
            foreach ($this->serialColumns($target, $table) as $column) {
                $sequence = $target->selectOne(
                    'select pg_get_serial_sequence(?, ?) as sequence',
                    ['public.'.$table, $column],
                );
                $name = $sequence->sequence ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }
                [$schema, $sequenceName] = $this->sequenceName($name);
                $max = $target->table($table)->max($column);
                if ($max === null) {
                    $target->statement("select setval('{$schema}.{$sequenceName}'::regclass, 1, false)");
                } else {
                    if (! preg_match('/^[0-9]+$/', (string) $max)) {
                        throw new PostgresCopyRejected("Unexpected maximum for {$table}.{$column}.");
                    }
                    $target->statement("select setval('{$schema}.{$sequenceName}'::regclass, {$max}, true)");
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function sequencesBehind(Connection $target): array
    {
        $problems = [];
        foreach (self::TABLES as $table) {
            foreach ($this->serialColumns($target, $table) as $column) {
                $sequence = $target->selectOne(
                    'select pg_get_serial_sequence(?, ?) as sequence',
                    ['public.'.$table, $column],
                );
                $name = $sequence->sequence ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }
                [$schema, $sequenceName] = $this->sequenceName($name);

                $max = $target->table($table)->max($column);
                if ($max === null) {
                    continue;
                }

                $state = $target->selectOne("select last_value, is_called from \"{$schema}\".\"{$sequenceName}\"");
                $last = (int) $state->last_value;
                $called = filter_var($state->is_called, FILTER_VALIDATE_BOOLEAN);
                $next = $called ? $last + 1 : $last;
                if ($next <= (int) $max) {
                    $problems[] = "{$table}.{$column} sequence {$name} would next return {$next} but the max id is {$max}";
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function serialColumns(Connection $target, string $table): array
    {
        $rows = $target->select(
            "select column_name from information_schema.columns
             where table_schema = 'public' and table_name = ?
             and (is_identity = 'YES' or column_default like 'nextval(%')",
            [$this->identifier($table)],
        );

        return array_map(
            fn (object $row): string => $this->identifier((string) array_change_key_case((array) $row, CASE_LOWER)['column_name']),
            $rows,
        );
    }

    /**
     * @return list<array{table: string, column: string, foreign_table: string, foreign_column: string}>
     */
    private function foreignKeys(Connection $target): array
    {
        $rows = $target->select(<<<'SQL'
            select
                src.relname as table_name,
                src_attr.attname as column_name,
                ref.relname as foreign_table_name,
                ref_attr.attname as foreign_column_name
            from pg_constraint con
            join pg_class src on src.oid = con.conrelid
            join pg_namespace ns on ns.oid = src.relnamespace
            join pg_class ref on ref.oid = con.confrelid
            join lateral unnest(con.conkey, con.confkey) as cols(src_attnum, ref_attnum) on true
            join pg_attribute src_attr on src_attr.attrelid = src.oid and src_attr.attnum = cols.src_attnum
            join pg_attribute ref_attr on ref_attr.attrelid = ref.oid and ref_attr.attnum = cols.ref_attnum
            where con.contype = 'f'
              and ns.nspname = 'public'
            SQL);

        $keys = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array) $row, CASE_LOWER);
            $keys[] = [
                'table' => $this->identifier((string) $row['table_name']),
                'column' => $this->identifier((string) $row['column_name']),
                'foreign_table' => $this->identifier((string) $row['foreign_table_name']),
                'foreign_column' => $this->identifier((string) $row['foreign_column_name']),
            ];
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function orphanCounts(Connection $target): array
    {
        $problems = [];
        foreach ($this->foreignKeys($target) as $foreignKey) {
            $table = $foreignKey['table'];
            $column = $foreignKey['column'];
            $foreignTable = $foreignKey['foreign_table'];
            $foreignColumn = $foreignKey['foreign_column'];
            $count = $target->selectOne(
                "select count(*) as orphans from \"{$table}\" as child
                 left join \"{$foreignTable}\" as parent on parent.\"{$foreignColumn}\" = child.\"{$column}\"
                 where child.\"{$column}\" is not null and parent.\"{$foreignColumn}\" is null",
            );
            $orphans = (int) ($count->orphans ?? 0);
            if ($orphans > 0) {
                $problems[] = "{$table}.{$column} has {$orphans} orphaned rows";
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>  $keys
     * @return array{count: int, key_hash: string}
     */
    private function profile(Connection $connection, string $table, array $keys): array
    {
        $query = $connection->table($table);
        foreach ($keys as $key) {
            $query->orderBy($key);
        }

        $lines = [];
        foreach ($query->cursor() as $record) {
            $row = array_change_key_case((array) $record, CASE_LOWER);
            $parts = [];
            foreach ($keys as $key) {
                $value = $row[$key] ?? null;
                $parts[] = $value === null ? '' : (string) $value;
            }
            $lines[] = implode('|', $parts);
        }

        return [
            'count' => count($lines),
            'key_hash' => hash('sha256', implode("\n", $lines)),
        ];
    }

    /**
     * @return list<array{name: string, data_type: string, nullable: bool}>
     */
    private function columns(Connection $connection, string $table): array
    {
        $schema = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            ? (string) $connection->getDatabaseName()
            : 'public';
        $rows = $connection->select(
            'select column_name, data_type, is_nullable
             from information_schema.columns
             where table_schema = ? and table_name = ?
             order by ordinal_position',
            [$schema, $this->identifier($table)],
        );

        return array_map(function (object $row): array {
            $row = array_change_key_case((array) $row, CASE_LOWER);

            return [
                'name' => $this->identifier((string) $row['column_name']),
                'data_type' => (string) $row['data_type'],
                'nullable' => strtoupper((string) $row['is_nullable']) === 'YES',
            ];
        }, $rows);
    }

    /**
     * @return list<string>
     */
    private function primaryKey(Connection $connection, string $table): array
    {
        $schema = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            ? (string) $connection->getDatabaseName()
            : 'public';
        $rows = $connection->select(
            'select kcu.column_name
             from information_schema.table_constraints tc
             join information_schema.key_column_usage kcu
               on tc.constraint_name = kcu.constraint_name
              and tc.table_schema = kcu.table_schema
              and tc.table_name = kcu.table_name
             where tc.constraint_type = \'PRIMARY KEY\'
               and tc.table_schema = ?
               and tc.table_name = ?
             order by kcu.ordinal_position',
            [$schema, $this->identifier($table)],
        );
        $keys = array_map(
            fn (object $row): string => $this->identifier((string) array_change_key_case((array) $row, CASE_LOWER)['column_name']),
            $rows,
        );

        if ($keys === []) {
            throw new PostgresCopyRejected("Table [{$table}] has no primary key.");
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function tableNames(Connection $connection): array
    {
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $rows = $connection->select(
                "select table_name from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE'",
            );
        } else {
            $rows = $connection->select(
                "select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'",
            );
        }

        return array_map(
            fn (object $row): string => strtolower((string) array_change_key_case((array) $row, CASE_LOWER)['table_name']),
            $rows,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sequenceName(string $name): array
    {
        $parts = explode('.', $name);
        if (count($parts) === 1) {
            $parts = ['public', $parts[0]];
        }
        if (count($parts) !== 2) {
            throw new PostgresCopyRejected("Unexpected sequence name [{$name}].");
        }

        return [$this->identifier($parts[0]), $this->identifier($parts[1])];
    }

    private function identifier(string $name): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new PostgresCopyRejected("Unexpected identifier [{$name}].");
        }

        return $name;
    }
}
