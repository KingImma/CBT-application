<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Actions;

use Illuminate\Support\Facades\DB;

class SyncTenantUser
{
    public function execute(string $email, string $role): void
    {
        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->updateOrInsert(
                ['email' => $email, 'tenant_id' => tenant('id')],
                ['role' => $role, 'updated_at' => now()],
            );
    }
}
