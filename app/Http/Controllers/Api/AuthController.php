<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\SuperAdminAuthService;
use App\Services\TenantAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UniversalAuthController extends Controller
{
    public function __construct(
        private readonly SuperAdminAuthService $superAdminAuth,
        private readonly TenantAuthService $tenantAuth
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Super admin path
        $superAdmin = SuperAdmin::where('email', $request->email)
            ->where('is_active', true)
            ->first();
            
        if ($superAdmin) {
            return $this->superAdminAuth->authenticate($superAdmin, $request->password);
        }

        // Tenant user path (tenant already resolved by middleware)
        return response()->json($this->tenantAuth->authenticate(
            $request->email,
            $request->password
        ));
    }
    
    public function logout(Request $request): JsonResponse
    {
        // Detect auth guard and delete token
        $user = $request->user('sanctum') ?? $request->user('tenant');
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
   
    public function me(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user('tenant');
        
        if ($user instanceof SuperAdmin) {
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => 'super_admin',
            ]);
        }

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'type' => 'tenant_user',
            'tenant_handle' => tenant('handle'),
        ]);
    }
}