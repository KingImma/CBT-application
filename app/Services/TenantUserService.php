<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantUserService
{
    /**
     * Update or insert a user in the central tenant_user_index table.
     * Called when creating new users (students, teachers, admins).
     */
    public function updateCentralIndex(string $email, string $role): void
    {
        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->updateOrInsert(
                ['email' => $email, 'tenant_id' => tenant('id')],
                ['role' => $role, 'updated_at' => now(), 'created_at' => now()]
            );
    }

    /**
     * Remove a user from the central tenant_user_index table.
     * Called when permanently deleting a user.
     */
    public function removeFromCentralIndex(string $email): void
    {
        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where(['email' => $email, 'tenant_id' => tenant('id')])
            ->delete();
    }

    /**
     * Check if a user exists in the central tenant_user_index table.
     * Useful for preventing duplicate registrations across tenants.
     */
    public function userExists(string $email): bool
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where('email', $email)
            ->exists();
    }

    /**
     * Get all users for the current tenant from the central index.
     * Returns collection with email, role, created_at, updated_at.
     */
    public function getTenantUsers(): Collection
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where('tenant_id', tenant('id'))
            ->select(['email', 'role', 'created_at', 'updated_at'])
            ->get();
    }

    /**
     * Get tenant ID for a given email from the central index.
     * Returns null if email not found.
     */
    public function getTenantForEmail(string $email): ?string
    {
        $record = DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where('email', $email)
            ->select(['tenant_id'])
            ->first();

        return $record?->tenant_id;
    }

    /**
     * Update the role for a user in the central tenant_user_index table.
     * Called when changing a user's role.
     */
    public function updateUserRole(string $email, string $newRole): void
    {
        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where(['email' => $email, 'tenant_id' => tenant('id')])
            ->update(['role' => $newRole, 'updated_at' => now()]);
    }
}
