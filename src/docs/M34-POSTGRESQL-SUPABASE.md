# M34 — PostgreSQL compatibility and Supabase readiness

MariaDB 11.4 remains the default application database. PostgreSQL 17 is available beside it. This document is the repeatable path from MariaDB to PostgreSQL. It does not connect to a hosted Supabase project.

## What was already portable

Migrations use the Laravel schema builder. They do not set a storage engine, character set, or `ENUM`. `after()` is MySQL-only positioning and PostgreSQL ignores it, so a fresh PostgreSQL schema has the same columns and keys with a different physical column order. Integer, decimal, JSON, text, and foreign-key definitions map to PostgreSQL types. Grouped analytics queries already group by every selected column. Login already compares `LOWER(email)`. Encrypted socioeconomic fields and socioeconomic questionnaire answers are application ciphertext in text columns; PostgreSQL does not change that ciphertext.

## What PostgreSQL rejected or changed

- `LIKE` is case-sensitive on PostgreSQL. MariaDB's `utf8mb4_unicode_ci` collation and SQLite's ASCII `LIKE` are not. Shared tables now use Laravel `whereLike` / `orWhereLike`, which compiles to `ILIKE` on PostgreSQL and ordinary `LIKE` on MariaDB.
- A unique index on `users.email` is case-sensitive on PostgreSQL and SQLite, so `Admin@school.edu` and `admin@school.edu` could both be stored. Migration `2026_10_24_120000_add_case_insensitive_email_unique_index` adds a unique index on `LOWER(email)` for those drivers. MariaDB 11.4 rejects MySQL expression-index syntax. Its existing `utf8mb4_unicode_ci` unique index on `email` already rejects case-only duplicates, and the migration checks that the collation still ends in `_ci`.
- Laravel sends booleans as integers `0` and `1`. With native prepared statements PostgreSQL rejects those for `boolean` columns. `App\Database\PostgresConnection` binds real booleans.
- PostgreSQL client encoding must stay `utf8`. `utf8mb4` is a MySQL name and is not used for the `pgsql` connection.
- PHPUnit's default file forces SQLite `:memory:`. A shell or container that already has `DB_CONNECTION=pgsql` cannot make `RefreshDatabase` rebuild a populated database.

`migrate:fresh` is allowed only against `edupredict_testing`, and only from `phpunit.pgsql.xml`. Do not run it against the MariaDB database, `edupredict`, or `edupredict_defense`.

## Local databases

`docker-compose.postgres.yml` is its own Compose project (`edupredict-m34-pg`). Volumes are `edupredict_m34_pg_data` and `edupredict_m34_mariadb_data`. It does not mount the shared MariaDB volume.

| Database | Who connects | Purpose |
|---|---|---|
| `edupredict` | role `edupredict` | Local PostgreSQL development and the only database the copy/rollback commands may change |
| `edupredict_testing` | role `edupredict` | PHPUnit on PostgreSQL. Disposable |
| `edupredict_defense` | role `edupredict_defense`, which cannot log in | Reserved empty database. The development role cannot connect |

The rehearsal MariaDB in this file listens on host port `3308` and is not `edupredict_db`. The PostgreSQL port on the host is `5433`. The app port is `8017`.

The local password in that compose file is the same development password already used by the MariaDB compose file. It is not a Supabase password. `edupredict_defense` is created without a login password. Before any real defense use, set a password outside git:

```sql
ALTER ROLE edupredict_defense LOGIN PASSWORD 'replace-with-a-password-from-your-secret-store';
```

Do not commit that password. Keep the defense Supabase project, or a future defense server, in an untracked `.env.defense`. Do not put those values in the development `.env`.

## Start the isolated stack

From the repository root of this worktree:

```bash
docker compose -f docker-compose.postgres.yml up -d --build
docker compose -f docker-compose.postgres.yml exec app composer install --no-interaction
docker compose -f docker-compose.postgres.yml exec app cp .env.pgsql.example .env
docker compose -f docker-compose.postgres.yml exec app php artisan key:generate
docker compose -f docker-compose.postgres.yml exec app php artisan migrate --force --no-interaction
```

`php artisan migrate` here creates the empty development schema. It does not load a dump and it does not call `migrate:fresh`. Copy `.env.pgsql.example` only when `.env` does not already exist. `key:generate` writes `src/.env`, which is gitignored. Keep that `APP_KEY` if you later copy encrypted rows; a new key makes ciphertext unreadable.

PostgreSQL tests:

```bash
docker compose -f docker-compose.postgres.yml exec app php artisan test --configuration=phpunit.pgsql.xml
```

The default `php artisan test` still uses SQLite and is the right command for the MariaDB stack.

## Supabase, when a project exists

Use `.env.supabase.example` as the checklist. Leave the real password, project reference, and URL out of git.

- Migrations, `pg_dump`, `pg_restore`, and `edupredict:copy-mysql-to-pgsql` use the direct host or the session pooler on port `5432`. `DB_EMULATE_PREPARES=false`.
- The running app can use the transaction pooler on port `6543`. That mode cannot use server-side prepared statements, so set `DB_EMULATE_PREPARES=true` for that process only.
- Set `DB_SSLMODE=require`. Use `verify-full` only with a CA file referenced by `DB_SSLROOTCERT` and stored outside the repository.
- Do not run migrations through the transaction pooler.
- Use a separate Supabase project for defense. Do not reuse the development database name, role, or password.

No hosted Supabase database is configured or contacted by this milestone.

