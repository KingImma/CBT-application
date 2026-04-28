<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\Auth\SuperAdminAuthService;
use App\Services\Auth\TenantAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
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

        // 1. TENANT PATH (School Admin, Teacher, Student)
        // If the middleware initialized a tenant, we are in the school's database.
        if (tenant()) {
            return response()->json($this->tenantAuth->authenticate(
                $request->email,
                $request->password
            ));
        }

        // 2. CENTRAL PATH (Super Admin)
        // If tenant() is null, we are safely on the central database.
        $superAdmin = SuperAdmin::where('email', $request->email)
            ->where('is_active', true)
            ->first();
            
        if ($superAdmin) {
            return $this->superAdminAuth->authenticate($superAdmin, $request->password);
        }

        // Fallback for invalid central credentials
        return response()->json([
            'success' => false, 
            'message' => 'Invalid credentials.'
        ], 401);
    }
    
    public function logout(Request $request): JsonResponse
    {
        // Detect auth guard and delete token
        $user = $request->user('sanctum') ?? $request->user('tenant');
        
        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        // If we are in a tenant DB, pull the tenant user. Otherwise, pull super admin.
        $user = tenant() ? $request->user('tenant') : $request->user('sanctum');
        
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
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