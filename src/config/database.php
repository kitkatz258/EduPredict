<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

$activeConnection = env('DB_CONNECTION', 'sqlite');
$usingMysql = in_array($activeConnection, ['mysql', 'mariadb'], true);
$usingPgsql = $activeConnection === 'pgsql';
$emulatePrepares = filter_var(env('DB_EMULATE_PREPARES', false), FILTER_VALIDATE_BOOLEAN);
$pgsqlOptions = extension_loaded('pdo_pgsql') && $emulatePrepares
    ? [PDO::ATTR_EMULATE_PREPARES => true]
    : [];
$sslRootCert = env('DB_SSLROOTCERT');
$sslRootCert = is_string($sslRootCert) && $sslRootCert !== '' ? $sslRootCert : null;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => $usingMysql ? env('DB_URL') : env('MYSQL_URL'),
            'host' => $usingMysql ? env('DB_HOST', '127.0.0.1') : env('MYSQL_HOST', '127.0.0.1'),
            'port' => $usingMysql ? env('DB_PORT', '3306') : env('MYSQL_PORT', '3306'),
            'database' => $usingMysql ? env('DB_DATABASE', 'laravel') : env('MYSQL_DATABASE', 'edupredict'),
            'username' => $usingMysql ? env('DB_USERNAME', 'root') : env('MYSQL_USERNAME', 'edupredict'),
            'password' => $usingMysql ? env('DB_PASSWORD', '') : env('MYSQL_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => $usingMysql ? env('DB_URL') : env('MYSQL_URL'),
            'host' => $usingMysql ? env('DB_HOST', '127.0.0.1') : env('MYSQL_HOST', '127.0.0.1'),
            'port' => $usingMysql ? env('DB_PORT', '3306') : env('MYSQL_PORT', '3306'),
            'database' => $usingMysql ? env('DB_DATABASE', 'laravel') : env('MYSQL_DATABASE', 'edupredict'),
            'username' => $usingMysql ? env('DB_USERNAME', 'root') : env('MYSQL_USERNAME', 'edupredict'),
            'password' => $usingMysql ? env('DB_PASSWORD', '') : env('MYSQL_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => $usingPgsql ? env('DB_URL') : env('PGSQL_URL'),
            'host' => $usingPgsql ? env('DB_HOST', '127.0.0.1') : env('PGSQL_HOST', '127.0.0.1'),
            'port' => $usingPgsql ? env('DB_PORT', '5432') : env('PGSQL_PORT', '5432'),
            'database' => $usingPgsql ? env('DB_DATABASE', 'edupredict') : env('PGSQL_DATABASE', 'edupredict'),
            'username' => $usingPgsql ? env('DB_USERNAME', 'edupredict') : env('PGSQL_USERNAME', 'edupredict'),
            'password' => $usingPgsql ? env('DB_PASSWORD', '') : env('PGSQL_PASSWORD', ''),
            // PostgreSQL rejects MySQL's utf8mb4 client encoding. Keep this utf8.
            'charset' => env('PGSQL_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'sslrootcert' => $sslRootCert,
            'application_name' => env('DB_APPLICATION_NAME', 'edupredict'),
            'connect_via_database' => env('DB_CONNECT_VIA_DATABASE'),
            'connect_via_port' => env('DB_CONNECT_VIA_PORT'),
            // Supabase's transaction pooler (port 6543) cannot use server-side prepares.
            'options' => $pgsqlOptions,
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
