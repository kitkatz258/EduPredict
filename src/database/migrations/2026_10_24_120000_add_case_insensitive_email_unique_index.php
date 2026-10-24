<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // MariaDB 11.4 rejects MySQL's ((expression)) index syntax. Its
        // utf8mb4_unicode_ci unique index on email is already case-insensitive.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->assertMariaDbEmailCollationIsCaseInsensitive();

            return;
        }

        $expression = match ($driver) {
            'pgsql', 'sqlite' => '(LOWER(email))',
            default => throw new RuntimeException("Unsupported driver [{$driver}] for email uniqueness."),
        };

        Schema::getConnection()->statement(
            "CREATE UNIQUE INDEX users_email_lower_unique ON users {$expression}",
        );
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_email_lower_unique');
        });
    }

    private function assertMariaDbEmailCollationIsCaseInsensitive(): void
    {
        $row = Schema::getConnection()->selectOne(
            'SELECT COLLATION_NAME AS collation_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['users', 'email'],
        );
        $collation = strtolower((string) ($row->collation_name ?? ''));

        if (! str_ends_with($collation, '_ci')) {
            throw new RuntimeException("users.email collation [{$collation}] is case-sensitive. MariaDB cannot store a LOWER(email) expression index, so case-only duplicates would be allowed.");
        }
    }
};
