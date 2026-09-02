<?php

declare(strict_types=1);

namespace App\TenantDatabaseManagers;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;
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
}
