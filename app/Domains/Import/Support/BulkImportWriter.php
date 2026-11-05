<?php

declare(strict_types=1);

namespace App\Domains\Import\Support;

use App\Enums\RoleType;
use App\Models\Tenant\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Batched write helpers shared by the student and teacher CSV importers.
 *
 * Every method here is intentionally query-builder based: bulk imports must not
 * pay for Eloquent model instantiation, model events or per-row transactions.
 */
class BulkImportWriter
{
    public function __construct(
        private readonly string $connectionName = 'tenant',
    ) {
    }

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function chunkSize(): int
    {
        $configured = (int) config('imports.chunk_size', 500);

        return max(50, min($configured, 2000));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, list<array<string, mixed>>>
     */
    public function chunk(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        return array_chunk($rows, $this->chunkSize());
    }

    /**
     * @param  list<mixed>  $values
     * @return array<int, list<mixed>>
     */
    public function chunkValues(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return array_chunk($values, $this->chunkSize());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function insert(string $table, array $rows, bool $ignore = false): void
    {
        foreach ($this->chunk($rows) as $chunk) {
            $query = $this->connection()->table($table);

            $ignore ? $query->insertOrIgnore($chunk) : $query->insert($chunk);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>  $update
     */
    public function upsert(string $table, array $rows, array $uniqueBy, array $update): void
    {
        foreach ($this->chunk($rows) as $chunk) {
            $this->connection()->table($table)->upsert($chunk, $uniqueBy, $update);
        }
    }

    /**
     * Attach a single guard role to many freshly created users at once.
     *
     * @param  list<string>  $userIds
     */
    public function assignRole(RoleType $role, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $table = config('permission.table_names.model_has_roles');
        $roleId = Role::findOrCreate($role->value, 'tenant')->getKey();
        $modelType = (new User())->getMorphClass();

        $pivot = [];

        foreach ($userIds as $userId) {
            $pivot[] = [
                'role_id' => $roleId,
                'model_type' => $modelType,
                'model_id' => $userId,
            ];
        }

        $this->insert($table, $pivot, true);
    }

    /**
     * Mirror newly imported users into the central login index.
     *
     * Runs after the tenant transaction commits: the index lives on a different
     * database, so it cannot be part of that transaction. It is idempotent.
     *
     * @param  list<string>  $emails
     */
    public function syncTenantUserIndex(RoleType $role, array $emails): void
    {
        $tenantId = tenant('id');

        if ($tenantId === null) {
            return;
        }

        $emails = array_values(array_unique(array_filter(
            array_map(static fn ($email): string => trim((string) $email), $emails),
            static fn (string $email): bool => $email !== '',
        )));

        if ($emails === []) {
            return;
        }

        $central = DB::connection(config('tenancy.database.central_connection'));
        $now = now();

        foreach ($this->chunkValues($emails) as $chunk) {
            $rows = [];

            foreach ($chunk as $email) {
                $rows[] = [
                    'email' => $email,
                    'tenant_id' => $tenantId,
                    'role' => $role->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $central->table('tenant_user_index')->upsert(
                $rows,
                ['email', 'tenant_id'],
                ['role', 'updated_at'],
            );
        }
    }

    /**
     * Allocate a contiguous block of sequential identifiers, e.g. STU/2026/0001.
     *
     * The current maximum suffix is read once and then incremented in memory.
     * On PostgreSQL the read happens under a transaction-scoped advisory lock so
     * two concurrent imports (or an import racing an interactive create) cannot
     * hand out the same number. Call this from inside the writing transaction.
     *
     * @param  array<string, true>  $reserved  identifiers already used by the file itself
     * @return Closure(): string
     */
    public function sequentialNumberAllocator(
        string $prefix,
        string $table,
        string $column,
        int $pad,
        array $reserved = [],
    ): Closure {
        if ($this->connection()->getConfig('driver') === 'pgsql') {
            $this->connection()->statement(
                'SELECT pg_advisory_xact_lock(?)',
                [crc32($table.'.'.$column)],
            );

            $row = $this->connection()->table($table)
                ->where($column, 'like', $prefix.'%')
                ->selectRaw("MAX(CAST(SUBSTRING({$column} FROM '[0-9]+$') AS INTEGER)) AS max_sequence")
                ->first();

            $sequence = (int) ($row->max_sequence ?? 0);
        } else {
            // Portable fallback (used by local/sqlite test databases).
            $sequence = 0;

            $existing = $this->connection()->table($table)
                ->where($column, 'like', $prefix.'%')
                ->pluck($column);

            foreach ($existing as $identifier) {
                if (preg_match('/(\d+)$/', (string) $identifier, $matches)) {
                    $sequence = max($sequence, (int) $matches[1]);
                }
            }
        }

        return function () use (&$sequence, $prefix, $pad, &$reserved): string {
            do {
                $sequence++;
                $candidate = $prefix.str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT);
            } while (isset($reserved[$candidate]));

            $reserved[$candidate] = true;

            return $candidate;
        };
    }
}