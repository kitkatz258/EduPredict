<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Database\MariaDbToPostgresCopier;
use App\Services\Database\PostgresCopyRejected;
use Illuminate\Console\Command;
use Throwable;

class CopyMariaDbToPostgresCommand extends Command
{
    protected $signature = 'edupredict:copy-mysql-to-pgsql
        {--source=mysql : Read-only MariaDB connection name}
        {--target=pgsql : Empty PostgreSQL development connection}
        {--dry-run : Count rows without writing}
        {--verify-only : Compare keys and foreign keys without writing}';

    protected $description = 'Copy MariaDB rows into the empty PostgreSQL development database, preserving primary keys';

    public function handle(MariaDbToPostgresCopier $copier): int
    {
        $source = (string) $this->option('source');
        $target = (string) $this->option('target');

        $this->line('The source session is read-only. This command does not rebuild the schema and does not change MariaDB.');
        $this->line('Take a MariaDB dump and a PostgreSQL dump before treating the copy as the application database.');

        try {
            if ($this->option('dry-run')) {
                $rows = $copier->dryRun($source, $target);
                $this->table(['Table', 'MariaDB', 'PostgreSQL'], array_map(
                    fn (array $row): array => [$row['table'], $row['source'], $row['target']],
                    $rows,
                ));
                $this->info('Dry run finished. No rows were written.');

                return self::SUCCESS;
            }

            if ($this->option('verify-only')) {
                $rows = $copier->verifyOnly($source, $target);
                $this->printVerified($rows);
                $this->info('Primary keys match and PostgreSQL foreign keys have no orphans.');

                return self::SUCCESS;
            }

            $rows = $copier->copy($source, $target);
            $this->printVerified($rows);
            $this->info('Copy finished. Primary keys match and foreign keys have no orphans.');
            $this->line('Keep the MariaDB database until the PostgreSQL copy has been checked. Switch back by restoring DB_CONNECTION=mysql.');

            return self::SUCCESS;
        } catch (PostgresCopyRejected $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  list<array{table: string, source: int, target: int, keys_match: bool}>  $rows
     */
    private function printVerified(array $rows): void
    {
        $this->table(['Table', 'MariaDB', 'PostgreSQL', 'Keys'], array_map(
            fn (array $row): array => [$row['table'], $row['source'], $row['target'], $row['keys_match'] ? 'match' : 'mismatch'],
            $rows,
        ));
    }
}
