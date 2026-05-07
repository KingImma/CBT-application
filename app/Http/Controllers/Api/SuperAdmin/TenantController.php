<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Actions\SuperAdmin\CreateTenantAction;
use App\Actions\SuperAdmin\DeleteTenantAction;
use App\Actions\SuperAdmin\ReinstateTenantAction;
use App\Actions\SuperAdmin\SuspendTenantAction;
use App\Actions\SuperAdmin\UpdateTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
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
            TenantResource::collection($tenants->getCollection())->resolve()
        );
    }

    public function store(StoreTenantRequest $request, CreateTenantAction $action): JsonResponse
    {
        $tenant = $action->execute($request->validated());

        return ApiResponse::created(
            (new TenantResource($tenant))->resolve(),
            'School registered successfully.'
        );
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        return ApiResponse::success((new TenantResource($tenant))->resolve(), 'Tenant retrieved successfully.');
    }

    public function update(UpdateTenantRequest $request, string $id, UpdateTenantAction $action): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $updatedTenant = $action->handle($request->validated(), $tenant);

        return ApiResponse::success(
            (new TenantResource($updatedTenant->load('domains')))->resolve(),
            'Tenant updated successfully.'
        );
    }

    public function suspend(string $id, SuspendTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $action->handle($tenant);

        return ApiResponse::success(
            (new TenantResource($tenant))->resolve(),
            'Tenant suspended successfully.'
        );
    }

    public function reinstate(string $id, ReinstateTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $action->handle($tenant);

        return ApiResponse::success(
            (new TenantResource($tenant))->resolve(),
            'Tenant reinstated successfully.'
        );
    }

    public function destroy(string $id, DeleteTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $action->handle($tenant);

        return ApiResponse::message('Tenant deleted successfully');
    }
}
