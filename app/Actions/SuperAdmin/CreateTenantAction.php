<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Concerns\ValidatesTenantSlug;
use App\Concerns\ResolvesSubscriptionDetails;
use App\Concerns\HasLocking;
use App\Exceptions\Tenant\TenantProvisioningException;
use App\Models\Tenant;
use Illuminate\Support\Str;

class CreateTenantAction
{
    use ValidatesTenantSlug, ResolvesSubscriptionDetails, HasLocking;

    private const DB_PREFIX = 'tenant_';
    private const MAX_IDENTIFIER_LENGTH = 63;

    /**
     * @param array{name: string, admin_first_name: string, admin_last_name: string, admin_email: string, admin_password: string, plan_id?: string|null} $data
     */
    public function execute(array $data): Tenant
    {
        $slug = $this->generateUniqueSlug($data['name']);
        
        return $this->withSlugLock($slug, function () use ($data, $slug) {
            $tenant = Tenant::create($this->buildTenantData($data, $slug));
            
            try {
                $tenant->domains()->create(['domain' => "{$slug}.{$this->resolveCentralDomain()}"]);
                $tenant->load('domains');
            } catch (\Exception $e) {
                $tenant->delete();
                throw new TenantProvisioningException($slug, $e->getMessage());
            }

            return $tenant;
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::limit(Str::slug(mb_strtolower($name)), self::MAX_IDENTIFIER_LENGTH);
        $this->ensureSlugAvailable($slug);
        return $slug;
    }

    private function buildTenantData(array $data, string $slug): array
    {
        $dbName = Str::limit(self::DB_PREFIX . str_replace('-', '_', $slug), self::MAX_IDENTIFIER_LENGTH);
        $subscriptionDetails = $this->resolveSubscriptionDetails($data['plan_id'] ?? null);

        return array_merge([
            'id' => $slug,
            'name' => $data['name'],
            'slug' => $slug,
            'database' => $dbName,
            'subscription_status' => $subscriptionDetails['subscription_status'],
            'trial_ends_at' => $subscriptionDetails['trial_ends_at'],
            'subscription_ends_at' => $subscriptionDetails['subscription_ends_at'],
            'is_active' => true,
            'settings' => [
                'onboarding_admin' => [
                    'first_name' => $data['admin_first_name'],
                    'last_name' => $data['admin_last_name'],
                    'email' => $data['admin_email'],
                    'password' => $data['admin_password'],
                ],
            ],
        ], array_diff_key($data, array_flip(['name', 'admin_first_name', 'admin_last_name', 'admin_email', 'admin_password', 'plan_id'])));
    }
}