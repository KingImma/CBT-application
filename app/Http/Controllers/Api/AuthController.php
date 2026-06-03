<?php

namespace App\Http\Controllers\Api;

use App\Data\Student\StudentData;
use App\Data\Teacher\TeacherData;
use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\Auth\SuperAdminAuthService;
use App\Services\Auth\TenantAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication & Onboarding
 * * APIs for user login, password resets, and initial school onboarding.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly SuperAdminAuthService $superAdminAuth,
        private readonly TenantAuthService $tenantAuth
    ) {}

    /**
     * Authenticate a user and return a token.
     *
     * @subgroup Login & Session
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 1. TENANT PATH (School Admin, Teacher, Student)
        // If the middleware initialized a tenant, we are in the school's database.
        if (tenant()) {
            return ApiResponse::success($this->tenantAuth->authenticate(
                $request->identifier,
                $request->password
            ), 'Login successful.');
        }

        // 2. CENTRAL PATH (Super Admin)
        // If tenant() is null, we are safely on the central database.
        $superAdmin = SuperAdmin::where('email', $request->identifier)
            ->where('is_active', true)
            ->first();

        if ($superAdmin) {
            return $this->superAdminAuth->authenticate($superAdmin, $request->password);
        }

        // Fallback for invalid central credentials
        return ApiResponse::error('Invalid credentials.', 401);
    }

    /**
     * Logout the user and invalidate the token.
     *
     * @subgroup Login & Session
     */
    public function logout(Request $request): JsonResponse
    {
        // Detect auth guard and delete token
        $user = $request->user('super_admin') ?? $request->user('tenant');

        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::message('Logged out successfully');
    }

    /**
     * Get the authenticated user's profile.
     *
     * @subgroup Login & Session
     */
    public function me(Request $request): JsonResponse
    {
        // If we are in a tenant DB, pull the tenant user. Otherwise, pull super admin.
        $user = tenant() ? $request->user('tenant') : $request->user('super_admin');

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        if ($user instanceof SuperAdmin) {
            return ApiResponse::success([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => 'super_admin',
            ], 'Profile retrieved successfully.');
        }

        $role = $user->getRoleNames()->first();

        $sessionData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $role,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'type' => 'tenant_user',
            'tenant_handle' => tenant('handle'),
        ];

        $roleData = match ($role) {
            'teacher' => TeacherData::from(
                $user->loadMissing('teacherProfile.classLevel', 'teacherAssignments.subject')
            ),

            'student' => StudentData::from(
                $user->loadMissing('studentProfile.classArm.classLevel')
            ),

            // School Admins typically don't need a profile resource,
            // so they safely return an empty array to merge.
            default => []
        };

        $finalPayload = array_merge($sessionData, $roleData);

        return ApiResponse::success($finalPayload, 'Profile retrieved successfully.');
    }
}
