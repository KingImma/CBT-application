<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Actions;

use Illuminate\Support\Facades\DB;

class RemoveTenantUserIndex
{
    public function execute(string $email): void
    {
        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where(['email' => $email, 'tenant_id' => tenant('id')])
            ->delete();
    }
}
