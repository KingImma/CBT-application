<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Exceptions\TenantSlugAlreadyTakenException;

use App\Http\Controllers\Controller;
use App\Models\Tenant;

use Illuminate\Http\JsonResponse;
use App\Http\Resources\TenantResource;

use Illuminate\Http\Request;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;

use App\Actions\CreateTenantAction;
use App\Actions\Tenants\UpdateTenantAction;
use App\Actions\Tenants\DeleteTenantAction;
use App\Actions\Tenants\SuspendTenantAction;
use App\Actions\Tenants\ReinstateTenantAction;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->when($request->search, fn($query) => 
                $query->where('name', 'ilike', "%{$request->search}%")
                    ->orWhere('slug', 'ilike', "%{$request->search}%")
            )
            ->when($request->status, fn($query) => 
                $query->where('subscription_status', $request->status)
            )
            ->when($request->is_active !== null, fn($query) => 
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderByDesc('created_at')
            ->paginate(20);
        
        return response()->json($tenants);
    }
    
    public function store(StoreTenantRequest $request, CreateTenantAction $action): JsonResponse
    {
        try{
            $tenant = $action->execute($request->validated());
        }catch(TenantSlugAlreadyTakenException $e){
            return response()->json([
                'message' => $e->getMessage()
            ], 409);
        }
        
        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }
    
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        return response()->json(new TenantResource($tenant))->setStatusCode(200);
    }
    
    public function update(UpdateTenantRequest $request, string $id, UpdateTenantAction $action): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $updatedTenant = $action->handle($request->validated(), $tenant);
        
        return response()->json(new TenantResource($updatedTenant->load('domains')));
    }
    
    public function suspend(string $id, SuspendTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $action->handle($tenant);
        
        return response()->json([
            'message' => 'Tenant suspended successfully',
            'tenant'  =>  new TenantResource($tenant)
        ]);
    }
    
    public function reinstate(string $id, ReinstateTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $action->handle($tenant);
        
        return response()->json([
            'message' => 'Tenant reinstated successfully',
            'tenant'  => new TenantResource($tenant),
        ]);
    }
    
    public function destroy(string $id, DeleteTenantAction $action): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        
        $action->handle($tenant);
        
        return response()->json(['message' => 'Tenant deleted successfully']);
    }
}
