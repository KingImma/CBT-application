<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Actions;

use App\Enums\StatusType;
use App\Domains\Tenancy\Exceptions\TenantProvisioningException;
use App\Domains\Tenancy\Exceptions\TenantSlugAlreadyTakenException;
use App\Jobs\ProvisionTenantDetailsJob;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateTenant
{
    private const DB_PREFIX = 'tenant_';

    private const MAX_IDENTIFIER_LENGTH = 63;

    private const TRIAL_DAYS = 30;

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

    /**
     * @param  callable(): mixed  $callback
     */
    private function withSlugLock(string $slug, callable $callback): mixed
    {
        $lock = Cache::lock("tenant_slug:{$slug}", 10);

        if (! $lock->get()) {
            throw new TenantSlugAlreadyTakenException($slug);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function ensureSlugAvailable(string $slug): void
    {
        if (
            Tenant::where('slug', $slug)->exists() ||
            DB::table('domains')->where('domain', 'like', "{$slug}.%")->exists()
        ) {
            throw new TenantSlugAlreadyTakenException($slug);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveSubscriptionDetails(?string $planId): array
    {
        if (empty($planId)) {
            return [
                'subscription_status' => StatusType::Trial->value,
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'subscription_ends_at' => null,
            ];
        }

        $plan = SubscriptionPlan::find($planId);
        $endsAt = $plan?->interval === 'yearly' ? now()->addYear() : now()->addMonth();

        return [
            'subscription_status' => StatusType::Active->value,
            'trial_ends_at' => null,
            'subscription_ends_at' => $endsAt,
        ];
    }

    private function resolveCentralDomain(): string
    {
        return config('app.central_domain')
            ?? collect(config('tenancy.central_domains', []))
                ->reject(fn ($d) => in_array($d, ['127.0.0.1', 'localhost'], true))
                ->first()
            ?? 'localhost';
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
