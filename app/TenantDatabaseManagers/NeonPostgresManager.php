<?php

declare(strict_types=1);

namespace App\TenantDatabaseManagers;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;

class NeonPostgresManager extends PostgreSQLDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        return DB::connection('pgsql_direct')->statement(
            "CREATE DATABASE \"{$tenant->database()->getName()}\" WITH TEMPLATE=template0"
        );
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        return DB::connection('pgsql_direct')->statement(
            "DROP DATABASE \"{$tenant->database()->getName()}\""
        );
    }

    public function databaseExists(string $name): bool
    {
        return (bool) DB::connection('pgsql_direct')
            ->select('SELECT datname FROM pg_database WHERE datname = ?', [$name]);
    }

    /**
     * @param  array<string, mixed>  $baseConfig
     * @return array<string, mixed>
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        unset($baseConfig['url']);

        $url = (string) (config('database.connections.pgsql_direct.url')
            ?: config('database.connections.pgsql.url'));

        $parsed = $url !== '' ? parse_url($url) : [];

        if (! isset($parsed['host'], $parsed['user'], $parsed['pass'])) {
            $pgsql = config('database.connections.pgsql', []);

            $parsed = [
                'host' => $parsed['host'] ?? $pgsql['host'] ?? env('DB_HOST', '127.0.0.1'),
                'port' => $parsed['port'] ?? $pgsql['port'] ?? env('DB_PORT', 5432),
                'user' => $parsed['user'] ?? $pgsql['username'] ?? env('DB_USERNAME'),
                'pass' => $parsed['pass'] ?? $pgsql['password'] ?? env('DB_PASSWORD'),
            ];
        }

        if (! isset($parsed['host'], $parsed['user'], $parsed['pass'])) {
            throw new RuntimeException(
                'Invalid DATABASE_URL_DIRECT configuration.'
            );
        }

        $baseConfig['driver'] = 'pgsql';
        $baseConfig['host'] = $parsed['host'];
        $baseConfig['port'] = $parsed['port'] ?? 5432;
        $baseConfig['username'] = $parsed['user'];
        $baseConfig['password'] = $parsed['pass'];
        $baseConfig['database'] = $databaseName;
        $baseConfig['sslmode'] = 'require';

        return $baseConfig;
    }
}
