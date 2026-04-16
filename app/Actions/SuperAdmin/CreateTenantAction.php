<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\StatusType;
use App\Exceptions\TenantSlugAlreadyTakenException;
use App\Exceptions\Tenant\TenantProvisioningException;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantAction
{
    private const TRIAL_DAYS = 30;
    private const DB_PREFIX = 'tenant_';
    private const MAX_IDENTIFIER_LENGTH = 63;
    private const LOCK_TIMEOUT_SECONDS = 10;

    /**
     * @param array{
     *     name: string,
     *     admin_first_name: string,
     *     admin_last_name: string,
     *     admin_email: string,
     *     admin_password: string,
     *     plan_id?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     state?: string|null
     * } $data
     * @throws TenantSlugAlreadyTakenException|TenantProvisioningException
     */
    public function execute(array $data): Tenant
    {
        $slug = $this->generateUniqueSlug($data['name']);
        $databaseName = $this->generateDatabaseName($slug);

        $lock = Cache::lock("tenant_slug:{$slug}", self::LOCK_TIMEOUT_SECONDS);

        if (!$lock->get()) {
            throw new TenantSlugAlreadyTakenException($slug);
        }

        try {
            $tenant = $this->createTenantRecord($data, $slug, $databaseName);
            $this->createTenantDomain($tenant);
            $tenant->load('domains');

            return $tenant;
        } finally {
            $lock->release();
        }
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::limit(Str::slug(mb_strtolower($name)), self::MAX_IDENTIFIER_LENGTH);
        
        if (!$this->isSlugAvailable($slug)) {
            throw new TenantSlugAlreadyTakenException($slug);
        }

        return $slug;
    }

    private function generateDatabaseName(string $slug): string
    {
        return Str::limit(
            self::DB_PREFIX . str_replace('-', '_', $slug), 
            self::MAX_IDENTIFIER_LENGTH
        );
    }

    private function isSlugAvailable(string $slug): bool
    {
        return !(
            Tenant::where('slug', $slug)->exists() || 
            DB::table('domains')->where('domain', 'like', "{$slug}.%")->exists()
        );
    }

    private function createTenantRecord(array $data, string $slug, string $databaseName): Tenant
    {
        try {
            return Tenant::create($this->buildTenantAttributes($data, $slug, $databaseName));
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new TenantSlugAlreadyTakenException($slug);
            }

            throw new TenantProvisioningException($slug, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTenantAttributes(array $data, string $slug, string $databaseName): array
    {
        $subscriptionDetails = $this->getSubscriptionDetails($data['plan_id'] ?? null);

        return [
            'id' => $slug,
            'name' => $data['name'],
            'slug' => $slug,
            'database' => $databaseName,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
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
        ];
    }

    private function createTenantDomain(Tenant $tenant): void
    {
        try {
            $domain = $tenant->slug . '.' . $this->resolveCentralDomain();
            $tenant->domains()->create(['domain' => $domain]);
        } catch (\Exception $e) {
            $tenant->delete(); // Triggers automatic database cleanup
            throw new TenantProvisioningException(
                $tenant->slug, 
                "Domain mapping failed: {$e->getMessage()}"
            );
        }
    }

    private function resolveCentralDomain(): string
    {
        return config('app.central_domain')
            ?? collect(config('tenancy.central_domains', []))
                ->reject(fn($domain) => in_array($domain, ['127.0.0.1', 'localhost'], true))
                ->first()
            ?? 'localhost';
    }

    /**
     * @return array{subscription_status: string, trial_ends_at: ?\Illuminate\Support\Carbon, subscription_ends_at: ?\Illuminate\Support\Carbon}
     */
    private function getSubscriptionDetails(?string $planId): array
    {
        if (empty($planId)) {
            return [
                'subscription_status' => StatusType::Trial->value,
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'subscription_ends_at' => null,
            ];
        }

        $plan = SubscriptionPlan::find($planId);
        $isAnnual = $plan?->interval === 'yearly';

        return [
            'subscription_status' => StatusType::Active->value,
            'trial_ends_at' => null,
            'subscription_ends_at' => $isAnnual 
                ? now()->addYear() 
                : now()->addMonth(),
        ];
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23505', '23000', '1062'], true);
    }
}