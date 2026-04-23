<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Concerns\ValidatesTenantSlug;
use App\Concerns\ResolvesSubscriptionDetails;
use App\Concerns\HasLocking;
use App\Exceptions\Tenant\TenantProvisioningException;
use App\Jobs\ProvisionTenantDetailsJob;
use App\Jobs\SendSchoolWelcomeEmail;
use App\Models\Tenant;
use Illuminate\Support\Str;

/*
 * 1. What it is: The finalized `CreateTenantAction`.
 * 2. What it does in a nutshell: Generates the tenant record, locks the slug, saves the database metadata, and dispatches the asynchronous jobs (provisioning and email) without saving temporary state to the database.
 * 3. Why this was chosen: It perfectly separates the HTTP request cycle from the heavy database operations. It keeps the central `tenants` table free of temporary JSON clutter, ensuring your database remains strictly a source of truth for infrastructure, not application state.
 * 4. Expected deliverables and alternatives: A fast, clean tenant creation process returning the model for the controller to use. The alternative was the previous method of temporarily mutating the `settings` column, which required manual database cleanup logic.
 */

class CreateTenantAction
{
    use ValidatesTenantSlug, ResolvesSubscriptionDetails, HasLocking;

    private const DB_PREFIX = 'tenant_';
    private const MAX_IDENTIFIER_LENGTH = 63;
    /**
     * @param array<int,mixed> $data
     */
    public function execute(array $data): Tenant
    {
        $slug = $this->generateUniqueSlug($data['name']);
        
        return $this->withSlugLock($slug, function () use ($data, $slug) {
            
            // 1. Isolate the setup data before building the DB record
            $adminData = [
                'first_name' => $data['admin_first_name'],
                'last_name'  => $data['admin_last_name'],
                'email'      => $data['admin_email'],
                'phone'      => $data['admin_phone'] ?? null,
                'password'   => $data['admin_password'],
            ];
            
            $curriculumData = $data['curriculum'] ?? [];

            // 2. Create the strictly clean Tenant record
            $tenant = Tenant::create($this->buildTenantData($data, $slug));
            
            try {
                // 3. Attach the domain mapping
                $tenant->domains()->create(['domain' => "{$slug}.{$this->resolveCentralDomain()}"]);
                $tenant->load('domains');

                // 4. Dispatch the heavy lifting to the queue
                ProvisionTenantDetailsJob::dispatch($tenant, $adminData, $curriculumData);
                
                SendSchoolWelcomeEmail::dispatch(
                    adminEmail: $data['admin_email'],
                    adminName:  trim(($data['admin_first_name'] ?? '') . ' ' . ($data['admin_last_name'] ?? '')),
                    schoolName: $tenant->name,
                    handle:     $tenant->handle,
                    loginUrl:   "https://{$tenant->handle}.{$this->resolveCentralDomain()}/login",
                )->onQueue('emails');

            } catch (\Exception $e) {
                // Rollback if domain creation or job dispatching fails
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
    /**
     * @param array<int,mixed> $data
     * @return array<string|array-key,mixed|<missing>>*/
     
    private function buildTenantData(array $data, string $slug): array
    {
        $dbName = Str::limit(self::DB_PREFIX . str_replace('-', '_', $slug), self::MAX_IDENTIFIER_LENGTH);
        $subscriptionDetails = $this->resolveSubscriptionDetails($data['plan_id'] ?? null);

        // Strip out all the onboarding-specific fields so they don't get saved to the DB
        $cleanData = array_diff_key($data, array_flip([
            'admin_first_name', 'admin_last_name', 'admin_email', 'admin_password', 'admin_phone', 'plan_id', 'curriculum'
        ]));

        return array_merge([
            'id' => $slug,
            'name' => $data['name'],
            'school_type' => $data['schoolType'] ?? null,
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