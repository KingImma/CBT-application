<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\StatusType;
use App\Exceptions\TenantSlugAlreadyTakenException;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreateTenantAction
{
    private const TRIAL_DAYS            = 30;
    private const DB_PREFIX             = 'tenant_';
    private const MAX_IDENTIFIER_LENGTH = 63;

    /**
     * @param array<string, mixed> $data
     * @throws TenantSlugAlreadyTakenException
     */
    public function execute(array $data): Tenant
    {
        $slug   = $this->buildSlug($data['name']);
        $dbName = Str::limit(self::DB_PREFIX . str_replace('-', '_', $slug), self::MAX_IDENTIFIER_LENGTH);

        $lock = Cache::lock('tenant_slug:' . $slug, 10);

        if (! $lock->get()) {
            throw new TenantSlugAlreadyTakenException($slug);
        }

        try {
            $this->ensureSlugIsAvailable($slug);

            $subscriptionDetails = $this->resolveSubscriptionDetails($data['plan_id'] ?? null);

            try {
                $tenant = Tenant::create([
                    'id'                   => $slug,
                    'name'                 => $data['name'],
                    'slug'                 => $slug,
                    'database'             => $dbName,
                    'email'                => $data['email']   ?? null,
                    'phone'                => $data['phone']   ?? null,
                    'address'              => $data['address'] ?? null,
                    'city'                 => $data['city']    ?? null,
                    'state'                => $data['state']   ?? null,
                    'plan_id'              => $data['plan_id'] ?? null,
                    'subscription_status'  => $subscriptionDetails['subscription_status'],
                    'trial_ends_at'        => $subscriptionDetails['trial_ends_at'],
                    'subscription_ends_at' => $subscriptionDetails['subscription_ends_at'],
                    'is_active'            => true,

                    // Admin credentials stored temporarily in settings JSON.
                    // Picked up by TenantDatabaseSeeder after DB provisioning.
                    // Cleared from settings after the admin user is created.
                    'settings' => [
                        'onboarding_admin' => [
                            'first_name' => $data['admin_first_name'],
                            'last_name'  => $data['admin_last_name'],
                            'email'      => $data['admin_email'],
                            'password'   => $data['admin_password'], // hashed in seeder
                        ],
                    ],
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    throw new TenantSlugAlreadyTakenException($slug);
                }
                throw $e;
            }

            $centralDomain = config('app.central_domain')
                ?? collect(config('tenancy.central_domains', []))
                    ->reject(fn ($d) => in_array($d, ['127.0.0.1', 'localhost']))
                    ->first()
                ?? 'localhost';

            $tenant->domains()->create([
                'domain' => $slug . '.' . $centralDomain,
            ]);

            $tenant->load('domains');

            return $tenant;

        } finally {
            $lock->release();
        }
    }

    private function buildSlug(string $name): string
    {
        $slug = Str::slug(mb_strtolower($name));
        return Str::limit($slug, self::MAX_IDENTIFIER_LENGTH);
    }

    private function ensureSlugIsAvailable(string $slug): void
    {
        if (Tenant::where('slug', $slug)->exists()) {
            throw new TenantSlugAlreadyTakenException($slug);
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23505', '23000', '1062'], true);
    }

    private function resolveSubscriptionDetails(?string $planId): array
    {
        if (empty($planId)) {
            return [
                'subscription_status'  => StatusType::Trial->value,
                'trial_ends_at'        => now()->addDays(self::TRIAL_DAYS),
                'subscription_ends_at' => null,
            ];
        }

        $plan     = SubscriptionPlan::find($planId);
        $isAnnual = $plan && $plan->interval === 'yearly';

        return [
            'subscription_status'  => StatusType::Active->value,
            'trial_ends_at'        => null,
            'subscription_ends_at' => $isAnnual ? now()->addYear() : now()->addMonth(),
        ];
    }
}