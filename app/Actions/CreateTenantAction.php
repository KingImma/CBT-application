<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StatusType;
use App\Exceptions\TenantSlugAlreadyTakenException;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class CreateTenantAction
{
    private const TRIAL_DAYS = 30;
    private const DB_PREFIX  = 'tenant_';
    private const MAX_IDENTIFIER_LENGTH = 63;

    /**
     * @param array<string, mixed> $data
     * @throws TenantSlugAlreadyTakenException
     */
    public function execute(array $data): Tenant
    {
        $raw = Str::slug($data['name']);
        $slug = Str::slug(mb_strtolower(\Normalizer::normalize($raw, \Normalizer::FORM_KC)));
        $slug = Str::limit($slug, self::MAX_IDENTIFIER_LENGTH);
        
        $dbName = self::DB_PREFIX . str_replace('-', '_', $slug);
        $dbName = Str::limit($dbName, self::MAX_IDENTIFIER_LENGTH);
        
        $lock = Cache::lock('tenant_slug:' . $slug, 10);
        if (! $lock->get()) {
            throw new TenantSlugAlreadyTakenException($slug);
        }

        try { 
            $subscriptionDetails = $this->resolveSubscriptionDetails($data['plan_id'] ?? null);
            
            try {
                $tenant = Tenant::create([
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
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    throw new TenantSlugAlreadyTakenException($slug);
                }
                throw $e;
            }

            $centralDomain = config('app.central_domain')
                ?? collect(config('tenancy.central_domains', []))
                    ->reject(fn($d) => in_array($d, ['127.0.0.1', 'localhost']))
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
    
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        // Postgres 23505, MySQL 23000/1062
        $code = $e->getCode();
        return in_array($code, ['23505', '23000', '1062'], true);
    }
    
    /**
     * Resolve the subscription status and dates based on provided plan.
     *
     * @param string|null $planId
     * @return array {subscription_status: string, trial_ends_at: \Illuminate\Support\Carbon|null, subscription_ends_at: \Illuminate\Support\Carbon|null}
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

             $plan = SubscriptionPlan::query()->find($planId);
             $isAnnual = $plan && $plan->interval === 'yearly';

             return [
                 'subscription_status' => StatusType::Active->value,
                 'trial_ends_at' => null,
                 'subscription_ends_at' => $isAnnual ? now()->addYears(1) : now()->addMonth(),
             ];
         }

}
