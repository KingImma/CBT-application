<?php

namespace App\Concerns;

use App\Exceptions\TenantSlugAlreadyTakenException;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

trait ValidatesTenantSlug
{
    protected function ensureSlugAvailable(string $slug): void
    {
        if (
            Tenant::where('slug', $slug)->exists() || 
            DB::table('domains')->where('domain', 'like', "{$slug}.%")->exists()
        ) {
            throw new TenantSlugAlreadyTakenException($slug);
        }
    }
}