<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StatusType;
use App\Exceptions\TenantSlugAlreadyTakenException;
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
            // Create tenant — UUID PK stays handled by stancl/app/model
            try {
                $tenant = Tenant::create([
                    'name'                => $data['name'],
                    'slug'                => $slug,
                    'database'            => $dbName,
                    'email'               => $data['email']   ?? null,
                    'phone'               => $data['phone']   ?? null,
                    'address'             => $data['address'] ?? null,
                    'city'                => $data['city']    ?? null,
                    'state'               => $data['state']   ?? null,
                    'plan_id'             => $data['plan_id'] ?? null,
                    'subscription_status' => StatusType::Trial->value,
                    'trial_ends_at'       => now()->addDays(self::TRIAL_DAYS),
                    'is_active'           => true,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    throw new TenantSlugAlreadyTakenException($slug);
                }
                throw $e;
            }

            $tenant->domains()->create([
                'domain' => $slug . '.' . config('app.central_domain', 'localhost'),
            ]);

            return $tenant;
        } finally {
            $lock->release();
        }
    }

    // private function ensureSlugIsAvailable(string $slug): void
    // {
    //     if (Tenant::where('slug', $slug)->exists()) {
    //         throw new TenantSlugAlreadyTakenException($slug);
    //     }
    // }
    
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        // Postgres 23505, MySQL 23000/1062
        $code = $e->getCode();
        return in_array($code, ['23505', '23000', '1062'], true);
    }
}

