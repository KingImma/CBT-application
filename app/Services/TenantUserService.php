<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantUserService
{
    private function centralTable(): Builder
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index');
    }

    public function updateCentralIndex(string $email, string $role): void
    {
        $this->centralTable()->updateOrInsert(
            ['email' => $email, 'tenant_id' => tenant('id')],
            ['role' => $role, 'updated_at' => now()],
        );
    }

    public function removeFromCentralIndex(string $email): void
    {
        $this->centralTable()
            ->where(['email' => $email, 'tenant_id' => tenant('id')])
            ->delete();
    }

    public function userExists(string $email): bool
    {
        return $this->centralTable()
            ->where('email', $email)
            ->exists();
    }

    public function getTenantUsers(): Collection
    {
        return $this->centralTable()
            ->where('tenant_id', tenant('id'))
            ->select(['email', 'role', 'created_at', 'updated_at'])
            ->get();
    }

    public function getTenantForEmail(string $email): ?string
    {
        $record = $this->centralTable()
            ->where('email', $email)
            ->select(['tenant_id'])
            ->first();

        return $record?->tenant_id;
    }

    public function updateUserRole(string $email, string $newRole): void
    {
        $this->centralTable()
            ->where(['email' => $email, 'tenant_id' => tenant('id')])
            ->update(['role' => $newRole, 'updated_at' => now()]);
    }
}
