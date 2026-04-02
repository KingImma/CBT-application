<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Enums\StatusType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Exceptions\TenantSlugAlreadyTakenException;

class CreateTenantAction
{
    private const TRIAL_DAYS = 30;
    private const DB_PREFIX  = 'tenant_';
    /**
     * Create a new tenant.
     *
     * @param array<int,mixed> $data
     * @return Tenant
     */
     public function execute(array $data): Tenant
     {
        $slug = Str::slug($data['name']);
    
        $this->ensureSlugIsAvailable($slug);
    
        $tenant = DB::transaction(function () use ($data, $slug) {
            $tenant = Tenant::create([
                'name'                => $data['name'],
                'slug'                => $slug,
                'database'            => self::DB_PREFIX . str_replace('-', '_', $slug),
                'email'               => $data['email'] ?? null,
                'phone'               => $data['phone'] ?? null,
                'address'             => $data['address'] ?? null,
                'city'                => $data['city'] ?? null,
                'state'               => $data['state'] ?? null,
                'plan_id'             => $data['plan_id'] ?? null,
                'subscription_status' => StatusType::Trial->value,
                'trial_ends_at'       => now()->addDays(self::TRIAL_DAYS),
                'is_active'           => true,
            ]);
    
            $tenant->domains()->create([
                'domain' => $slug . '.' . config('app.central_domain', 'localhost'),
            ]);
    
            return $tenant;
        });
    
        return $tenant;
     }
    
    private function ensureSlugIsAvailable(string $slug): void
    {
        if (Tenant::where('slug', $slug)->exists()) {
            throw new TenantSlugAlreadyTakenException($slug);
        }
    }
}
