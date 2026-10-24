<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Database\MariaDbToPostgresCopier;
use App\Services\Database\PostgresCopyRejected;
use Illuminate\Console\Command;
use Throwable;

class RollbackPostgresDataCommand extends Command
{
    protected $signature = 'edupredict:rollback-postgres-data
        {--target=pgsql : PostgreSQL connection to clear}
        {--database= : Must exactly match the development database name}';

    protected $description = 'Remove copied rows from the PostgreSQL development database and reset its sequences';

    public function handle(MariaDbToPostgresCopier $copier): int
    {
        $database = (string) $this->option('database');
        if ($database === '') {
            $this->error('Pass --database with the PostgreSQL development database name. This command does not touch MariaDB.');

            return self::FAILURE;
        }

        $this->line('MariaDB is not modified. Point DB_CONNECTION back at mysql to use that database again.');
        $this->line("This clears copied rows in PostgreSQL database [{$database}] and keeps the schema.");

        try {
            $copier->clearDevelopmentDatabase((string) $this->option('target'), $database);
        } catch (PostgresCopyRejected $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Cleared copied rows in [{$database}]. Sequences were restarted. The schema and MariaDB were left in place.");

        return self::SUCCESS;
    }
}
