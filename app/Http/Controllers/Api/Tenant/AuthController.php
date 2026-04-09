<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Tenant-scoped login.
     * Detects role and returns it with the token so the frontend
     * can route to the correct dashboard (admin vs teacher vs student).
     */
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            "email" => ["required", "email"],
            "password" => ["required", "string"],
        ]);

        // 1. Look up the email in the central index
        // This tells us exactly which school this email belongs to without needing a header.
        $indexRecord = \Illuminate\Support\Facades\DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where('email', $request->email)
            ->first();

        if (!$indexRecord) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "email" => ["The provided credentials are incorrect."],
            ]);
        }

        // 2. Initialize the tenant dynamically
        tenancy()->initialize($indexRecord->tenant_id);

        // 3. Now safely connected to the tenant's isolated DB.
        // Look up the actual user model and verify the password.
        $user = \App\Models\Tenant\User::where("email", $request->email)->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "email" => ["The provided credentials are incorrect."],
            ]);
        }

        // 4. Verify account status
        if (!$user->is_active) {
            return response()->json([
                "message" => "Your account has been deactivated. Contact your school admin.",
            ], 403);
        }

        // 5. Delete previous tokens for this device — enforce single session per user
        $user->tokens()->where("name", "tenant-token")->delete();

        // 6. Determine role and expiration
        $role = $user->getRoleNames()->first(); // school_admin | teacher | student

        // Students get a shorter window (e.g., for exams), Admins/Teachers get a full day
        $expiresAt = match ($role) {
            "student" => now()->addHours(4),
            default => now()->addHours(8), 
        };

        // 7. Generate the Sanctum token
        $token = $user->createToken("tenant-token", ["*"], $expiresAt)->plainTextToken;

        // 8. Return comprehensive payload including the critical tenant_slug
        return response()->json([
            "token" => $token,
            "token_type" => "Bearer",
            "expires_in" => (int) now()->diffInSeconds($expiresAt),
            "tenant_slug" => tenant('slug'), // Frontend MUST save this to localStorage
            "user" => [
                "id" => $user->id,
                "name" => trim($user->first_name . " " . $user->last_name),
                "email" => $user->email,
                "role" => $role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user("tenant")->currentAccessToken()->delete();

        return response()->json(["message" => "Logged out successfully."]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user("tenant");

        return response()->json([
            "id" => $user->id,
            "first_name" => $user->first_name,
            "last_name" => $user->last_name,
            "email" => $user->email,
            "role" => $user->getRoleNames()->first(),
            "permissions" => $user->getAllPermissions()->pluck("name"),
        ]);
    }
}
