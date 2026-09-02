<?php

declare(strict_types=1);

namespace App\TenantDatabaseManagers;

use Illuminate\Support\Facades\DB;
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
            ->select("SELECT datname FROM pg_database WHERE datname = ?", [$name]);
    }

    /**
     * @param  array<string, mixed>  $baseConfig
     * @return array<string, mixed>
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        unset($baseConfig['url']);

        $urlKey = app()->bound('tenancy.provisioning')
            ? 'database.connections.pgsql_direct.url'
            : 'database.connections.pgsql.url';

        $parsed = parse_url((string) config($urlKey));

        $baseConfig['host'] = $parsed['host'];
        $baseConfig['port'] = $parsed['port'] ?? 5432;
        $baseConfig['username'] = $parsed['user'];
        $baseConfig['password'] = $parsed['pass'];
        $baseConfig['database'] = $databaseName;

        return $baseConfig;
    }
}
