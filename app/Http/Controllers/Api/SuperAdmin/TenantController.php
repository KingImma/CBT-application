<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Actions\SuperAdmin\CreateTenantAction;
use App\Actions\SuperAdmin\DeleteTenantAction;
use App\Actions\SuperAdmin\ReinstateTenantAction;
use App\Actions\SuperAdmin\SuspendTenantAction;
use App\Actions\SuperAdmin\UpdateTenantAction;
use App\Data\Tenant\TenantData;
use App\Events\ActivityFeedEvent;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Super Admin: Tenant Management
 * * APIs for provisioning, suspending, and managing school instances.
 */
class TenantController extends Controller
{
    /**
     * List all tenants with optional filters.
     *
     * @subgroup Tenant CRUD
     *
     * @queryParam search string Search by name or slug. No-example
     * @queryParam status string Filter by subscription status. No-example
     * @queryParam is_active bool Filter by active status. No-example
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->with('plan') // 1. Eager load the plan to prevent N+1 queries
            ->when($request->search, fn ($query) => $query->where('name', 'ilike', "%{$request->search}%")
                ->orWhere('slug', 'ilike', "%{$request->search}%")
            )
            ->when($request->status, fn ($query) => $query->where('subscription_status', $request->status)
            )
            ->when($request->is_active !== null, fn ($query) => $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated(
            $tenants,
            'Tenants retrieved successfully.',
            TenantData::collect($tenants->getCollection())
        );
    }

    /**
     * Create a new tenant (school).
     *
     * @subgroup Tenant CRUD
     *
     * @bodyParam name string required School name. No-example
     * @bodyParam slug string required School slug/handle. No-example
     * @bodyParam domain string required School domain. No-example
     * @bodyParam plan_id string nullable Subscription plan UUID. No-example
     */
    public function store(StoreTenantRequest $request, CreateTenantAction $action): JsonResponse
    {
        $tenant = $action->execute($request->validated());

        broadcast(new ActivityFeedEvent(
            channelType: 'super_admin',
            channelId: 'platform',
            action: 'tenant.created',
            description: "New school '{$tenant->name}' registered.",
            meta: ['tenant_id' => $tenant->id],
        ));

        return ApiResponse::created(
            TenantData::from($tenant),
            'School registered successfully.'
        );
    }

    /**
     * Get a single tenant with its domains.
     *
     * @subgroup Tenant CRUD
     *
     * @urlParam id string required The tenant UUID.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        return ApiResponse::success(TenantData::from($tenant), 'Tenant retrieved successfully.');
    }

    /**
     * Update a tenant.
     *
     * @subgroup Tenant CRUD
     *
     * @urlParam id string required The tenant UUID.
     */
    public function update(UpdateTenantRequest $request, string $id, UpdateTenantAction $action): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $updatedTenant = $action->handle($request->validated(), $tenant);

        return ApiResponse::success(
            TenantData::from($updatedTenant->load('domains')),
            'Tenant updated successfully.'
        );
    }

    /**
     * Suspend a tenant's access.
     *
     * @subgroup Tenant Status
     *
     * @urlParam id string required The tenant UUID.
     */
    public function suspend(string $id, SuspendTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $action->handle($tenant);

        return ApiResponse::success(
            TenantData::from($tenant),
            'Tenant suspended successfully.'
        );
    }

    /**
     * Reinstate a suspended tenant.
     *
     * @subgroup Tenant Status
     *
     * @urlParam id string required The tenant UUID.
     */
    public function reinstate(string $id, ReinstateTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $action->handle($tenant);

        return ApiResponse::success(
            TenantData::from($tenant),
            'Tenant reinstated successfully.'
        );
    }

    /**
     * Permanently delete a tenant.
     *
     * @subgroup Tenant Status
     *
     * @urlParam id string required The tenant UUID.
     */
    public function destroy(string $id, DeleteTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $action->handle($tenant);

        return ApiResponse::message('Tenant deleted successfully');
    }
}
