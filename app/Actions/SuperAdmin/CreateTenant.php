<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Concerns\HasLocking;
use App\Concerns\ResolvesSubscriptionDetails;
use App\Concerns\ValidatesTenantSlug;
use App\Exceptions\Tenant\TenantProvisioningException;
use App\Jobs\ProvisionTenantDetailsJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateTenant
{
    use HasLocking, ResolvesSubscriptionDetails, ValidatesTenantSlug;

    private const DB_PREFIX = 'tenant_';

    private const MAX_IDENTIFIER_LENGTH = 63;

    public function execute(array $data): Tenant
    {
        $slug = $this->generateUniqueSlug($data['name']);

        return $this->withSlugLock($slug, function () use ($data, $slug) {
            $adminData = [
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'email' => $data['admin_email'],
                'phone' => $data['admin_phone'] ?? null,
                'password' => $data['admin_password'],
            ];

            $curriculumData = $data['curriculum'] ?? [];

            $tenant = Tenant::create($this->buildTenantData($data, $slug));

            try {
                $tenant->domains()->create(['domain' => "{$slug}.{$this->resolveCentralDomain()}"]);
                $tenant->load('domains');

                ProvisionTenantDetailsJob::dispatch($tenant, $adminData, $curriculumData);
            } catch (\Exception $e) {
                $this->cleanupOrphanedTenant($tenant, $slug, $e);
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

    private function cleanupOrphanedTenant(Tenant $tenant, string $slug, \Exception $original): void
    {
        try {
            $tenant->delete();
        } catch (\Exception $cleanupException) {
            Log::critical('Orphaned tenant database after failed provisioning', [
                'slug' => $slug,
                'database' => $tenant->database,
                'original_error' => $original->getMessage(),
                'cleanup_error' => $cleanupException->getMessage(),
            ]);
        }
    }

    private function buildTenantData(array $data, string $slug): array
    {
        $dbName = Str::limit(self::DB_PREFIX.str_replace('-', '_', $slug), self::MAX_IDENTIFIER_LENGTH);
        $subscriptionDetails = $this->resolveSubscriptionDetails($data['plan_id'] ?? null);

        $cleanData = array_diff_key($data, array_flip([
            'admin_first_name', 'admin_last_name', 'admin_email', 'admin_password', 'admin_phone', 'plan_id', 'curriculum',
        ]));

        return array_merge([
            'id' => $slug,
            'name' => $data['name'],
            'slug' => $slug,
            'handle' => $slug,
            'database' => $dbName,
            'email' => $data['admin_email'],
            'phone' => $data['admin_phone'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
            'settings' => [],
            'subscription_status' => $subscriptionDetails['subscription_status'],
            'trial_ends_at' => $subscriptionDetails['trial_ends_at'],
            'subscription_ends_at' => $subscriptionDetails['subscription_ends_at'],
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ], $cleanData);
    }
}