## Copy MariaDB to PostgreSQL

Preconditions:

1. Both databases are migrated to the same revision. The PostgreSQL development database has the schema and no application rows.
2. You have a MariaDB dump taken with `mysqldump` (or `mariadb-dump`) without `--add-drop-database` against a server you intend to read. The copy command also sets the source session to `READ ONLY`.
3. `APP_KEY` on the PostgreSQL app is the key that encrypted the MariaDB ciphertext. Do not rotate it as part of the copy.
4. The target database name is `edupredict`. The command rejects `edupredict_testing` and `edupredict_defense`.

The isolated rehearsal, which does not use the shared MariaDB container:

```bash
docker compose -f docker-compose.postgres.yml exec \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=mariadb \
  -e DB_PORT=3306 \
  -e DB_DATABASE=edupredict \
  -e DB_USERNAME=edupredict \
  -e DB_PASSWORD=secret \
  app php artisan migrate --seed --force --no-interaction

docker compose -f docker-compose.postgres.yml exec \
  -e MYSQL_HOST=mariadb \
  -e MYSQL_PORT=3306 \
  -e MYSQL_DATABASE=edupredict \
  -e MYSQL_USERNAME=edupredict \
  -e MYSQL_PASSWORD=secret \
  app php artisan edupredict:copy-mysql-to-pgsql --dry-run

docker compose -f docker-compose.postgres.yml exec \
  -e MYSQL_HOST=mariadb \
  -e MYSQL_PORT=3306 \
  -e MYSQL_DATABASE=edupredict \
  -e MYSQL_USERNAME=edupredict \
  -e MYSQL_PASSWORD=secret \
  app php artisan edupredict:copy-mysql-to-pgsql
```

The command copies tables in foreign-key order, preserves primary keys, nulls a self-reference such as `grade_reports.supersedes_id` until the parent row exists, then restores it. Boolean `0`/`1` values become booleans. JSON text is validated and inserted unchanged. After the load it moves each sequence past `MAX(id)`. It then checks that every primary-key set matches and that every foreign key has zero orphaned rows.

A cutover is a configuration change: point `DB_CONNECTION` at PostgreSQL and restart the app. Take both dumps before that change.

### Backup commands

MariaDB, read-only dump, from whichever MariaDB container you are actually migrating:

```bash
docker exec <mariadb-container> mariadb-dump -uedupredict -p --single-transaction --routines --triggers edupredict > edupredict-mariadb.sql
```

PostgreSQL development database, from the isolated Postgres container:

```bash
docker compose -f docker-compose.postgres.yml exec postgres \
  pg_dump -U edupredict -d edupredict --format=custom --file=/tmp/edupredict-before-cutover.dump
docker compose -f docker-compose.postgres.yml cp postgres:/tmp/edupredict-before-cutover.dump ./edupredict-before-cutover.dump
```

Do not commit either dump. They contain personal student data and ciphertext.

## Rollback

MariaDB is not modified by the copy, so the application rollback is to point `DB_*` back at MariaDB and restart. Keep that database until the PostgreSQL copy has been checked.

To empty a bad PostgreSQL development copy and try again, without dropping the schema and without touching MariaDB:

```bash
docker compose -f docker-compose.postgres.yml exec app \
  php artisan edupredict:rollback-postgres-data --database=edupredict
```

The command refuses any database name other than the configured development database. It truncates the copied tables and restarts their sequences. It does not call `migrate:fresh`.

To restore a PostgreSQL dump instead:

```bash
docker compose -f docker-compose.postgres.yml exec postgres \
  pg_restore -U edupredict -d edupredict --clean --if-exists /tmp/edupredict-before-cutover.dump
```

Run `pg_restore` only against the database you just dumped. Do not aim it at `edupredict_defense` or at MariaDB.

## Risks

- Column order differs on PostgreSQL because `after()` is ignored. Row contents and keys do not depend on order. A hand-written `INSERT` that lists values without column names would break; the copier names every column.
- Encrypted values survive only when `APP_KEY` is unchanged. Losing the key makes socioeconomic profiles and protected questionnaire answers unreadable. There is no second copy of the key in git.
- Case-insensitive search follows Unicode case folding on PostgreSQL (`ILIKE`) and the MariaDB collation on MariaDB. Ordinary Latin text matches. Unusual case pairs can still differ.
- `ORDER BY` on text uses each engine's collation. PostgreSQL's default locale can place "BA Communication" after "Bachelor..." because spaces are not primary letters. MariaDB `utf8mb4_unicode_ci` and SQLite do not. Lists stay stable on one database. Nothing in the copy depends on that order.
- The email index blocks new case-only duplicates. Existing MariaDB data cannot contain them under `utf8mb4_unicode_ci`.
- Sequences are moved after the copy. A failed copy rolls the inserted rows back. `setval` is not transactional in PostgreSQL; rerunning the copy sets the sequences from the loaded ids again.
- Identity columns are `bigserial`. Explicit ids are preserved. The next application insert uses `MAX(id) + 1`.
- `edupredict_testing` is owned by the development role so PHPUnit can rebuild it. It is still a different database. The test guard refuses to boot PostgreSQL tests when the connected database is not `edupredict_testing`.
- Supabase's transaction pooler will fail migrations and native prepared statements until `DB_EMULATE_PREPARES=true` and the migration is run on the session/direct port.
- A future defense database must stay empty until its own migration window. Do not seed it with development demo accounts.
