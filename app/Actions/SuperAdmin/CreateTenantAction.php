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
    private const TRIAL_DAYS            = 30;
    private const DB_PREFIX             = 'tenant_';
    private const MAX_IDENTIFIER_LENGTH = 63;

    /**
     * @param array<string, mixed> $data
     * @throws TenantSlugAlreadyTakenException|TenantProvisioningException
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

            // Wrap everything in a database transaction
            $tenant = DB::transaction(function () use ($data, $slug, $dbName, $subscriptionDetails) {
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
                        'settings' => [
                            'onboarding_admin' => [
                                'first_name' => $data['admin_first_name'],
                                'last_name'  => $data['admin_last_name'],
                                'email'      => $data['admin_email'],
                                'password'   => $data['admin_password'],
                            ],
                        ],
                    ]);
                } catch (QueryException $e) {
                    if ($this->isUniqueViolation($e)) {
                        throw new TenantSlugAlreadyTakenException($slug);
                    }
                    throw new TenantProvisioningException($slug, $e->getMessage());
                }

                $centralDomain = config('app.central_domain')
                    ?? collect(config('tenancy.central_domains', []))
                        ->reject(fn ($d) => in_array($d, ['127.0.0.1', 'localhost']))
                        ->first()
                    ?? 'localhost';

                // If this fails, the $tenant creation above is completely undone.
                $tenant->domains()->create([
                    'domain' => $slug . '.' . $centralDomain,
                ]);

                return $tenant;
            }); // End Transaction

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
        // Check BOTH the tenants table and the domains table to prevent orphan blocks
        if (
            Tenant::where('slug', $slug)->exists() || 
            \Illuminate\Support\Facades\DB::table('domains')->where('domain', 'like', $slug . '.%')->exists()
        ) {
            throw new TenantSlugAlreadyTakenException($slug);
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string)$e->getCode(), ['23505', '23000', '1062'], true);
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